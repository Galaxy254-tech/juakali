<?php
/**
 * User Management Interface
 * Comprehensive user administration and role management
 */

session_start();
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../integrations/credit-scoring.php';

// Check if user is logged in and is admin
if (!isLoggedIn() || $_SESSION['user_role'] !== 'admin') {
    header('Location: ../login.php');
    exit;
}

$db = Database::getInstance();
$creditScoring = new CreditScoringEngine($db);

// Handle user actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    switch ($action) {
        case 'create_user':
            handleCreateUser($db);
            break;
        case 'update_user':
            handleUpdateUser($db);
            break;
        case 'suspend_user':
            handleSuspendUser($db);
            break;
        case 'activate_user':
            handleActivateUser($db);
            break;
        case 'reset_password':
            handleResetPassword($db);
            break;
        case 'update_role':
            handleUpdateRole($db);
            break;
    }
}

// Get users data
$users = getUsers($db);
$userStats = getUserStatistics($db);
$recentRegistrations = getRecentRegistrations($db);
$roleDistribution = getRoleDistribution($db);

function getUsers($db) {
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $limit = 20;
    $offset = ($page - 1) * $limit;

    $filters = [];
    $params = [];

    // Apply filters
    if (!empty($_GET['role'])) {
        $filters[] = "u.role = ?";
        $params[] = $_GET['role'];
    }

    if (!empty($_GET['status'])) {
        if ($_GET['status'] === 'active') {
            $filters[] = "u.status = 'active'";
        } elseif ($_GET['status'] === 'suspended') {
            $filters[] = "u.status = 'suspended'";
        }
    }

    if (!empty($_GET['search'])) {
        $filters[] = "(u.name LIKE ? OR u.email LIKE ? OR u.phone LIKE ?)";
        $searchParam = '%' . $_GET['search'] . '%';
        $params[] = $searchParam;
        $params[] = $searchParam;
        $params[] = $searchParam;
    }

    $whereClause = !empty($filters) ? 'WHERE ' . implode(' AND ', $filters) : '';

    // Get total count
    $countSql = "SELECT COUNT(*) as total FROM users u $whereClause";
    $totalResult = $db->fetchOne($countSql, $params);
    $totalUsers = $totalResult['total'];

    // Get users with additional data
    $sql = "
        SELECT
            u.*,
            CASE
                WHEN u.role = 'retailer' THEN (SELECT COUNT(*) FROM loans WHERE borrower_id = u.id)
                WHEN u.role = 'supplier' THEN (SELECT COUNT(*) FROM orders WHERE supplier_id = u.id)
                WHEN u.role = 'field_agent' THEN (SELECT COUNT(*) FROM deliveries WHERE agent_id = u.id)
                ELSE 0
            END as activity_count,
            CASE
                WHEN u.role = 'retailer' THEN (SELECT COALESCE(SUM(loan_amount), 0) FROM loans WHERE borrower_id = u.id AND status IN ('active', 'completed'))
                ELSE 0
            END as total_loan_value,
            CASE
                WHEN u.role = 'retailer' THEN (SELECT cse.credit_score FROM credit_score_evaluations cse WHERE cse.user_id = u.id ORDER BY cse.created_at DESC LIMIT 1)
                ELSE NULL
            END as latest_credit_score,
            (SELECT COUNT(*) FROM fraud_detections WHERE user_id = u.id AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)) as fraud_flags
        FROM users u
        $whereClause
        ORDER BY u.created_at DESC
        LIMIT ? OFFSET ?
    ";

    $params[] = $limit;
    $params[] = $offset;

    $users = $db->fetchAll($sql, $params);

    return [
        'users' => $users,
        'total' => $totalUsers,
        'page' => $page,
        'total_pages' => ceil($totalUsers / $limit)
    ];
}

function getUserStatistics($db) {
    return $db->fetchOne("
        SELECT
            COUNT(*) as total_users,
            COUNT(CASE WHEN status = 'active' THEN 1 END) as active_users,
            COUNT(CASE WHEN status = 'suspended' THEN 1 END) as suspended_users,
            COUNT(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 END) as new_users_month,
            COUNT(CASE WHEN last_login >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 END) as active_week,
            COUNT(CASE WHEN role = 'retailer' THEN 1 END) as retailers,
            COUNT(CASE WHEN role = 'supplier' THEN 1 END) as suppliers,
            COUNT(CASE WHEN role = 'field_agent' THEN 1 END) as field_agents,
            COUNT(CASE WHEN role = 'lender' THEN 1 END) as lenders,
            COUNT(CASE WHEN role = 'admin' THEN 1 END) as admins
        FROM users
    ");
}

function getRecentRegistrations($db) {
    return $db->fetchAll("
        SELECT
            u.*,
            CASE
                WHEN u.role = 'retailer' THEN (SELECT COUNT(*) FROM loans WHERE borrower_id = u.id)
                ELSE 0
            END as loan_count
        FROM users u
        WHERE u.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        ORDER BY u.created_at DESC
        LIMIT 10
    ");
}

function getRoleDistribution($db) {
    return $db->fetchAll("
        SELECT
            role,
            COUNT(*) as count,
            ROUND(COUNT(*) * 100.0 / (SELECT COUNT(*) FROM users), 2) as percentage
        FROM users
        GROUP BY role
        ORDER BY count DESC
    ");
}

function handleCreateUser($db) {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $role = $_POST['role'];
    $password = $_POST['password'];

    // Validation
    if (empty($name) || empty($email) || empty($phone) || empty($role) || empty($password)) {
        $_SESSION['error'] = 'All fields are required';
        return;
    }

    // Check if email already exists
    if ($db->fetchOne("SELECT id FROM users WHERE email = ?", [$email])) {
        $_SESSION['error'] = 'Email already exists';
        return;
    }

    // Check if phone already exists
    if ($db->fetchOne("SELECT id FROM users WHERE phone = ?", [$phone])) {
        $_SESSION['error'] = 'Phone number already exists';
        return;
    }

    // Hash password
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

    // Create user
    $userId = $db->execute("
        INSERT INTO users (name, email, phone, role, password, status, created_at, updated_at)
        VALUES (?, ?, ?, ?, ?, 'active', NOW(), NOW())
    ", [$name, $email, $phone, $role, $hashedPassword]);

    if ($userId) {
        $_SESSION['success'] = 'User created successfully';

        // Log the action
        $db->execute("
            INSERT INTO admin_logs (admin_id, action, target_user_id, description, created_at)
            VALUES (?, 'create_user', ?, ?, NOW())
        ", [$_SESSION['user_id'], $userId, "Created new user: $name"]);
    } else {
        $_SESSION['error'] = 'Failed to create user';
    }
}

function handleUpdateUser($db) {
    $userId = (int)$_POST['user_id'];
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);

    // Validation
    if (empty($name) || empty($email) || empty($phone)) {
        $_SESSION['error'] = 'Name, email, and phone are required';
        return;
    }

    // Check if email exists for another user
    $existingEmail = $db->fetchOne("SELECT id FROM users WHERE email = ? AND id != ?", [$email, $userId]);
    if ($existingEmail) {
        $_SESSION['error'] = 'Email already exists';
        return;
    }

    // Check if phone exists for another user
    $existingPhone = $db->fetchOne("SELECT id FROM users WHERE phone = ? AND id != ?", [$phone, $userId]);
    if ($existingPhone) {
        $_SESSION['error'] = 'Phone number already exists';
        return;
    }

    // Update user
    $result = $db->execute("
        UPDATE users
        SET name = ?, email = ?, phone = ?, updated_at = NOW()
        WHERE id = ?
    ", [$name, $email, $phone, $userId]);

    if ($result) {
        $_SESSION['success'] = 'User updated successfully';

        // Log the action
        $db->execute("
            INSERT INTO admin_logs (admin_id, action, target_user_id, description, created_at)
            VALUES (?, 'update_user', ?, ?, NOW())
        ", [$_SESSION['user_id'], $userId, "Updated user: $name"]);
    } else {
        $_SESSION['error'] = 'Failed to update user';
    }
}

function handleSuspendUser($db) {
    $userId = (int)$_POST['user_id'];
    $reason = trim($_POST['reason']);

    $user = $db->fetchOne("SELECT name, role FROM users WHERE id = ?", [$userId]);
    if (!$user) {
        $_SESSION['error'] = 'User not found';
        return;
    }

    // Don't allow suspending other admins
    if ($user['role'] === 'admin') {
        $_SESSION['error'] = 'Cannot suspend admin users';
        return;
    }

    // Suspend user
    $result = $db->execute("
        UPDATE users
        SET status = 'suspended', suspension_reason = ?, updated_at = NOW()
        WHERE id = ?
    ", [$reason, $userId]);

    if ($result) {
        $_SESSION['success'] = 'User suspended successfully';

        // Log the action
        $db->execute("
            INSERT INTO admin_logs (admin_id, action, target_user_id, description, created_at)
            VALUES (?, 'suspend_user', ?, ?, NOW())
        ", [$_SESSION['user_id'], $userId, "Suspended user: {$user['name']} - Reason: $reason"]);
    } else {
        $_SESSION['error'] = 'Failed to suspend user';
    }
}

function handleActivateUser($db) {
    $userId = (int)$_POST['user_id'];

    $user = $db->fetchOne("SELECT name FROM users WHERE id = ?", [$userId]);
    if (!$user) {
        $_SESSION['error'] = 'User not found';
        return;
    }

    // Activate user
    $result = $db->execute("
        UPDATE users
        SET status = 'active', suspension_reason = NULL, updated_at = NOW()
        WHERE id = ?
    ", [$userId]);

    if ($result) {
        $_SESSION['success'] = 'User activated successfully';

        // Log the action
        $db->execute("
            INSERT INTO admin_logs (admin_id, action, target_user_id, description, created_at)
            VALUES (?, 'activate_user', ?, ?, NOW())
        ", [$_SESSION['user_id'], $userId, "Activated user: {$user['name']}"]);
    } else {
        $_SESSION['error'] = 'Failed to activate user';
    }
}

function handleResetPassword($db) {
    $userId = (int)$_POST['user_id'];
    $newPassword = $_POST['new_password'];

    if (strlen($newPassword) < 8) {
        $_SESSION['error'] = 'Password must be at least 8 characters long';
        return;
    }

    $user = $db->fetchOne("SELECT name FROM users WHERE id = ?", [$userId]);
    if (!$user) {
        $_SESSION['error'] = 'User not found';
        return;
    }

    // Hash new password
    $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);

    // Update password
    $result = $db->execute("
        UPDATE users
        SET password = ?, updated_at = NOW()
        WHERE id = ?
    ", [$hashedPassword, $userId]);

    if ($result) {
        $_SESSION['success'] = 'Password reset successfully';

        // Log the action
        $db->execute("
            INSERT INTO admin_logs (admin_id, action, target_user_id, description, created_at)
            VALUES (?, 'reset_password', ?, ?, NOW())
        ", [$_SESSION['user_id'], $userId, "Reset password for: {$user['name']}"]);
    } else {
        $_SESSION['error'] = 'Failed to reset password';
    }
}

function handleUpdateRole($db) {
    $userId = (int)$_POST['user_id'];
    $newRole = $_POST['new_role'];

    $user = $db->fetchOne("SELECT name, role FROM users WHERE id = ?", [$userId]);
    if (!$user) {
        $_SESSION['error'] = 'User not found';
        return;
    }

    // Don't allow changing role of other admins
    if ($user['role'] === 'admin' && $userId != $_SESSION['user_id']) {
        $_SESSION['error'] = 'Cannot change role of other admin users';
        return;
    }

    // Update role
    $result = $db->execute("
        UPDATE users
        SET role = ?, updated_at = NOW()
        WHERE id = ?
    ", [$newRole, $userId]);

    if ($result) {
        $_SESSION['success'] = 'User role updated successfully';

        // Log the action
        $db->execute("
            INSERT INTO admin_logs (admin_id, action, target_user_id, description, created_at)
            VALUES (?, 'update_role', ?, ?, NOW())
        ", [$_SESSION['user_id'], $userId, "Changed role for {$user['name']} from {$user['role']} to $newRole"]);
    } else {
        $_SESSION['error'] = 'Failed to update user role';
    }
}

$userData = getUsers($db);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management - JuaKali Lend Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/admin.css" rel="stylesheet">
    <style>
        .user-card {
            transition: all 0.3s ease;
            border: none;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        .user-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 16px rgba(0,0,0,0.15);
        }
        .status-badge {
            font-size: 0.8rem;
            padding: 4px 8px;
        }
        .role-badge {
            font-size: 0.8rem;
            padding: 4px 8px;
            font-weight: 500;
        }
        .credit-score-badge {
            background: linear-gradient(135deg, #28a745, #20c997);
            color: white;
        }
        .fraud-warning {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            color: #856404;
        }
        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
        }
        .filter-section {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .action-buttons .btn {
            padding: 0.25rem 0.5rem;
            font-size: 0.8rem;
        }
    </style>
</head>
<body>
    <!-- Header -->
    <div class="bg-primary text-white p-4 mb-4">
        <div class="container-fluid">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <h1><i class="fas fa-users"></i> User Management</h1>
                    <p class="mb-0">Manage users, roles, and permissions</p>
                </div>
                <div class="col-md-6 text-end">
                    <button class="btn btn-light" data-bs-toggle="modal" data-bs-target="#createUserModal">
                        <i class="fas fa-plus"></i> Add New User
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="container-fluid">
        <!-- Success/Error Messages -->
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-md-2">
                <div class="card user-card text-center">
                    <div class="card-body">
                        <div class="h3 text-primary"><?php echo $userStats['total_users']; ?></div>
                        <div class="small text-muted">Total Users</div>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card user-card text-center">
                    <div class="card-body">
                        <div class="h3 text-success"><?php echo $userStats['active_users']; ?></div>
                        <div class="small text-muted">Active</div>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card user-card text-center">
                    <div class="card-body">
                        <div class="h3 text-warning"><?php echo $userStats['suspended_users']; ?></div>
                        <div class="small text-muted">Suspended</div>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card user-card text-center">
                    <div class="card-body">
                        <div class="h3 text-info"><?php echo $userStats['new_users_month']; ?></div>
                        <div class="small text-muted">New This Month</div>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card user-card text-center">
                    <div class="card-body">
                        <div class="h3 text-secondary"><?php echo $userStats['active_week']; ?></div>
                        <div class="small text-muted">Active This Week</div>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card user-card text-center">
                    <div class="card-body">
                        <div class="h3 text-danger"><?php echo $userStats['retailers']; ?></div>
                        <div class="small text-muted">Retailers</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filters and Search -->
        <div class="filter-section">
            <form method="GET" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Role</label>
                    <select name="role" class="form-select">
                        <option value="">All Roles</option>
                        <option value="retailer" <?php echo ($_GET['role'] ?? '') === 'retailer' ? 'selected' : ''; ?>>Retailer</option>
                        <option value="supplier" <?php echo ($_GET['role'] ?? '') === 'supplier' ? 'selected' : ''; ?>>Supplier</option>
                        <option value="field_agent" <?php echo ($_GET['role'] ?? '') === 'field_agent' ? 'selected' : ''; ?>>Field Agent</option>
                        <option value="lender" <?php echo ($_GET['role'] ?? '') === 'lender' ? 'selected' : ''; ?>>Lender</option>
                        <option value="admin" <?php echo ($_GET['role'] ?? '') === 'admin' ? 'selected' : ''; ?>>Admin</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select">
                        <option value="">All Statuses</option>
                        <option value="active" <?php echo ($_GET['status'] ?? '') === 'active' ? 'selected' : ''; ?>>Active</option>
                        <option value="suspended" <?php echo ($_GET['status'] ?? '') === 'suspended' ? 'selected' : ''; ?>>Suspended</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Search</label>
                    <input type="text" name="search" class="form-control" placeholder="Name, email, or phone..."
                           value="<?php echo htmlspecialchars($_GET['search'] ?? ''); ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label">&nbsp;</label><br>
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-search"></i> Search
                    </button>
                </div>
            </form>
        </div>

        <!-- Users Table -->
        <div class="card user-card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5><i class="fas fa-list"></i> Users (<?php echo $userData['total']; ?> total)</h5>
                <div>
                    <button class="btn btn-sm btn-outline-success" onclick="exportUsers()">
                        <i class="fas fa-download"></i> Export
                    </button>
                </div>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>User</th>
                                <th>Role</th>
                                <th>Status</th>
                                <th>Credit Score</th>
                                <th>Activity</th>
                                <th>Fraud Flags</th>
                                <th>Joined</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($userData['users'] as $user): ?>
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="user-avatar me-3">
                                                <?php echo strtoupper(substr($user['name'], 0, 2)); ?>
                                            </div>
                                            <div>
                                                <div class="fw-bold"><?php echo htmlspecialchars($user['name']); ?></div>
                                                <small class="text-muted"><?php echo htmlspecialchars($user['email']); ?></small><br>
                                                <small class="text-muted"><?php echo htmlspecialchars($user['phone']); ?></small>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge role-badge
                                            <?php
                                            $roleColors = [
                                                'admin' => 'bg-danger',
                                                'retailer' => 'bg-primary',
                                                'supplier' => 'bg-success',
                                                'field_agent' => 'bg-warning',
                                                'lender' => 'bg-info'
                                            ];
                                            echo $roleColors[$user['role']] ?? 'bg-secondary';
                                            ?>">
                                            <?php echo ucfirst(str_replace('_', ' ', $user['role'])); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge status-badge
                                            <?php echo $user['status'] === 'active' ? 'bg-success' : 'bg-danger'; ?>">
                                            <?php echo ucfirst($user['status']); ?>
                                        </span>
                                        <?php if ($user['status'] === 'suspended' && !empty($user['suspension_reason'])): ?>
                                            <br><small class="text-muted"><?php echo htmlspecialchars($user['suspension_reason']); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($user['latest_credit_score']): ?>
                                            <span class="badge credit-score-badge">
                                                <?php echo $user['latest_credit_score']; ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-muted">N/A</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($user['role'] === 'retailer'): ?>
                                            <div>
                                                <strong><?php echo $user['activity_count']; ?></strong> loans<br>
                                                <small class="text-muted">KES <?php echo number_format($user['total_loan_value'], 0); ?></small>
                                            </div>
                                        <?php else: ?>
                                            <span class="text-muted"><?php echo $user['activity_count']; ?> activities</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($user['fraud_flags'] > 0): ?>
                                            <span class="badge bg-danger fraud-warning">
                                                <i class="fas fa-exclamation-triangle"></i> <?php echo $user['fraud_flags']; ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-muted">Clean</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div>
                                            <?php echo date('M j, Y', strtotime($user['created_at'])); ?><br>
                                            <small class="text-muted"><?php echo date('H:i', strtotime($user['created_at'])); ?></small>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="action-buttons">
                                            <button class="btn btn-sm btn-outline-primary" onclick="editUser(<?php echo $user['id']; ?>)" title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="btn btn-sm btn-outline-info" onclick="viewDetails(<?php echo $user['id']; ?>)" title="View Details">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <?php if ($user['status'] === 'active' && $user['role'] !== 'admin'): ?>
                                                <button class="btn btn-sm btn-outline-warning" onclick="suspendUser(<?php echo $user['id']; ?>)" title="Suspend">
                                                    <i class="fas fa-ban"></i>
                                                </button>
                                            <?php endif; ?>
                                            <?php if ($user['status'] === 'suspended'): ?>
                                                <button class="btn btn-sm btn-outline-success" onclick="activateUser(<?php echo $user['id']; ?>)" title="Activate">
                                                    <i class="fas fa-check"></i>
                                                </button>
                                            <?php endif; ?>
                                            <button class="btn btn-sm btn-outline-secondary" onclick="resetPassword(<?php echo $user['id']; ?>)" title="Reset Password">
                                                <i class="fas fa-key"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <?php if ($userData['total_pages'] > 1): ?>
                    <nav>
                        <ul class="pagination justify-content-center">
                            <?php for ($i = 1; $i <= $userData['total_pages']; $i++): ?>
                                <li class="page-item <?php echo $i === $userData['page'] ? 'active' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $i; ?>&<?php echo http_build_query($_GET); ?>">
                                        <?php echo $i; ?>
                                    </a>
                                </li>
                            <?php endfor; ?>
                        </ul>
                    </nav>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Create User Modal -->
    <div class="modal fade" id="createUserModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Create New User</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="create_user">

                        <div class="mb-3">
                            <label class="form-label">Name</label>
                            <input type="text" name="name" class="form-control" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" name="email" class="form-control" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Phone</label>
                            <input type="tel" name="phone" class="form-control" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Role</label>
                            <select name="role" class="form-select" required>
                                <option value="">Select Role</option>
                                <option value="retailer">Retailer</option>
                                <option value="supplier">Supplier</option>
                                <option value="field_agent">Field Agent</option>
                                <option value="lender">Lender</option>
                                <option value="admin">Admin</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Password</label>
                            <input type="password" name="password" class="form-control" required minlength="8">
                            <small class="text-muted">Minimum 8 characters</small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Create User</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function editUser(userId) {
            // Load user data and open edit modal
            fetch(`../api/admin/get-user-details.php?id=${userId}`)
                .then(response => response.json())
                .then(data => {
                    // Populate edit form and show modal
                    // Implementation details...
                });
        }

        function viewDetails(userId) {
            window.open(`user-details.php?id=${userId}`, '_blank');
        }

        function suspendUser(userId) {
            const reason = prompt('Please enter the reason for suspension:');
            if (reason) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="suspend_user">
                    <input type="hidden" name="user_id" value="${userId}">
                    <input type="hidden" name="reason" value="${reason}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        function activateUser(userId) {
            if (confirm('Are you sure you want to activate this user?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="activate_user">
                    <input type="hidden" name="user_id" value="${userId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        function resetPassword(userId) {
            const newPassword = prompt('Enter new password (minimum 8 characters):');
            if (newPassword && newPassword.length >= 8) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="reset_password">
                    <input type="hidden" name="user_id" value="${userId}">
                    <input type="hidden" name="new_password" value="${newPassword}">
                `;
                document.body.appendChild(form);
                form.submit();
            } else if (newPassword) {
                alert('Password must be at least 8 characters long.');
            }
        }

        function exportUsers() {
            const params = new URLSearchParams(window.location.search);
            params.set('export', 'true');
            window.open(`export-users.php?${params.toString()}`, '_blank');
        }
    </script>
</body>
</html>