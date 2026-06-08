<?php
require_once 'config.php';

$db = new Database();

$sql = "
-- Create table for mobile app uploads
CREATE TABLE IF NOT EXISTS mobile_app_uploads (
    id INT AUTO_INCREMENT PRIMARY KEY,
    filename VARCHAR(255) NOT NULL,
    filepath VARCHAR(500) NOT NULL,
    upload_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    uploaded_by INT NOT NULL,
    FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE CASCADE
);

-- Add index for better performance
CREATE INDEX idx_upload_date ON mobile_app_uploads(upload_date);
CREATE INDEX idx_uploaded_by ON mobile_app_uploads(uploaded_by);
";

try {
    $db->getConnection()->exec($sql);
    echo "Table 'mobile_app_uploads' created successfully.";
} catch (Exception $e) {
    echo "Error creating table: " . $e->getMessage();
}
?>
