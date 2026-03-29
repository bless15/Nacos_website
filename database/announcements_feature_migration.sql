-- ============================================
-- ANNOUNCEMENTS FEATURE MIGRATION
-- ============================================
-- Purpose: Adds announcements module with comments, reactions, polls,
-- notifications, and audit logs.
-- Date: March 21, 2026

-- If executive_position already exists, only this MODIFY is needed to add PRO.
ALTER TABLE members
MODIFY COLUMN executive_position ENUM(
    'social_director',
    'general_secretary',
    'academic_director',
    'creative_innovative_director',
    'public_relations_officer'
) NULL;

CREATE TABLE IF NOT EXISTS announcements (
    announcement_id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    body TEXT NULL,
    image_path VARCHAR(255) NULL,
    external_link VARCHAR(500) NULL,
    cta_label VARCHAR(120) NULL,
    audience_level ENUM('all', '100', '200', '300', '400', '500') DEFAULT 'all',
    audience_status ENUM('all', 'active', 'inactive', 'alumni', 'pending') DEFAULT 'all',
    is_pinned TINYINT(1) DEFAULT 0,
    status ENUM('draft', 'published') DEFAULT 'draft',
    publish_at DATETIME NULL,
    expires_at DATETIME NULL,
    allow_comments TINYINT(1) DEFAULT 1,
    allow_poll TINYINT(1) DEFAULT 0,
    creator_type ENUM('admin_account', 'member') NOT NULL,
    created_by_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_announcements_status (status),
    INDEX idx_announcements_pin_date (is_pinned, publish_at, created_at),
    INDEX idx_announcements_audience (audience_level, audience_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS announcement_comments (
    comment_id INT AUTO_INCREMENT PRIMARY KEY,
    announcement_id INT NOT NULL,
    member_id INT NOT NULL,
    comment_text TEXT NOT NULL,
    is_hidden TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (announcement_id) REFERENCES announcements(announcement_id) ON DELETE CASCADE,
    FOREIGN KEY (member_id) REFERENCES members(member_id) ON DELETE CASCADE,
    INDEX idx_announcement_comments_created (announcement_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS announcement_reactions (
    reaction_id INT AUTO_INCREMENT PRIMARY KEY,
    announcement_id INT NOT NULL,
    member_id INT NOT NULL,
    reaction ENUM('like', 'love') NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (announcement_id) REFERENCES announcements(announcement_id) ON DELETE CASCADE,
    FOREIGN KEY (member_id) REFERENCES members(member_id) ON DELETE CASCADE,
    UNIQUE KEY uniq_announcement_member_reaction (announcement_id, member_id),
    INDEX idx_announcement_reaction_type (announcement_id, reaction)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS announcement_polls (
    poll_id INT AUTO_INCREMENT PRIMARY KEY,
    announcement_id INT NOT NULL,
    question VARCHAR(255) NOT NULL,
    allow_multiple TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (announcement_id) REFERENCES announcements(announcement_id) ON DELETE CASCADE,
    UNIQUE KEY uniq_poll_announcement (announcement_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS announcement_poll_options (
    option_id INT AUTO_INCREMENT PRIMARY KEY,
    poll_id INT NOT NULL,
    option_text VARCHAR(255) NOT NULL,
    sort_order INT DEFAULT 0,
    FOREIGN KEY (poll_id) REFERENCES announcement_polls(poll_id) ON DELETE CASCADE,
    INDEX idx_poll_options_sort (poll_id, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS announcement_poll_votes (
    vote_id INT AUTO_INCREMENT PRIMARY KEY,
    poll_id INT NOT NULL,
    option_id INT NOT NULL,
    member_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (poll_id) REFERENCES announcement_polls(poll_id) ON DELETE CASCADE,
    FOREIGN KEY (option_id) REFERENCES announcement_poll_options(option_id) ON DELETE CASCADE,
    FOREIGN KEY (member_id) REFERENCES members(member_id) ON DELETE CASCADE,
    UNIQUE KEY uniq_poll_member_vote (poll_id, member_id),
    INDEX idx_poll_votes_option (option_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS announcement_notifications (
    notification_id INT AUTO_INCREMENT PRIMARY KEY,
    announcement_id INT NOT NULL,
    member_id INT NOT NULL,
    is_read TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (announcement_id) REFERENCES announcements(announcement_id) ON DELETE CASCADE,
    FOREIGN KEY (member_id) REFERENCES members(member_id) ON DELETE CASCADE,
    UNIQUE KEY uniq_announcement_notification (announcement_id, member_id),
    INDEX idx_member_notifications (member_id, is_read, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS announcement_audit_logs (
    log_id INT AUTO_INCREMENT PRIMARY KEY,
    announcement_id INT NULL,
    actor_type ENUM('admin_account', 'member') NOT NULL,
    actor_id INT NOT NULL,
    action VARCHAR(80) NOT NULL,
    details TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_announcement_audit_lookup (announcement_id, created_at),
    INDEX idx_announcement_audit_actor (actor_type, actor_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
