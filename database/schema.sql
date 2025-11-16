-- ============================================
-- NACOS DASHBOARD DATABASE SCHEMA
-- ============================================
-- Purpose: Complete database structure for the NACOS Dashboard
-- Supporting: Public pages, Admin backend, and Member empowerment
-- Created: November 2, 2025
-- ============================================

-- Drop existing tables (in reverse order of dependencies)
DROP TABLE IF EXISTS MEMBER_PROJECTS;
DROP TABLE IF EXISTS MEMBER_EVENTS;
DROP TABLE IF EXISTS RESOURCES;
DROP TABLE IF EXISTS PARTNERS;
DROP TABLE IF EXISTS EVENTS;
DROP TABLE IF EXISTS PROJECTS;
DROP TABLE IF EXISTS DOCUMENTS;
DROP TABLE IF EXISTS MEMBERS;
DROP TABLE IF EXISTS ADMINISTRATORS;

-- ============================================
-- CORE & ADMIN TABLES
-- ============================================

-- ADMINISTRATORS Table
-- Purpose: System login security and role-based access control
CREATE TABLE ADMINISTRATORS (
    admin_id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('super_admin', 'admin', 'moderator') DEFAULT 'admin',
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    status ENUM('active', 'inactive') DEFAULT 'active',
    last_login DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_username (username),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table to store incoming partner interest requests from the public
CREATE TABLE PARTNER_REQUESTS (
    request_id INT AUTO_INCREMENT PRIMARY KEY,
    company_name VARCHAR(150) NOT NULL,
    contact_person VARCHAR(100) NULL,
    contact_email VARCHAR(100) NOT NULL,
    contact_phone VARCHAR(30) NULL,
    website_url VARCHAR(500) NULL,
    message TEXT NULL,
    status ENUM('new','reviewed','rejected') DEFAULT 'new',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- MEMBERS Table
-- Purpose: Accurate headcount and comprehensive member profile data
CREATE TABLE MEMBERS (
    member_id INT AUTO_INCREMENT PRIMARY KEY,
    matric_no VARCHAR(20) NOT NULL UNIQUE,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    phone VARCHAR(20) NULL,
    department VARCHAR(100) NOT NULL,
    level ENUM('100', '200', '300', '400', '500') NOT NULL,
    gender ENUM('Male', 'Female', 'Other') NULL,
    registration_date DATE NOT NULL,
    membership_status ENUM('active', 'inactive', 'alumni') DEFAULT 'active',
    profile_picture VARCHAR(255) NULL,
    skills TEXT NULL COMMENT 'JSON array of skills',
    bio TEXT NULL,
    github_username VARCHAR(100) NULL,
    linkedin_url VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_matric_no (matric_no),
    INDEX idx_department (department),
    INDEX idx_level (level),
    INDEX idx_status (membership_status),
    INDEX idx_name (full_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- DOCUMENTS Table
-- Purpose: Secure storage paths for handover notes and institutional knowledge
CREATE TABLE DOCUMENTS (
    doc_id INT AUTO_INCREMENT PRIMARY KEY,
    file_name VARCHAR(255) NOT NULL,
    file_path VARCHAR(500) NOT NULL COMMENT 'Stored outside public web root',
    doc_type ENUM('handover', 'policy', 'financial', 'meeting_minutes', 'report', 'other') NOT NULL,
    description TEXT NULL,
    uploaded_by INT NOT NULL,
    academic_session VARCHAR(20) NULL COMMENT 'e.g., 2024/2025',
    file_size INT NULL COMMENT 'Size in bytes',
    upload_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (uploaded_by) REFERENCES ADMINISTRATORS(admin_id) ON DELETE RESTRICT,
    INDEX idx_doc_type (doc_type),
    INDEX idx_session (academic_session)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- CONTENT TABLES
-- ============================================

-- PROJECTS Table
-- Purpose: Innovation Portfolio content with verifiable links
CREATE TABLE PROJECTS (
    project_id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(200) NOT NULL,
    description TEXT NOT NULL,
    repository_link VARCHAR(500) NULL COMMENT 'GitHub/GitLab URL',
    demo_link VARCHAR(500) NULL COMMENT 'Live demo URL',
    project_image VARCHAR(255) NULL,
    tech_stack TEXT NULL COMMENT 'JSON array of technologies used',
    collaborating_depts TEXT NULL COMMENT 'JSON array of departments involved',
    project_status ENUM('ideation', 'in_progress', 'completed', 'archived') DEFAULT 'in_progress',
    start_date DATE NULL,
    completion_date DATE NULL,
    visibility ENUM('public', 'private') DEFAULT 'public',
    featured BOOLEAN DEFAULT FALSE,
    view_count INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_status (project_status),
    INDEX idx_featured (featured),
    INDEX idx_visibility (visibility),
    FULLTEXT idx_search (title, description)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- EVENTS Table
-- Purpose: Tech Workshops, Bootcamps, NACOS Week calendar content
CREATE TABLE EVENTS (
    event_id INT AUTO_INCREMENT PRIMARY KEY,
    event_name VARCHAR(200) NOT NULL,
    event_type ENUM('workshop', 'bootcamp', 'nacos_week', 'seminar', 'competition', 'networking', 'other') NOT NULL,
    event_date DATE NOT NULL,
    event_time TIME NULL,
    end_date DATE NULL COMMENT 'For multi-day events',
    location VARCHAR(200) NULL,
    venue_type ENUM('physical', 'virtual', 'hybrid') DEFAULT 'physical',
    meeting_link VARCHAR(500) NULL COMMENT 'For virtual/hybrid events',
    summary TEXT NOT NULL,
    full_description TEXT NULL,
    speaker_name VARCHAR(100) NULL,
    speaker_title VARCHAR(150) NULL,
    speaker_bio TEXT NULL,
    event_image VARCHAR(255) NULL,
    capacity INT NULL COMMENT 'Maximum attendees',
    registration_required BOOLEAN DEFAULT TRUE,
    registration_link VARCHAR(500) NULL,
    status ENUM('upcoming', 'ongoing', 'completed', 'cancelled') DEFAULT 'upcoming',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_event_date (event_date),
    INDEX idx_event_type (event_type),
    INDEX idx_status (status),
    FULLTEXT idx_search (event_name, summary)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- RESOURCES Table
-- Purpose: Learning materials and downloadable resources
CREATE TABLE RESOURCES (
    resource_id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(200) NOT NULL,
    resource_type ENUM('slides', 'video', 'document', 'code', 'link', 'book', 'other') NOT NULL,
    link_url VARCHAR(500) NOT NULL COMMENT 'External URL or file path',
    description TEXT NULL,
    event_id INT NULL COMMENT 'Link to related event',
    upload_date DATE NOT NULL,
    file_size INT NULL COMMENT 'Size in bytes if uploaded file',
    downloads_count INT DEFAULT 0,
    tags TEXT NULL COMMENT 'JSON array of tags for filtering',
    visibility ENUM('public', 'members_only') DEFAULT 'public',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (event_id) REFERENCES EVENTS(event_id) ON DELETE SET NULL,
    INDEX idx_resource_type (resource_type),
    INDEX idx_event_id (event_id),
    INDEX idx_visibility (visibility),
    FULLTEXT idx_search (title, description)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- PARTNERS Table
-- Purpose: Partnership and sponsorship management
CREATE TABLE PARTNERS (
    partner_id INT AUTO_INCREMENT PRIMARY KEY,
    company_name VARCHAR(150) NOT NULL,
    partnership_type ENUM('sponsor', 'mentor', 'industry_partner', 'academic_partner', 'other') NOT NULL,
    status ENUM('active', 'pending', 'inactive', 'former') DEFAULT 'pending',
    contact_person VARCHAR(100) NULL,
    contact_email VARCHAR(100) NULL,
    contact_phone VARCHAR(20) NULL,
    company_logo VARCHAR(255) NULL,
    website_url VARCHAR(500) NULL,
    description TEXT NULL COMMENT 'What they offer/partnership details',
    partnership_start_date DATE NULL,
    partnership_end_date DATE NULL,
    value_offered TEXT NULL COMMENT 'Resources, funding, mentorship details',
    visibility ENUM('public', 'private') DEFAULT 'public' COMMENT 'Show on Partner Portal?',
    is_featured TINYINT(1) DEFAULT 0 COMMENT 'Flag to feature partner on public pages',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_status (status),
    INDEX idx_type (partnership_type),
    INDEX idx_visibility (visibility)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- RELATIONAL TABLES (Many-to-Many)
-- ============================================

-- MEMBER_EVENTS Table
-- Purpose: Track member attendance and engagement with events
CREATE TABLE MEMBER_EVENTS (
    member_event_id INT AUTO_INCREMENT PRIMARY KEY,
    member_id INT NOT NULL,
    event_id INT NOT NULL,
    registration_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    attendance_status ENUM('registered', 'attended', 'absent', 'cancelled') DEFAULT 'registered',
    feedback_rating INT NULL COMMENT '1-5 star rating',
    feedback_comment TEXT NULL,
    certificate_issued BOOLEAN DEFAULT FALSE,
    FOREIGN KEY (member_id) REFERENCES MEMBERS(member_id) ON DELETE CASCADE,
    FOREIGN KEY (event_id) REFERENCES EVENTS(event_id) ON DELETE CASCADE,
    UNIQUE KEY unique_member_event (member_id, event_id),
    INDEX idx_member_id (member_id),
    INDEX idx_event_id (event_id),
    INDEX idx_attendance (attendance_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- MEMBER_PROJECTS Table
-- Purpose: Track member contributions to innovation projects
CREATE TABLE MEMBER_PROJECTS (
    member_project_id INT AUTO_INCREMENT PRIMARY KEY,
    member_id INT NOT NULL,
    project_id INT NOT NULL,
    role_on_project VARCHAR(100) NULL COMMENT 'e.g., Lead Developer, Designer, etc.',
    contribution_description TEXT NULL,
    join_date DATE NOT NULL,
    status ENUM('active', 'completed', 'left') DEFAULT 'active',
    hours_contributed DECIMAL(6,2) NULL COMMENT 'Optional tracking',
    FOREIGN KEY (member_id) REFERENCES MEMBERS(member_id) ON DELETE CASCADE,
    FOREIGN KEY (project_id) REFERENCES PROJECTS(project_id) ON DELETE CASCADE,
    UNIQUE KEY unique_member_project (member_id, project_id),
    INDEX idx_member_id (member_id),
    INDEX idx_project_id (project_id),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- ADDITIONAL INDEXES FOR PERFORMANCE
-- ============================================

-- Composite indexes for common queries
CREATE INDEX idx_member_status_level ON MEMBERS(membership_status, level);
CREATE INDEX idx_project_status_featured ON PROJECTS(project_status, featured);
CREATE INDEX idx_event_status_date ON EVENTS(status, event_date);

-- ============================================
-- DATABASE SCHEMA CREATION COMPLETE
-- ============================================
-- Total Tables: 10
-- Core/Admin: ADMINISTRATORS, MEMBERS, DOCUMENTS
-- Content: PROJECTS, EVENTS, RESOURCES, PARTNERS
-- Relational: MEMBER_EVENTS, MEMBER_PROJECTS
-- ============================================
