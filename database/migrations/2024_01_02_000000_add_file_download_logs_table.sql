-- ===================================================================
-- File Download Logs Table Migration
-- Created: 2024-01-02
-- Description: Add table for tracking file downloads
-- ===================================================================

-- ===================================================================
-- TABLE: file_download_logs
-- Description: Track file downloads for security and auditing
-- ===================================================================
CREATE TABLE IF NOT EXISTS `file_download_logs` (
    `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    `answer_id` bigint(20) UNSIGNED NOT NULL COMMENT 'submission_answers ID',
    `submission_id` bigint(20) UNSIGNED NOT NULL COMMENT 'form_submissions ID',
    `downloaded_by` varchar(255) NOT NULL COMMENT 'Who downloaded the file',
    `downloaded_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Download timestamp',
    `ip_address` varchar(45) DEFAULT NULL COMMENT 'Downloader IP address',
    
    PRIMARY KEY (`id`),
    KEY `file_download_logs_answer_id_foreign` (`answer_id`),
    KEY `file_download_logs_submission_id_foreign` (`submission_id`),
    KEY `file_download_logs_downloaded_at_index` (`downloaded_at`),
    
    CONSTRAINT `file_download_logs_answer_id_foreign` FOREIGN KEY (`answer_id`) REFERENCES `submission_answers` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `file_download_logs_submission_id_foreign` FOREIGN KEY (`submission_id`) REFERENCES `form_submissions` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='File download tracking logs table';

-- ===================================================================
-- MIGRATION COMPLETE
-- ===================================================================
SELECT 'File download logs table created successfully!' AS status;
