-- ============================================
-- RESOURCES TABLE CREATION
-- ============================================
-- Purpose: Store learning resources, tutorials, code samples
-- Date: November 3, 2025
-- ============================================

CREATE TABLE IF NOT EXISTS RESOURCES (
    resource_id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    resource_type ENUM('tutorial', 'code_sample', 'past_question', 'study_guide', 'video', 'pdf', 'link', 'other') NOT NULL,
    file_path VARCHAR(500) NULL COMMENT 'Path to uploaded file',
    file_name VARCHAR(255) NULL COMMENT 'Original filename',
    file_size INT NULL COMMENT 'File size in bytes',
    external_link VARCHAR(500) NULL COMMENT 'External URL if resource is a link',
    course_code VARCHAR(20) NULL COMMENT 'Related course code',
    level ENUM('100','200','300','400','500','general') DEFAULT 'general',
    tags VARCHAR(500) NULL COMMENT 'Comma-separated tags',
    uploaded_by INT NOT NULL,
    download_count INT DEFAULT 0,
    views_count INT DEFAULT 0,
    is_featured BOOLEAN DEFAULT FALSE,
    is_active BOOLEAN DEFAULT TRUE,
    upload_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (uploaded_by) REFERENCES MEMBERS(member_id) ON DELETE CASCADE,
    INDEX idx_resource_type (resource_type),
    INDEX idx_level (level),
    INDEX idx_is_featured (is_featured),
    INDEX idx_is_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
