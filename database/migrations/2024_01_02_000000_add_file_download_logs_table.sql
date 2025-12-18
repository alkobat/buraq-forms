-- ===================================================================
-- File Download Logs Table Migration
-- Description: Track file downloads for security and auditing
-- ===================================================================

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS `file_download_logs`;
SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE `file_download_logs` (
    `id` INT PRIMARY KEY AUTO_INCREMENT,
    `submission_id` INT NOT NULL,
    `field_id` INT NULL,
    `file_name` VARCHAR(255),
    `downloaded_by` VARCHAR(255),
    `ip_address` VARCHAR(45),
    `downloaded_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`submission_id`) REFERENCES `form_submissions`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`field_id`) REFERENCES `form_fields`(`id`) ON DELETE SET NULL,
    INDEX `idx_file_download_logs_submission_id` (`submission_id`),
    INDEX `idx_file_download_logs_downloaded_at` (`downloaded_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SELECT 'File download logs table created successfully!' AS status;
