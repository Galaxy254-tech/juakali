<?php
session_start();
require_once '../config/config.php';
require_once '../includes/database.php';
require_once '../includes/functions.php';

// Check if user is logged in
if (!isLoggedIn()) {
    redirect('../pages/auth/login.php');
}

$user_id = $_SESSION['user_id'];
$error = '';
$success = '';

// Get existing KYC documents
try {
    $db = Database::getInstance();
    $existing_docs = $db->fetchAll('SELECT * FROM kyc_documents WHERE user_id = ?', [$user_id]);
} catch (Exception $e) {
    $existing_docs = [];
}

// Handle file upload
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $document_type = sanitize($_POST['document_type'] ?? '');
    $document_number = sanitize($_POST['document_number'] ?? '');

    if (empty($document_type) || empty($document_number)) {
        $error = 'Document type and number are required';
    } elseif (!isset($_FILES['document_file']) || $_FILES['document_file']['error'] !== UPLOAD_ERR_OK) {
        $error = 'Please select a valid document file';
    } else {
        try {
            // Create uploads directory if it doesn't exist
            $upload_dir = '../uploads/kyc/' . $user_id . '/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }

            // Generate unique filename
            $file_extension = strtolower(pathinfo($_FILES['document_file']['name'], PATHINFO_EXTENSION));
            $allowed_extensions = ['jpg', 'jpeg', 'png', 'pdf'];

            if (!in_array($file_extension, $allowed_extensions)) {
                throw new Exception('Invalid file type. Only JPG, PNG, and PDF are allowed.');
            }

            $file_size = $_FILES['document_file']['size'];
            if ($file_size > 5242880) { // 5MB
                throw new Exception('File size too large. Maximum size is 5MB.');
            }

            $filename = $document_type . '_' . uniqid() . '.' . $file_extension;
            $filepath = $upload_dir . $filename;

            // Move uploaded file
            if (!move_uploaded_file($_FILES['document_file']['tmp_name'], $filepath)) {
                throw new Exception('Failed to upload file.');
            }

            // Save to database
            $db = Database::getInstance();
            $db->execute('INSERT INTO kyc_documents (user_id, document_type, document_number, front_image, status) VALUES (?, ?, ?, ?, ?)',
                [$user_id, $document_type, $document_number, $filepath, 'pending']);

            $success = 'Document uploaded successfully. Verification will be completed within 24 hours.';

            // Refresh existing documents
            $existing_docs = $db->fetchAll('SELECT * FROM kyc_documents WHERE user_id = ?', [$user_id]);

        } catch (Exception $e) {
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
    <title>KYC Verification - JuaKali Lend</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #10b981 0%, #34d399 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .kyc-container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            margin: 20px auto;
            max-width: 800px;
            overflow: hidden;
        }
        .kyc-header {
            background: linear-gradient(135deg, #10b981 0%, #34d399 100%);
            color: white;
            padding: 2rem;
            text-align: center;
        }
        .kyc-body {
            padding: 2.5rem;
        }
        .document-card {
            border: 2px solid #e5e7eb;
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            transition: all 0.3s ease;
        }
        .document-card:hover {
            border-color: #10b981;
            box-shadow: 0 10px 20px rgba(16, 185, 129, 0.1);
        }
        .status-badge {
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.875rem;
            font-weight: 600;
        }
        .status-pending {
            background: #fef3c7;
            color: #92400e;
        }
        .status-approved {
            background: #d1fae5;
            color: #065f46;
        }
        .status-rejected {
            background: #fee2e2;
            color: #991b1b;
        }
        .upload-area {
            border: 3px dashed #d1d5db;
            border-radius: 15px;
            padding: 2rem;
            text-align: center;
            transition: all 0.3s ease;
            cursor: pointer;
        }
        .upload-area:hover {
            border-color: #10b981;
            background: #f0fdf4;
        }
        .upload-area.dragover {
            border-color: #10b981;
            background: #dcfce7;
        }
        .btn-upload {
            background: linear-gradient(135deg, #10b981 0%, #34d399 100%);
            color: white;
            border: none;
            border-radius: 10px;
            padding: 0.75rem 2rem;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        .btn-upload:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(16, 185, 129, 0.3);
            color: white;
        }
        .progress-steps {
            display: flex;
            justify-content: space-between;
            margin-bottom: 2rem;
            position: relative;
        }
        .progress-step {
            flex: 1;
            text-align: center;
            position: relative;
        }
        .progress-step::before {
            content: '';
            position: absolute;
            top: 20px;
            left: 50%;
            width: 100%;
            height: 2px;
            background: #e5e7eb;
            z-index: -1;
        }
        .progress-step:first-child::before {
            left: 50%;
            width: 50%;
        }
        .progress-step:last-child::before {
            width: 50%;
        }
        .progress-step.active::before {
            background: #10b981;
        }
        .step-circle {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #e5e7eb;
            color: #6b7280;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }
        .progress-step.active .step-circle {
            background: #10b981;
            color: white;
        }
        .progress-step.completed .step-circle {
            background: #10b981;
            color: white;
        }
    </style>
</head>
<body>
    <div class="container py-5">
        <div class="kyc-container">
            <div class="kyc-header">
                <h1><i class="fas fa-shield-alt"></i> KYC Verification</h1>
                <p>Complete your verification to access all features</p>
            </div>

            <div class="kyc-body">
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

                <!-- Progress Steps -->
                <div class="progress-steps">
                    <div class="progress-step completed">
                        <div class="step-circle"><i class="fas fa-check"></i></div>
                        <small>Basic Info</small>
                    </div>
                    <div class="progress-step active">
                        <div class="step-circle">2</div>
                        <small>Documents</small>
                    </div>
                    <div class="progress-step">
                        <div class="step-circle">3</div>
                        <small>Verification</small>
                    </div>
                    <div class="progress-step">
                        <div class="step-circle">4</div>
                        <small>Complete</small>
                    </div>
                </div>

                <!-- Existing Documents -->
                <?php if (!empty($existing_docs)): ?>
                    <h5 class="mb-3">Uploaded Documents</h5>
                    <?php foreach ($existing_docs as $doc): ?>
                        <div class="document-card">
                            <div class="row align-items-center">
                                <div class="col-md-8">
                                    <h6 class="mb-1">
                                        <?php
                                        $doc_types = [
                                            'national_id' => 'National ID',
                                            'passport' => 'Passport',
                                            'business_license' => 'Business License',
                                            'tax_certificate' => 'Tax Certificate'
                                        ];
                                        echo $doc_types[$doc['document_type']] ?? $doc['document_type'];
                                        ?>
                                    </h6>
                                    <small class="text-muted">Number: <?php echo htmlspecialchars($doc['document_number']); ?></small>
                                    <br>
                                    <small class="text-muted">Uploaded: <?php echo date('M j, Y', strtotime($doc['created_at'])); ?></small>
                                </div>
                                <div class="col-md-4 text-end">
                                    <span class="status-badge status-<?php echo $doc['status']; ?>">
                                        <?php echo ucfirst($doc['status']); ?>
                                    </span>
                                    <?php if ($doc['status'] === 'approved'): ?>
                                        <br><small class="text-success"><i class="fas fa-check"></i> Verified</small>
                                    <?php elseif ($doc['status'] === 'rejected'): ?>
                                        <br><small class="text-danger"><?php echo htmlspecialchars($doc['rejection_reason']); ?></small>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>

                <!-- Upload Form -->
                <div class="row mt-4">
                    <div class="col-md-8">
                        <h5 class="mb-3">Upload New Document</h5>
                        <form method="POST" enctype="multipart/form-data" id="kycForm">
                            <div class="mb-3">
                                <label for="document_type" class="form-label">Document Type *</label>
                                <select class="form-select" id="document_type" name="document_type" required>
                                    <option value="">Select Document Type</option>
                                    <option value="national_id">National ID</option>
                                    <option value="passport">Passport</option>
                                    <option value="business_license">Business License</option>
                                    <option value="tax_certificate">Tax Certificate</option>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label for="document_number" class="form-label">Document Number *</label>
                                <input type="text" class="form-control" id="document_number" name="document_number"
                                       placeholder="Enter document number" required>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Upload Document *</label>
                                <div class="upload-area" id="uploadArea">
                                    <input type="file" id="document_file" name="document_file" accept=".jpg,.jpeg,.png,.pdf" style="display: none;" required>
                                    <div id="uploadContent">
                                        <i class="fas fa-cloud-upload-alt fa-3x text-muted mb-3"></i>
                                        <p class="mb-2">Click to upload or drag and drop</p>
                                        <p class="text-muted small">JPG, PNG or PDF (max. 5MB)</p>
                                    </div>
                                    <div id="filePreview" style="display: none;">
                                        <p class="mb-2"><strong>Selected file:</strong></p>
                                        <p id="fileName"></p>
                                        <button type="button" class="btn btn-sm btn-outline-danger" onclick="clearFile()">
                                            <i class="fas fa-times"></i> Remove
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <button type="submit" class="btn btn-upload">
                                <i class="fas fa-upload"></i> Upload Document
                            </button>
                        </form>
                    </div>

                    <div class="col-md-4">
                        <div class="alert alert-info">
                            <h6><i class="fas fa-info-circle"></i> Why KYC?</h6>
                            <ul class="small mb-0">
                                <li>Verify your identity</li>
                                <li>Build trust with lenders</li>
                                <li>Access higher loan limits</li>
                                <li>Comply with regulations</li>
                            </ul>
                        </div>

                        <div class="alert alert-warning">
                            <h6><i class="fas fa-shield-alt"></i> Your Data is Safe</h6>
                            <ul class="small mb-0">
                                <li>Bank-level encryption</li>
                                <li>Secure storage</li>
                                <li>Limited access</li>
                                <li>GDPR compliant</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // File upload handling
        const uploadArea = document.getElementById('uploadArea');
        const fileInput = document.getElementById('document_file');
        const uploadContent = document.getElementById('uploadContent');
        const filePreview = document.getElementById('filePreview');
        const fileName = document.getElementById('fileName');

        uploadArea.addEventListener('click', () => fileInput.click());

        uploadArea.addEventListener('dragover', (e) => {
            e.preventDefault();
            uploadArea.classList.add('dragover');
        });

        uploadArea.addEventListener('dragleave', () => {
            uploadArea.classList.remove('dragover');
        });

        uploadArea.addEventListener('drop', (e) => {
            e.preventDefault();
            uploadArea.classList.remove('dragover');

            const files = e.dataTransfer.files;
            if (files.length > 0) {
                fileInput.files = files;
                handleFileSelect(files[0]);
            }
        });

        fileInput.addEventListener('change', (e) => {
            if (e.target.files.length > 0) {
                handleFileSelect(e.target.files[0]);
            }
        });

        function handleFileSelect(file) {
            const validTypes = ['image/jpeg', 'image/jpg', 'image/png', 'application/pdf'];
            const maxSize = 5 * 1024 * 1024; // 5MB

            if (!validTypes.includes(file.type)) {
                alert('Invalid file type. Only JPG, PNG, and PDF are allowed.');
                return;
            }

            if (file.size > maxSize) {
                alert('File size too large. Maximum size is 5MB.');
                return;
            }

            fileName.textContent = file.name;
            uploadContent.style.display = 'none';
            filePreview.style.display = 'block';
        }

        function clearFile() {
            fileInput.value = '';
            uploadContent.style.display = 'block';
            filePreview.style.display = 'none';
        }
    </script>
</body>
</html>