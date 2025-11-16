-- ============================================
-- PARTNERS TABLE CREATION
-- ============================================
-- Purpose: Store sponsors, collaborators, and partners
-- Date: November 3, 2025
-- ============================================

CREATE TABLE IF NOT EXISTS PARTNERS (
    partner_id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    partner_type ENUM('sponsor', 'collaborator', 'affiliate', 'industry', 'academic', 'other') NOT NULL,
    logo_path VARCHAR(500) NULL COMMENT 'Path to partner logo',
    logo_name VARCHAR(255) NULL COMMENT 'Original logo filename',
    website_url VARCHAR(500) NULL,
    contact_email VARCHAR(100) NULL,
    contact_phone VARCHAR(20) NULL,
    partnership_since DATE NULL,
    is_active BOOLEAN DEFAULT TRUE,
    display_order INT DEFAULT 0 COMMENT 'For sorting partners display',
    is_featured BOOLEAN DEFAULT FALSE COMMENT 'Feature on homepage',
    added_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (added_by) REFERENCES MEMBERS(member_id) ON DELETE CASCADE,
    INDEX idx_partner_type (partner_type),
    INDEX idx_is_active (is_active),
    INDEX idx_is_featured (is_featured),
    INDEX idx_display_order (display_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
