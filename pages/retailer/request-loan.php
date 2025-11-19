<?php
session_start();
require_once '../config/config.php';
require_once '../includes/database.php';
require_once '../includes/functions.php';

// Check if user is logged in and is a retailer
if (!isLoggedIn() || $_SESSION['role'] !== 'retailer') {
    redirect('../pages/auth/login.php');
}

$user_id = $_SESSION['user_id'];
$error = '';
$success = '';

// Get user's credit score and available credit
try {
    $db = Database::getInstance();
    $credit_score = $db->fetchOne('SELECT * FROM credit_scores WHERE retailer_id = ?', [$user_id]);

    if (!$credit_score) {
        // Initialize credit score if it doesn't exist
        $db->execute('INSERT INTO credit_scores (retailer_id, score, credit_limit) VALUES (?, 500, 10000)', [$user_id]);
        $credit_score = $db->fetchOne('SELECT * FROM credit_scores WHERE retailer_id = ?', [$user_id]);
    }
} catch (Exception $e) {
    $credit_score = ['credit_limit' => 10000, 'score' => 500];
}

// Get available suppliers and products
try {
    $suppliers = $db->fetchAll('SELECT id, company_name FROM users WHERE role = "supplier" AND status = "active"');
    $products = [];

    if (!empty($_GET['supplier_id'])) {
        $products = $db->fetchAll('SELECT * FROM products WHERE supplier_id = ? AND status = "active"', [$_GET['supplier_id']]);
    }
} catch (Exception $e) {
    $suppliers = [];
    $products = [];
}

// Handle loan request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $supplier_id = sanitize($_POST['supplier_id'] ?? '');
    $product_items = $_POST['products'] ?? [];
    $quantities = $_POST['quantities'] ?? [];
    $purpose = sanitize($_POST['purpose'] ?? '');

    if (empty($supplier_id) || empty($product_items) || empty($purpose)) {
        $error = 'Please fill all required fields';
    } else {
        try {
            // Calculate total loan amount
            $total_amount = 0;
            $order_items = [];

            foreach ($product_items as $index => $product_id) {
                $quantity = $quantities[$index] ?? 0;
                if ($quantity > 0) {
                    $product = $db->fetchOne('SELECT * FROM products WHERE id = ?', [$product_id]);
                    if ($product) {
                        $item_total = $product['price'] * $quantity;
                        $total_amount += $item_total;
                        $order_items[] = [
                            'product_id' => $product_id,
                            'quantity' => $quantity,
                            'unit_price' => $product['price'],
                            'total_price' => $item_total
                        ];
                    }
                }
            }

            if ($total_amount <= 0) {
                throw new Exception('Invalid order amount');
            }

            if ($total_amount > $credit_score['credit_limit']) {
                throw new Exception('Loan amount exceeds your credit limit of KES ' . number_format($credit_score['credit_limit'], 2));
            }

            // Start transaction
            $db->beginTransaction();

            // Create order
            $order_number = 'ORD' . date('Y') . str_pad(mt_rand(1, 999999), 6, '0', STR_PAD_LEFT);
            $db->execute('INSERT INTO orders (order_number, retailer_id, supplier_id, subtotal, total_amount, status, payment_method, shipping_address) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
                [$order_number, $user_id, $supplier_id, $total_amount, $total_amount, 'pending', 'mobile_money', '']);

            $order_id = $db->lastInsertId();

            // Add order items
            foreach ($order_items as $item) {
                $db->execute('INSERT INTO order_items (order_id, product_id, quantity, unit_price, total_price) VALUES (?, ?, ?, ?, ?)',
                    [$order_id, $item['product_id'], $item['quantity'], $item['unit_price'], $item['total_price']]);
            }

            // Create loan request with 5% daily interest
            $loan_number = 'LN' . date('Y') . str_pad(mt_rand(1, 999999), 6, '0', STR_PAD_LEFT);
            $interest_rate = 5.0; // 5% daily
            $loan_term = 10; // 10 days default
            $total_interest = $total_amount * ($interest_rate / 100) * $loan_term;
            $total_repayment = $total_amount + $total_interest;
            $due_date = date('Y-m-d H:i:s', strtotime('+' . $loan_term . ' days'));

            $db->execute('INSERT INTO loans (loan_number, retailer_id, lender_id, loan_amount, interest_rate, total_amount, loan_term, purpose, status, due_date, order_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [$loan_number, $user_id, 1, $total_amount, $interest_rate, $total_repayment, $loan_term, $purpose, 'pending', $due_date, $order_id]);

            $loan_id = $db->lastInsertId();

            // Create repayment schedule
            $daily_payment = $total_repayment / $loan_term;
            for ($day = 1; $day <= $loan_term; $day++) {
                $due_date = date('Y-m-d', strtotime('+' . $day . ' days'));
                $db->execute('INSERT INTO repayment_schedule (loan_id, amount_due, due_date, status) VALUES (?, ?, ?, ?)',
                    [$loan_id, $daily_payment, $due_date, 'pending']);
            }

            // Update credit score (temporarily reduce available credit)
            $db->execute('UPDATE credit_scores SET total_borrowed = total_borrowed + ? WHERE retailer_id = ?', [$total_amount, $user_id]);

            // Create notification for lenders
            $db->execute('INSERT INTO notifications (user_id, title, message, type, action_url) SELECT id, ?, ?, ?, ? FROM users WHERE role = "lender" AND status = "active"',
                ['New Loan Request', 'A retailer has requested a loan of KES ' . number_format($total_amount, 2), 'loan_request', 'pages/lender/view-loan.php?id=' . $loan_id]);

            $db->commit();

            $success = 'Loan request submitted successfully! Lenders will review your request and you will receive a notification once approved.';

            // Reset form
            $_POST = [];

        } catch (Exception $e) {
            $db->rollback();
            $error = $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Request Loan - JuaKali Lend</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #10b981 0%, #34d399 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .loan-container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            margin: 20px auto;
            max-width: 1000px;
            overflow: hidden;
        }
        .loan-header {
            background: linear-gradient(135deg, #10b981 0%, #34d399 100%);
            color: white;
            padding: 2rem;
            text-align: center;
        }
        .loan-body {
            padding: 2.5rem;
        }
        .credit-info {
            background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%);
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            border: 2px solid #bbf7d0;
        }
        .loan-calculator {
            background: #f8fafc;
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 2rem;
        }
        .product-item {
            background: white;
            border: 2px solid #e5e7eb;
            border-radius: 10px;
            padding: 1rem;
            margin-bottom: 1rem;
            transition: all 0.3s ease;
        }
        .product-item:hover {
            border-color: #10b981;
            box-shadow: 0 5px 15px rgba(16, 185, 129, 0.1);
        }
        .interest-info {
            background: linear-gradient(135deg, #fef3c7 0%, #fed7aa 100%);
            border-radius: 10px;
            padding: 1rem;
            border: 2px solid #fbbf24;
        }
        .btn-request {
            background: linear-gradient(135deg, #10b981 0%, #34d399 100%);
            color: white;
            border: none;
            border-radius: 10px;
            padding: 0.875rem 2rem;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        .btn-request:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(16, 185, 129, 0.3);
            color: white;
        }
        .step-indicator {
            display: flex;
            justify-content: space-between;
            margin-bottom: 2rem;
        }
        .step {
            flex: 1;
            text-align: center;
            position: relative;
        }
        .step::after {
            content: '';
            position: absolute;
            top: 15px;
            left: 50%;
            width: 100%;
            height: 2px;
            background: #e5e7eb;
            z-index: -1;
        }
        .step:last-child::after {
            display: none;
        }
        .step.active::after {
            background: #10b981;
        }
        .step-circle {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            background: #e5e7eb;
            color: #6b7280;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 0.875rem;
            font-weight: 600;
        }
        .step.active .step-circle {
            background: #10b981;
            color: white;
        }
        .step.completed .step-circle {
            background: #10b981;
            color: white;
        }
    </style>
</head>
<body>
    <div class="container py-5">
        <div class="loan-container">
            <div class="loan-header">
                <h1><i class="fas fa-hand-holding-usd"></i> Request Loan</h1>
                <p>Get the goods you need with our flexible lending solutions</p>
            </div>

            <div class="loan-body">
                <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if ($success): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="fas fa-check-circle"></i> <?php echo $success; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- Credit Information -->
                <div class="credit-info">
                    <div class="row">
                        <div class="col-md-3">
                            <h6 class="text-muted">Credit Score</h6>
                            <h3><?php echo $credit_score['score']; ?></h3>
                            <small class="text-success"><i class="fas fa-arrow-up"></i> Good</small>
                        </div>
                        <div class="col-md-3">
                            <h6 class="text-muted">Available Credit</h6>
                            <h3>KES <?php echo number_format($credit_score['credit_limit'], 0); ?></h3>
                        </div>
                        <div class="col-md-3">
                            <h6 class="text-muted">Total Borrowed</h6>
                            <h3>KES <?php echo number_format($credit_score['total_borrowed'] ?? 0, 0); ?></h3>
                        </div>
                        <div class="col-md-3">
                            <h6 class="text-muted">Total Repaid</h6>
                            <h3>KES <?php echo number_format($credit_score['total_repaid'] ?? 0, 0); ?></h3>
                        </div>
                    </div>
                </div>

                <!-- Step Indicator -->
                <div class="step-indicator">
                    <div class="step active">
                        <div class="step-circle">1</div>
                        <small>Select Supplier</small>
                    </div>
                    <div class="step">
                        <div class="step-circle">2</div>
                        <small>Choose Products</small>
                    </div>
                    <div class="step">
                        <div class="step-circle">3</div>
                        <small>Review Terms</small>
                    </div>
                    <div class="step">
                        <div class="step-circle">4</div>
                        <small>Submit Request</small>
                    </div>
                </div>

                <form method="POST" id="loanRequestForm">
                    <!-- Supplier Selection -->
                    <div class="mb-4">
                        <label for="supplier_id" class="form-label">Select Supplier *</label>
                        <select class="form-select" id="supplier_id" name="supplier_id" required onchange="loadProducts(this.value)">
                            <option value="">Choose a supplier</option>
                            <?php foreach ($suppliers as $supplier): ?>
                                <option value="<?php echo $supplier['id']; ?>" <?php echo (isset($_POST['supplier_id']) && $_POST['supplier_id'] == $supplier['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($supplier['company_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Products Selection -->
                    <div id="productsSection" style="display: <?php echo !empty($products) ? 'block' : 'none'; ?>;">
                        <h5 class="mb-3">Select Products</h5>
                        <div id="productsList">
                            <?php foreach ($products as $product): ?>
                                <div class="product-item">
                                    <div class="row align-items-center">
                                        <div class="col-md-6">
                                            <h6 class="mb-1"><?php echo htmlspecialchars($product['name']); ?></h6>
                                            <small class="text-muted"><?php echo htmlspecialchars($product['description']); ?></small>
                                        </div>
                                        <div class="col-md-2">
                                            <small class="text-muted">Price</small>
                                            <h6 class="mb-0">KES <?php echo number_format($product['price'], 2); ?></h6>
                                        </div>
                                        <div class="col-md-2">
                                            <label class="form-label small">Quantity</label>
                                            <input type="number" class="form-control form-control-sm" name="quantities[]" min="1" max="<?php echo $product['quantity_available']; ?>" value="1" onchange="calculateTotal()">
                                            <input type="hidden" name="products[]" value="<?php echo $product['id']; ?>">
                                        </div>
                                        <div class="col-md-2">
                                            <small class="text-muted">Subtotal</small>
                                            <h6 class="mb-0 product-total" data-price="<?php echo $product['price']; ?>">KES <?php echo number_format($product['price'], 2); ?></h6>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Loan Purpose -->
                    <div class="mb-4">
                        <label for="purpose" class="form-label">Purpose of Loan *</label>
                        <textarea class="form-control" id="purpose" name="purpose" rows="3" placeholder="Describe how you plan to use these goods..." required></textarea>
                    </div>

                    <!-- Loan Calculator -->
                    <div class="loan-calculator">
                        <h5><i class="fas fa-calculator"></i> Loan Breakdown</h5>
                        <div class="row">
                            <div class="col-md-3">
                                <small class="text-muted">Principal Amount</small>
                                <h5 id="principalAmount">KES 0</h5>
                            </div>
                            <div class="col-md-3">
                                <small class="text-muted">Interest Rate</small>
                                <h5>5% Daily</h5>
                            </div>
                            <div class="col-md-3">
                                <small class="text-muted">Loan Term</small>
                                <h5 id="loanTerm">10 Days</h5>
                            </div>
                            <div class="col-md-3">
                                <small class="text-muted">Total Repayment</small>
                                <h5 id="totalRepayment" class="text-success">KES 0</h5>
                            </div>
                        </div>
                        <div class="interest-info mt-3">
                            <i class="fas fa-info-circle"></i> <strong>Daily Payment:</strong> <span id="dailyPayment">KES 0</span>
                            <small class="d-block mt-1 text-muted">Payment due every day for 10 days via M-Pesa</small>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-request w-100">
                        <i class="fas fa-paper-plane"></i> Submit Loan Request
                    </button>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function loadProducts(supplierId) {
            if (supplierId) {
                window.location.href = '?supplier_id=' + supplierId;
            }
        }

        function calculateTotal() {
            let total = 0;
            const products = document.querySelectorAll('.product-item');

            products.forEach(item => {
                const price = parseFloat(item.querySelector('[data-price]').dataset.price);
                const quantity = parseInt(item.querySelector('input[type="number"]').value) || 0;
                const subtotal = price * quantity;

                item.querySelector('.product-total').textContent = 'KES ' + subtotal.toLocaleString('en-KEN', {minimumFractionDigits: 2});
                total += subtotal;
            });

            // Update loan calculator
            const interestRate = 5; // 5% daily
            const loanTerm = 10; // 10 days
            const totalInterest = total * (interestRate / 100) * loanTerm;
            const totalRepayment = total + totalInterest;
            const dailyPayment = totalRepayment / loanTerm;

            document.getElementById('principalAmount').textContent = 'KES ' + total.toLocaleString('en-KEN', {minimumFractionDigits: 2});
            document.getElementById('totalRepayment').textContent = 'KES ' + totalRepayment.toLocaleString('en-KEN', {minimumFractionDigits: 2});
            document.getElementById('dailyPayment').textContent = 'KES ' + dailyPayment.toLocaleString('en-KEN', {minimumFractionDigits: 2});
        }

        // Initialize calculation
        document.addEventListener('DOMContentLoaded', function() {
            calculateTotal();
        });
    </script>
</body>
</html>