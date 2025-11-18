<?php
/**
 * System Requirements Checker
 */

$checks = [
    'PHP Version' => [
        'required' => '8.1+',
        'current' => phpversion(),
        'passed' => version_compare(phpversion(), '8.1', '>=')
    ],
    'MySQL Support' => [
        'required' => 'MySQLi',
        'current' => extension_loaded('mysqli') ? 'Enabled' : 'Disabled',
        'passed' => extension_loaded('mysqli')
    ],
    'JSON Support' => [
        'required' => 'Enabled',
        'current' => extension_loaded('json') ? 'Enabled' : 'Disabled',
        'passed' => extension_loaded('json')
    ],
    'File Upload' => [
        'required' => 'Enabled',
        'current' => ini_get('file_uploads') ? 'Enabled' : 'Disabled',
        'passed' => ini_get('file_uploads')
    ],
    'OpenSSL' => [
        'required' => 'Enabled',
        'current' => extension_loaded('openssl') ? 'Enabled' : 'Disabled',
        'passed' => extension_loaded('openssl')
    ]
];

$all_passed = true;
foreach ($checks as $check) {
    if (!$check['passed']) $all_passed = false;
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Requirements</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container mt-5">
        <div class="row">
            <div class="col-md-8 offset-md-2">
                <div class="card">
                    <div class="card-header bg-<?php echo $all_passed ? 'success' : 'warning'; ?> text-white">
                        <h4 class="mb-0"><i class="fas fa-check-circle"></i> System Requirements Check</h4>
                    </div>
                    <div class="card-body">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Requirement</th>
                                    <th>Required</th>
                                    <th>Current</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($checks as $name => $check): ?>
                                <tr>
                                    <td><?php echo $name; ?></td>
                                    <td><?php echo $check['required']; ?></td>
                                    <td><?php echo $check['current']; ?></td>
                                    <td>
                                        <span class="badge bg-<?php echo $check['passed'] ? 'success' : 'danger'; ?>">
                                            <?php echo $check['passed'] ? 'OK' : 'FAIL'; ?>
                                        </span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>

                        <div class="mt-4">
                            <?php if ($all_passed): ?>
                                <div class="alert alert-success">
                                    <i class="fas fa-check-circle"></i> All requirements met! You can proceed with installation.
                                </div>
                                <a href="/install/" class="btn btn-success">Proceed to Installation</a>
                            <?php else: ?>
                                <div class="alert alert-warning">
                                    <i class="fas fa-exclamation-triangle"></i> Some requirements are not met. Please fix them before installing.
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
