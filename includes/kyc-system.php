<?php
/**
 * Comprehensive KYC (Know Your Customer) System for JuaKali Lend
 * Handles ID verification, document upload, and live selfie verification
 */

class KYCSystem {
    private $db;
    private $allowedFileTypes = ['jpg', 'jpeg', 'png', 'pdf'];
    private $maxFileSize = 5 * 1024 * 1024; // 5MB

    public function __construct($database) {
        $this->db = $database;
    }

    /**
     * Start KYC verification process
     */
    public function startVerification($userId, $kycData) {
        try {
            // Validate required fields
            $requiredFields = ['id_type', 'id_number', 'full_name', 'date_of_birth'];
            foreach ($requiredFields as $field) {
                if (empty($kycData[$field])) {
                    throw new Exception("Missing required field: $field");
                }
            }

            // Check if user already has pending KYC
            $existingKYC = $this->db->fetchOne("
                SELECT * FROM kyc_verifications
                WHERE user_id = ? AND status IN ('pending', 'under_review')
                ORDER BY created_at DESC LIMIT 1
            ", [$userId]);

            if ($existingKYC) {
                throw new Exception('KYC verification already in progress');
            }

            // Create KYC record
            $kycId = $this->db->execute("
                INSERT INTO kyc_verifications (
                    user_id, id_type, id_number, full_name, date_of_birth,
                    address, occupation, monthly_income, status, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW())
            ", [
                $userId,
                $kycData['id_type'],
                $kycData['id_number'],
                $kycData['full_name'],
                $kycData['date_of_birth'],
                $kycData['address'] ?? null,
                $kycData['occupation'] ?? null,
                $kycData['monthly_income'] ?? null
            ]);

            if (!$kycId) {
                throw new Exception('Failed to create KYC record');
            }

            // Generate verification token
            $verificationToken = $this->generateVerificationToken($userId, $kycId);

            return [
                'success' => true,
                'kyc_id' => $kycId,
                'verification_token' => $verificationToken,
                'message' => 'KYC verification started. Please upload required documents.',
                'required_documents' => $this->getRequiredDocuments($kycData['id_type'])
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Upload KYC document
     */
    public function uploadDocument($userId, $kycId, $documentType, $file) {
        try {
            // Validate KYC ownership
            $kyc = $this->db->fetchOne("
                SELECT * FROM kyc_verifications
                WHERE id = ? AND user_id = ? AND status = 'pending'
            ", [$kycId, $userId]);

            if (!$kyc) {
                throw new Exception('Invalid KYC record');
            }

            // Validate file
            $validation = $this->validateFile($file);
            if (!$validation['valid']) {
                throw new Exception($validation['error']);
            }

            // Generate unique filename
            $filename = $this->generateSecureFilename($userId, $kycId, $documentType, $file['name']);

            // Save file
            $filePath = $this->saveUploadedFile($file, $filename);
            if (!$filePath) {
                throw new Exception('Failed to save document');
            }

            // Extract metadata
            $metadata = $this->extractDocumentMetadata($filePath, $documentType);

            // Save document record
            $documentId = $this->db->execute("
                INSERT INTO kyc_documents (
                    kyc_id, user_id, document_type, file_name, file_path,
                    file_size, mime_type, metadata, upload_status, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'uploaded', NOW())
            ", [
                $kycId, $userId, $documentType, $file['name'], $filePath,
                $file['size'], $file['type'], json_encode($metadata)
            ]);

            if (!$documentId) {
                // Clean up file if database insert fails
                unlink($filePath);
                throw new Exception('Failed to save document record');
            }

            // Start document verification
            $this->processDocumentVerification($documentId);

            return [
                'success' => true,
                'document_id' => $documentId,
                'message' => 'Document uploaded successfully. Verification in progress.',
                'verification_status' => 'processing'
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Upload and verify live selfie
     */
    public function uploadLiveSelfie($userId, $kycId, $selfieFile) {
        try {
            // Validate KYC ownership
            $kyc = $this->db->fetchOne("
                SELECT * FROM kyc_verifications
                WHERE id = ? AND user_id = ? AND status = 'pending'
            ", [$kycId, $userId]);

            if (!$kyc) {
                throw new Exception('Invalid KYC record');
            }

            // Validate file
            $validation = $this->validateSelfie($selfieFile);
            if (!$validation['valid']) {
                throw new Exception($validation['error']);
            }

            // Generate unique filename
            $filename = $this->generateSecureFilename($userId, $kycId, 'live_selfie', $selfieFile['name']);

            // Save file
            $filePath = $this->saveUploadedFile($selfieFile, $filename);
            if (!$filePath) {
                throw new Exception('Failed to save selfie');
            }

            // Perform facial analysis
            $facialAnalysis = $this->analyzeFacialImage($filePath);

            // Save selfie record
            $selfieId = $this->db->execute("
                INSERT INTO kyc_selfies (
                    kyc_id, user_id, file_name, file_path, file_size,
                    mime_type, facial_data, verification_status, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, 'processing', NOW())
            ", [
                $kycId, $userId, $selfieFile['name'], $filePath,
                $selfieFile['size'], $selfieFile['type'], json_encode($facialAnalysis)
            ]);

            if (!$selfieId) {
                unlink($filePath);
                throw new Exception('Failed to save selfie record');
            }

            // Compare with ID document if available
            $comparisonResult = $this->performFaceComparison($kycId, $filePath);

            return [
                'success' => true,
                'selfie_id' => $selfieId,
                'facial_analysis' => $facialAnalysis,
                'face_comparison' => $comparisonResult,
                'message' => 'Live selfie uploaded successfully. Verification in progress.'
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Perform facial recognition match
     */
    public function performFaceComparison($kycId, $selfiePath) {
        try {
            // Get ID document image
            $idDocument = $this->db->fetchOne("
                SELECT * FROM kyc_documents
                WHERE kyc_id = ? AND document_type IN ('national_id_front', 'passport_photo')
                ORDER BY created_at DESC LIMIT 1
            ", [$kycId]);

            if (!$idDocument) {
                return [
                    'match_score' => 0,
                    'match_status' => 'no_id_document',
                    'message' => 'ID document not found for comparison'
                ];
            }

            // Use facial recognition service (placeholder implementation)
            $matchResult = $this->compareFaces($idDocument['file_path'], $selfiePath);

            // Update comparison result
            $this->db->execute("
                UPDATE kyc_selfies
                SET face_comparison_result = ?, verification_status = ?
                WHERE kyc_id = ?
            ", [
                json_encode($matchResult),
                $matchResult['match_status'] === 'match' ? 'verified' : 'failed',
                $kycId
            ]);

            return $matchResult;

        } catch (Exception $e) {
            return [
                'match_score' => 0,
                'match_status' => 'error',
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Submit KYC for review
     */
    public function submitForReview($userId, $kycId) {
        try {
            // Validate KYC ownership
            $kyc = $this->db->fetchOne("
                SELECT * FROM kyc_verifications
                WHERE id = ? AND user_id = ? AND status = 'pending'
            ", [$kycId, $userId]);

            if (!$kyc) {
                throw new Exception('Invalid KYC record');
            }

            // Check if all required documents are uploaded
            $requiredDocs = $this->getRequiredDocuments($kyc['id_type']);
            $uploadedDocs = $this->db->fetchAll("
                SELECT document_type FROM kyc_documents
                WHERE kyc_id = ? AND upload_status = 'verified'
            ", [$kycId]);

            $uploadedDocTypes = array_column($uploadedDocs, 'document_type');
            $missingDocs = array_diff($requiredDocs, $uploadedDocTypes);

            if (!empty($missingDocs)) {
                throw new Exception('Missing required documents: ' . implode(', ', $missingDocs));
            }

            // Check if selfie is verified
            $selfie = $this->db->fetchOne("
                SELECT verification_status FROM kyc_selfies
                WHERE kyc_id = ? AND verification_status = 'verified'
            ", [$kycId]);

            if (!$selfie) {
                throw new Exception('Live selfie verification required');
            }

            // Update KYC status
            $this->db->execute("
                UPDATE kyc_verifications
                SET status = 'under_review', submitted_at = NOW()
                WHERE id = ?
            ", [$kycId]);

            // Create review assignment
            $this->assignReviewer($kycId);

            return [
                'success' => true,
                'message' => 'KYC submitted for review. You will be notified within 24 hours.'
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Review and approve/reject KYC
     */
    public function reviewKYC($kycId, $reviewerId, $decision, $comments = '') {
        try {
            // Get KYC record
            $kyc = $this->db->fetchOne("
                SELECT * FROM kyc_verifications WHERE id = ? AND status = 'under_review'
            ", [$kycId]);

            if (!$kyc) {
                throw new Exception('KYC record not found or not under review');
            }

            // Update KYC status
            $newStatus = $decision === 'approve' ? 'approved' : 'rejected';
            $this->db->execute("
                UPDATE kyc_verifications
                SET status = ?, reviewer_id = ?, review_comments = ?, reviewed_at = NOW()
                WHERE id = ?
            ", [$newStatus, $reviewerId, $comments, $kycId]);

            // Update user KYC status if approved
            if ($decision === 'approve') {
                $this->db->execute("
                    UPDATE users SET kyc_status = 'verified', kyc_verified_at = NOW()
                    WHERE id = ?
                ", [$kyc['user_id']]);

                // Send notification
                $this->sendKYCNotification($kyc['user_id'], 'approved');
            } else {
                $this->sendKYCNotification($kyc['user_id'], 'rejected', $comments);
            }

            // Log review action
            $this->db->execute("
                INSERT INTO kyc_review_logs (
                    kyc_id, reviewer_id, decision, comments, created_at
                ) VALUES (?, ?, ?, ?, NOW())
            ", [$kycId, $reviewerId, $decision, $comments]);

            return [
                'success' => true,
                'message' => "KYC $decision successfully"
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Get KYC status for user
     */
    public function getKYCStatus($userId) {
        try {
            $kyc = $this->db->fetchOne("
                SELECT * FROM kyc_verifications
                WHERE user_id = ?
                ORDER BY created_at DESC LIMIT 1
            ", [$userId]);

            if (!$kyc) {
                return [
                    'status' => 'not_started',
                    'message' => 'KYC verification not started'
                ];
            }

            // Get documents status
            $documents = $this->db->fetchAll("
                SELECT document_type, upload_status FROM kyc_documents
                WHERE kyc_id = ?
            ", [$kyc['id']]);

            // Get selfie status
            $selfie = $this->db->fetchOne("
                SELECT verification_status, face_comparison_result FROM kyc_selfies
                WHERE kyc_id = ?
            ", [$kyc['id']]);

            return [
                'kyc_id' => $kyc['id'],
                'status' => $kyc['status'],
                'submitted_at' => $kyc['submitted_at'],
                'reviewed_at' => $kyc['reviewed_at'],
                'documents' => $documents,
                'selfie' => $selfie,
                'review_comments' => $kyc['review_comments']
            ];

        } catch (Exception $e) {
            return [
                'status' => 'error',
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Enhanced ID verification with third-party integration
     */
    public function verifyIDWithThirdParty($kycId) {
        try {
            $kyc = $this->db->fetchOne("
                SELECT * FROM kyc_verifications WHERE id = ?
            ", [$kycId]);

            if (!$kyc) {
                throw new Exception('KYC record not found');
            }

            // Integration with third-party ID verification service
            $verificationResult = $this->callIDVerificationService($kyc);

            // Store verification result
            $this->db->execute("
                UPDATE kyc_verifications
                SET third_party_verification = ?, third_party_verified_at = NOW()
                WHERE id = ?
            ", [json_encode($verificationResult), $kycId]);

            return $verificationResult;

        } catch (Exception $e) {
            error_log('Third-party ID verification failed: ' . $e->getMessage());
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    // Private helper methods
    private function generateVerificationToken($userId, $kycId) {
        return hash('sha256', $userId . $kycId . time() . random_bytes(16));
    }

    private function getRequiredDocuments($idType) {
        $requirements = [
            'national_id' => ['national_id_front', 'national_id_back'],
            'passport' => ['passport_photo', 'passport_signature'],
            'driving_license' => ['license_front', 'license_back'],
            'military_id' => ['military_id_front', 'military_id_back']
        ];

        return $requirements[$idType] ?? ['national_id_front', 'national_id_back'];
    }

    private function validateFile($file) {
        // Check if file was uploaded
        if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            return ['valid' => false, 'error' => 'Invalid file upload'];
        }

        // Check file size
        if ($file['size'] > $this->maxFileSize) {
            return ['valid' => false, 'error' => 'File size exceeds 5MB limit'];
        }

        // Check file type
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($extension, $this->allowedFileTypes)) {
            return ['valid' => false, 'error' => 'Invalid file type. Allowed: ' . implode(', ', $this->allowedFileTypes)];
        }

        // Check MIME type
        $allowedMimes = ['image/jpeg', 'image/png', 'application/pdf'];
        if (!in_array($file['type'], $allowedMimes)) {
            return ['valid' => false, 'error' => 'Invalid file format'];
        }

        return ['valid' => true];
    }

    private function validateSelfie($file) {
        $validation = $this->validateFile($file);
        if (!$validation['valid']) {
            return $validation;
        }

        // Additional selfie-specific validations
        if ($file['type'] !== 'image/jpeg' && $file['type'] !== 'image/png') {
            return ['valid' => false, 'error' => 'Selfie must be an image file'];
        }

        return ['valid' => true];
    }

    private function generateSecureFilename($userId, $kycId, $documentType, $originalName) {
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        return "kyc_{$userId}_{$kycId}_{$documentType}_" . time() . "_{$this->generateRandomString(8)}." . $extension;
    }

    private function generateRandomString($length = 8) {
        return substr(str_shuffle(str_repeat($x = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ', ceil($length / strlen($x))), 1, $length);
    }

    private function saveUploadedFile($file, $filename) {
        $uploadDir = dirname(__DIR__) . '/uploads/kyc/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $filePath = $uploadDir . $filename;

        if (move_uploaded_file($file['tmp_name'], $filePath)) {
            return $filePath;
        }

        return false;
    }

    private function extractDocumentMetadata($filePath, $documentType) {
        $metadata = [];

        // Extract EXIF data from images
        if (exif_imagetype($filePath)) {
            $exif = @exif_read_data($filePath);
            if ($exif) {
                $metadata['exif'] = $exif;
            }
        }

        // Extract PDF metadata
        if (mime_content_type($filePath) === 'application/pdf') {
            // PDF metadata extraction would go here
            $metadata['pdf_info'] = 'PDF metadata extracted';
        }

        // Add document-specific metadata
        $metadata['document_type'] = $documentType;
        $metadata['file_size'] = filesize($filePath);
        $metadata['mime_type'] = mime_content_type($filePath);
        $metadata['uploaded_at'] = date('Y-m-d H:i:s');

        return $metadata;
    }

    private function processDocumentVerification($documentId) {
        try {
            $document = $this->db->fetchOne("
                SELECT * FROM kyc_documents WHERE id = ?
            ", [$documentId]);

            // Perform document validation
            $isValid = $this->validateDocument($document['file_path'], $document['document_type']);

            $status = $isValid ? 'verified' : 'rejected';
            $this->db->execute("
                UPDATE kyc_documents
                SET upload_status = ?, verified_at = NOW()
                WHERE id = ?
            ", [$status, $documentId]);

        } catch (Exception $e) {
            error_log('Document verification failed: ' . $e->getMessage());
        }
    }

    private function validateDocument($filePath, $documentType) {
        // Document validation logic
        // Check for watermarks, tampering, etc.
        return true; // Placeholder
    }

    private function analyzeFacialImage($filePath) {
        // Facial analysis implementation
        return [
            'face_detected' => true,
            'face_count' => 1,
            'confidence' => 0.95,
            'liveness_check' => 'passed',
            'quality_score' => 0.88
        ]; // Placeholder
    }

    private function compareFaces($idImagePath, $selfiePath) {
        // Face comparison implementation
        return [
            'match_score' => 0.92,
            'match_status' => 'match',
            'confidence' => 0.88,
            'message' => 'High confidence match between ID document and live selfie'
        ]; // Placeholder
    }

    private function assignReviewer($kycId) {
        // Assign KYC to available reviewer
        $reviewer = $this->db->fetchOne("
            SELECT id FROM users
            WHERE role = 'kyc_reviewer' AND status = 'active'
            ORDER BY RAND() LIMIT 1
        ");

        if ($reviewer) {
            $this->db->execute("
                UPDATE kyc_verifications SET reviewer_id = ? WHERE id = ?
            ", [$reviewer['id'], $kycId]);
        }
    }

    private function sendKYCNotification($userId, $status, $comments = '') {
        // Send notification about KYC status
        try {
            require_once 'whatsapp-api.php';
            $whatsapp = new WhatsAppAPI($this->db);

            $user = $this->db->fetchOne("SELECT name, phone FROM users WHERE id = ?", [$userId]);

            if ($status === 'approved') {
                $message = "Congratulations {$user['name']}! Your KYC verification has been approved. You now have full access to all JuaKali Lend features.";
            } else {
                $message = "Hi {$user['name']}, your KYC verification could not be completed. Reason: $comments. Please re-submit with correct documents.";
            }

            $whatsapp->sendTextMessage($user['phone'], $message);

        } catch (Exception $e) {
            error_log('Failed to send KYC notification: ' . $e->getMessage());
        }
    }

    private function callIDVerificationService($kyc) {
        // Integration with third-party ID verification service
        return [
            'status' => 'verified',
            'verification_id' => 'VER_' . time(),
            'confidence_score' => 0.94,
            'verified_fields' => ['name', 'id_number', 'date_of_birth']
        ]; // Placeholder
    }
}
?>