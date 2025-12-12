<?php
// config/upload_config.php - File Upload Configuration and Helper Functions

class FileUploadHandler {
    
    private $upload_dir = '../uploads/documents/';
    private $allowed_extensions = ['pdf', 'jpg', 'jpeg', 'png'];
    private $max_file_size = 5242880; // 5MB in bytes
    
    public function __construct() {
        // Create upload directory if it doesn't exist
        if (!file_exists($this->upload_dir)) {
            mkdir($this->upload_dir, 0777, true);
        }
    }
    
    /**
     * Upload a file with validation
     * 
     * @param array $file The $_FILES array element
     * @param int $user_id The user ID for unique filename
     * @param string $prefix Optional filename prefix
     * @return array Result array with 'success' boolean and 'message' or 'path'
     */
    public function uploadFile($file, $user_id, $prefix = 'doc') {
        // Check if file was uploaded
        if (!isset($file) || $file['error'] === UPLOAD_ERR_NO_FILE) {
            return [
                'success' => false,
                'message' => 'No file was uploaded'
            ];
        }
        
        // Check for upload errors
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return [
                'success' => false,
                'message' => $this->getUploadErrorMessage($file['error'])
            ];
        }
        
        // Validate file size
        if ($file['size'] > $this->max_file_size) {
            return [
                'success' => false,
                'message' => 'File size exceeds maximum allowed size of ' . ($this->max_file_size / 1048576) . 'MB'
            ];
        }
        
        // Validate file extension
        $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($file_extension, $this->allowed_extensions)) {
            return [
                'success' => false,
                'message' => 'Invalid file type. Allowed types: ' . implode(', ', $this->allowed_extensions)
            ];
        }
        
        // Validate MIME type for additional security
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime_type = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        
        $allowed_mime_types = [
            'application/pdf',
            'image/jpeg',
            'image/jpg',
            'image/png'
        ];
        
        if (!in_array($mime_type, $allowed_mime_types)) {
            return [
                'success' => false,
                'message' => 'Invalid file content. File may be corrupted or not a valid ' . strtoupper($file_extension) . ' file'
            ];
        }
        
        // Generate unique filename
        $new_filename = $prefix . '_' . $user_id . '_' . time() . '_' . uniqid() . '.' . $file_extension;
        $upload_path = $this->upload_dir . $new_filename;
        
        // Move uploaded file
        if (move_uploaded_file($file['tmp_name'], $upload_path)) {
            // Set proper permissions
            chmod($upload_path, 0644);
            
            return [
                'success' => true,
                'path' => 'uploads/documents/' . $new_filename,
                'filename' => $new_filename
            ];
        } else {
            return [
                'success' => false,
                'message' => 'Failed to move uploaded file. Check directory permissions.'
            ];
        }
    }
    
    /**
     * Delete a file
     * 
     * @param string $file_path The relative path to the file
     * @return bool Success status
     */
    public function deleteFile($file_path) {
        $full_path = '../' . $file_path;
        
        if (file_exists($full_path)) {
            return unlink($full_path);
        }
        
        return false;
    }
    
    /**
     * Get human-readable upload error message
     * 
     * @param int $error_code PHP upload error code
     * @return string Error message
     */
    private function getUploadErrorMessage($error_code) {
        switch ($error_code) {
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                return 'File is too large';
            case UPLOAD_ERR_PARTIAL:
                return 'File was only partially uploaded';
            case UPLOAD_ERR_NO_FILE:
                return 'No file was uploaded';
            case UPLOAD_ERR_NO_TMP_DIR:
                return 'Missing temporary folder';
            case UPLOAD_ERR_CANT_WRITE:
                return 'Failed to write file to disk';
            case UPLOAD_ERR_EXTENSION:
                return 'File upload stopped by extension';
            default:
                return 'Unknown upload error';
        }
    }
    
    /**
     * Get file information
     * 
     * @param string $file_path The relative path to the file
     * @return array|false File information or false if file doesn't exist
     */
    public function getFileInfo($file_path) {
        $full_path = '../' . $file_path;
        
        if (!file_exists($full_path)) {
            return false;
        }
        
        return [
            'filename' => basename($full_path),
            'size' => filesize($full_path),
            'size_formatted' => $this->formatFileSize(filesize($full_path)),
            'extension' => pathinfo($full_path, PATHINFO_EXTENSION),
            'uploaded' => date('Y-m-d H:i:s', filectime($full_path))
        ];
    }
    
    /**
     * Format file size in human-readable format
     * 
     * @param int $bytes File size in bytes
     * @return string Formatted file size
     */
    private function formatFileSize($bytes) {
        if ($bytes >= 1073741824) {
            return number_format($bytes / 1073741824, 2) . ' GB';
        } elseif ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2) . ' MB';
        } elseif ($bytes >= 1024) {
            return number_format($bytes / 1024, 2) . ' KB';
        } else {
            return $bytes . ' bytes';
        }
    }
    
    /**
     * Validate if file exists and is accessible
     * 
     * @param string $file_path The relative path to the file
     * @return bool
     */
    public function fileExists($file_path) {
        $full_path = '../' . $file_path;
        return file_exists($full_path) && is_readable($full_path);
    }
    
    /**
     * Clean up old files (for maintenance)
     * 
     * @param int $days_old Delete files older than this many days
     * @return int Number of files deleted
     */
    public function cleanupOldFiles($days_old = 90) {
        $deleted_count = 0;
        $cutoff_time = time() - ($days_old * 86400);
        
        $files = glob($this->upload_dir . '*');
        
        foreach ($files as $file) {
            if (is_file($file) && filectime($file) < $cutoff_time) {
                if (unlink($file)) {
                    $deleted_count++;
                }
            }
        }
        
        return $deleted_count;
    }
}

/**
 * Helper function to create a secure download link
 * 
 * @param string $file_path The relative path to the file
 * @param string $display_name Optional display name for the download
 * @return string HTML link
 */
function createDownloadLink($file_path, $display_name = 'Download Document') {
    $file_handler = new FileUploadHandler();
    
    if ($file_handler->fileExists($file_path)) {
        $file_info = $file_handler->getFileInfo($file_path);
        $extension = strtoupper($file_info['extension']);
        
        return '<a href="../' . htmlspecialchars($file_path) . '" target="_blank" class="document-link">' 
               . htmlspecialchars($display_name) . ' (' . $extension . ' - ' . $file_info['size_formatted'] . ')</a>';
    } else {
        return '<span style="color: #dc3545;">Document not found</span>';
    }
}

