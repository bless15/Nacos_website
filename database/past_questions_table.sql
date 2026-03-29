-- ============================================
-- NACOS DASHBOARD - PAST QUESTIONS TABLE
-- Purpose: Store 100-400 level past questions uploads
-- ============================================

CREATE TABLE IF NOT EXISTS past_questions (
    question_id INT AUTO_INCREMENT PRIMARY KEY,
    level ENUM('100','200','300','400') NOT NULL,
    course_title VARCHAR(255) NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    file_name VARCHAR(255) NOT NULL,
    file_type ENUM('pdf','image') NOT NULL,
    file_size BIGINT UNSIGNED DEFAULT NULL,
    uploaded_by INT DEFAULT NULL,
    download_count INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_level (level),
    INDEX idx_course_title (course_title)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;