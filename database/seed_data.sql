-- ============================================
-- NACOS DASHBOARD - SEED DATA
-- ============================================
-- Purpose: Sample data for development and testing
-- Usage: Run AFTER schema.sql
-- Created: November 2, 2025
-- ============================================

-- ============================================
-- 1. ADMINISTRATORS (with secure passwords)
-- ============================================
-- NOTE: All passwords are hashed using PASSWORD_BCRYPT
-- Default password for all admins: "Admin@2025"
-- Hash generated using PHP: password_hash('Admin@2025', PASSWORD_BCRYPT)

INSERT INTO ADMINISTRATORS (username, password_hash, role, full_name, email, status) VALUES
('super_admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'super_admin', 'Chukwuemeka Okafor', 'super.admin@nacos.edu.ng', 'active'),
('admin_tech', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 'Aisha Mohammed', 'aisha.mohammed@nacos.edu.ng', 'active'),
('admin_events', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 'Oluwaseun Adebayo', 'seun.adebayo@nacos.edu.ng', 'active'),
('moderator_1', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'moderator', 'Fatima Ibrahim', 'fatima.ibrahim@nacos.edu.ng', 'active');

-- ============================================
-- 2. MEMBERS (Sample student database)
-- ============================================

INSERT INTO MEMBERS (matric_no, full_name, email, phone, department, level, gender, registration_date, membership_status, skills, bio, github_username, linkedin_url) VALUES
-- 100 Level Students
('CSC/2024/001', 'Ibrahim Musa', 'ibrahim.musa@student.edu.ng', '08012345678', 'Computer Science', '100', 'Male', '2024-09-15', 'active', '["HTML", "CSS", "JavaScript"]', 'Aspiring full-stack developer passionate about web technologies.', 'ibrahim_dev', 'https://linkedin.com/in/ibrahim-musa'),
('CSC/2024/002', 'Blessing Okonkwo', 'blessing.oko@student.edu.ng', '08023456789', 'Computer Science', '100', 'Female', '2024-09-15', 'active', '["Python", "Data Analysis"]', 'Interested in data science and machine learning.', 'blessingdata', 'https://linkedin.com/in/blessing-okonkwo'),
('SWE/2024/003', 'Daniel Okoro', 'daniel.okoro@student.edu.ng', '08034567890', 'Software Engineering', '100', 'Male', '2024-09-16', 'active', '["Java", "C++"]', 'Software engineering enthusiast learning algorithms.', 'daniel_codes', NULL),
('CYB/2024/004', 'Zainab Hassan', 'zainab.hassan@student.edu.ng', '08045678901', 'Cyber Security', '100', 'Female', '2024-09-16', 'active', '["Network Security", "Ethical Hacking"]', 'Cybersecurity advocate focused on network defense.', 'zainab_sec', 'https://linkedin.com/in/zainab-hassan'),

-- 200 Level Students
('CSC/2023/010', 'Chinedu Obi', 'chinedu.obi@student.edu.ng', '08056789012', 'Computer Science', '200', 'Male', '2023-09-10', 'active', '["React", "Node.js", "MongoDB"]', 'Building modern web applications with MERN stack.', 'chinedu_fullstack', 'https://linkedin.com/in/chinedu-obi'),
('CSC/2023/011', 'Amina Bello', 'amina.bello@student.edu.ng', '08067890123', 'Computer Science', '200', 'Female', '2023-09-10', 'active', '["UI/UX Design", "Figma", "Adobe XD"]', 'Creative designer passionate about user experience.', 'amina_designs', 'https://linkedin.com/in/amina-bello'),
('SWE/2023/012', 'Tunde Williams', 'tunde.williams@student.edu.ng', '08078901234', 'Software Engineering', '200', 'Male', '2023-09-11', 'active', '["Flutter", "Dart", "Firebase"]', 'Mobile app developer focused on cross-platform solutions.', 'tunde_mobile', NULL),
('IT/2023/013', 'Grace Nwosu', 'grace.nwosu@student.edu.ng', '08089012345', 'Information Technology', '200', 'Female', '2023-09-11', 'active', '["PHP", "MySQL", "Laravel"]', 'Backend developer interested in scalable systems.', 'grace_backend', 'https://linkedin.com/in/grace-nwosu'),

-- 300 Level Students
('CSC/2022/020', 'Abdullahi Yusuf', 'abdullahi.yusuf@student.edu.ng', '08090123456', 'Computer Science', '300', 'Male', '2022-09-12', 'active', '["Python", "Django", "Machine Learning"]', 'ML engineer building intelligent applications.', 'abdullahi_ml', 'https://linkedin.com/in/abdullahi-yusuf'),
('CSC/2022/021', 'Chioma Eze', 'chioma.eze@student.edu.ng', '08001234567', 'Computer Science', '300', 'Female', '2022-09-12', 'active', '["Data Science", "R", "TensorFlow"]', 'Data scientist exploring AI and neural networks.', 'chioma_ai', 'https://linkedin.com/in/chioma-eze'),
('SWE/2022/022', 'Emeka Nnamdi', 'emeka.nnamdi@student.edu.ng', '08011234568', 'Software Engineering', '300', 'Male', '2022-09-13', 'active', '["DevOps", "Docker", "Kubernetes"]', 'DevOps engineer automating deployment pipelines.', 'emeka_devops', 'https://linkedin.com/in/emeka-nnamdi'),
('CYB/2022/023', 'Halima Abubakar', 'halima.abu@student.edu.ng', '08021234569', 'Cyber Security', '300', 'Female', '2022-09-13', 'active', '["Penetration Testing", "OSINT", "Kali Linux"]', 'Ethical hacker and security researcher.', 'halima_pentest', NULL),

-- 400 Level Students
('CSC/2021/030', 'Yusuf Adamu', 'yusuf.adamu@student.edu.ng', '08031234570', 'Computer Science', '400', 'Male', '2021-09-14', 'active', '["Blockchain", "Solidity", "Web3"]', 'Blockchain developer building decentralized apps.', 'yusuf_web3', 'https://linkedin.com/in/yusuf-adamu'),
('CSC/2021/031', 'Ngozi Okafor', 'ngozi.okafor@student.edu.ng', '08041234571', 'Computer Science', '400', 'Female', '2021-09-14', 'active', '["Cloud Computing", "AWS", "Azure"]', 'Cloud architect designing scalable infrastructure.', 'ngozi_cloud', 'https://linkedin.com/in/ngozi-okafor'),
('SWE/2021/032', 'Ahmed Bala', 'ahmed.bala@student.edu.ng', '08051234572', 'Software Engineering', '400', 'Male', '2021-09-15', 'active', '["Game Development", "Unity", "C#"]', 'Game developer creating immersive experiences.', 'ahmed_gamedev', NULL),
('IT/2021/033', 'Blessing Chukwu', 'blessing.chukwu@student.edu.ng', '08061234573', 'Information Technology', '400', 'Female', '2021-09-15', 'active', '["System Administration", "Linux", "Networking"]', 'IT administrator managing enterprise systems.', 'blessing_sysadmin', 'https://linkedin.com/in/blessing-chukwu'),

-- 500 Level Students (Final Year)
('CSC/2020/040', 'Olayinka Adeleke', 'olayinka.adeleke@student.edu.ng', '08071234574', 'Computer Science', '500', 'Male', '2020-09-16', 'active', '["Full Stack", "Vue.js", "Express", "PostgreSQL"]', 'Senior developer and NACOS technical lead.', 'olayinka_lead', 'https://linkedin.com/in/olayinka-adeleke'),
('CSC/2020/041', 'Fatima Sani', 'fatima.sani@student.edu.ng', '08081234575', 'Computer Science', '500', 'Female', '2020-09-16', 'active', '["AI Research", "NLP", "Deep Learning"]', 'AI researcher working on language models.', 'fatima_nlp', 'https://linkedin.com/in/fatima-sani'),
('SWE/2020/042', 'Victor Okonkwo', 'victor.okonkwo@student.edu.ng', '08091234576', 'Software Engineering', '500', 'Male', '2020-09-17', 'active', '["Microservices", "Spring Boot", "Redis"]', 'Software architect building distributed systems.', 'victor_architect', 'https://linkedin.com/in/victor-okonkwo'),
('CYB/2020/043', 'Hauwa Mohammed', 'hauwa.mohammed@student.edu.ng', '08001234577', 'Cyber Security', '500', 'Female', '2020-09-17', 'active', '["Incident Response", "Forensics", "SIEM"]', 'Security analyst specializing in threat detection.', 'hauwa_forensics', 'https://linkedin.com/in/hauwa-mohammed');

-- ============================================
-- 3. PROJECTS (Innovation Portfolio)
-- ============================================

INSERT INTO PROJECTS (title, description, repository_link, demo_link, tech_stack, collaborating_depts, project_status, start_date, featured, visibility) VALUES
('NACOS Dashboard', 'Comprehensive student management and engagement platform for NACOS chapter.', 'https://github.com/nacos/dashboard', 'https://nacos-dashboard.edu.ng', '["PHP", "MySQL", "JavaScript", "Bootstrap"]', '["Computer Science", "Software Engineering", "Information Technology"]', 'in_progress', '2024-10-01', TRUE, 'public'),

('Smart Campus Navigator', 'Mobile app to help students navigate campus with AR features and real-time updates.', 'https://github.com/nacos/campus-navigator', 'https://play.google.com/store/apps/campus-nav', '["Flutter", "Firebase", "ARCore"]', '["Software Engineering", "Computer Science"]', 'completed', '2024-06-15', TRUE, 'public'),

('AI Exam Prep Assistant', 'Machine learning-powered study companion that generates practice questions from course materials.', 'https://github.com/nacos/exam-prep-ai', 'https://exam-prep.nacos.ng', '["Python", "TensorFlow", "Flask", "React"]', '["Computer Science"]', 'in_progress', '2024-08-20', TRUE, 'public'),

('CyberShield - Vulnerability Scanner', 'Automated security tool for scanning web applications for common vulnerabilities.', 'https://github.com/nacos/cybershield', NULL, '["Python", "Nmap", "OWASP ZAP"]', '["Cyber Security", "Computer Science"]', 'completed', '2024-05-10', FALSE, 'public'),

('E-Learning Platform', 'Open-source learning management system tailored for Nigerian universities.', 'https://github.com/nacos/e-learning', 'https://elearn.nacos.ng', '["Laravel", "Vue.js", "MySQL"]', '["Software Engineering", "Information Technology"]', 'in_progress', '2024-07-01', TRUE, 'public'),

('BlockVote - Secure Voting System', 'Blockchain-based voting system ensuring transparency and tamper-proof elections.', 'https://github.com/nacos/blockvote', 'https://blockvote-demo.nacos.ng', '["Solidity", "Ethereum", "React", "Web3.js"]', '["Computer Science"]', 'completed', '2024-03-15', TRUE, 'public'),

('Campus IoT Energy Monitor', 'IoT system to monitor and optimize energy consumption across campus buildings.', 'https://github.com/nacos/iot-energy', NULL, '["Arduino", "Python", "MQTT", "Node-RED"]', '["Information Technology", "Computer Science"]', 'ideation', '2024-10-20', FALSE, 'public');

-- ============================================
-- 4. EVENTS (Calendar Content)
-- ============================================

INSERT INTO EVENTS (event_name, event_type, event_date, event_time, location, venue_type, summary, speaker_name, speaker_title, capacity, status) VALUES
('Introduction to Web Development', 'workshop', '2024-11-15', '10:00:00', 'CSC Lab 1', 'physical', 'Beginner-friendly workshop covering HTML, CSS, and JavaScript fundamentals.', 'Chinedu Obi', 'Senior Developer, TechCorp', 50, 'upcoming'),

('Python for Data Science Bootcamp', 'bootcamp', '2024-11-20', '09:00:00', 'Main Auditorium', 'physical', '3-day intensive bootcamp on data analysis, visualization, and machine learning with Python.', 'Dr. Amara Chibueze', 'Data Scientist, DataHub Africa', 100, 'upcoming'),

('NACOS Week 2024 - Tech Summit', 'nacos_week', '2024-12-05', '08:00:00', 'University Convention Center', 'hybrid', 'Annual NACOS Week featuring keynotes, hackathons, exhibitions, and networking.', 'Multiple Speakers', 'Industry Leaders', 300, 'upcoming'),

('Cybersecurity Awareness Seminar', 'seminar', '2024-11-25', '14:00:00', 'Zoom Meeting', 'virtual', 'Learn about online security, phishing prevention, and best practices for digital safety.', 'Engr. Halima Abubakar', 'Cybersecurity Consultant', 150, 'upcoming'),

('Hackathon: Build for Impact', 'competition', '2024-12-10', '08:00:00', 'Innovation Hub', 'physical', '24-hour hackathon challenging students to build solutions for local community problems.', 'Judging Panel', 'Tech Industry Experts', 80, 'upcoming'),

('Git & GitHub Workshop', 'workshop', '2024-10-20', '13:00:00', 'CSC Lab 2', 'physical', 'Hands-on workshop on version control, collaboration, and open-source contribution.', 'Olayinka Adeleke', 'Final Year Student & GitHub Campus Expert', 40, 'completed'),

('Inter-Chapter Networking Meetup', 'networking', '2024-10-25', '16:00:00', 'Student Lounge', 'physical', 'Networking event connecting NACOS members across departments for collaboration.', NULL, NULL, 60, 'completed');

-- ============================================
-- 5. RESOURCES (Learning Materials)
-- ============================================

INSERT INTO RESOURCES (title, resource_type, link_url, description, event_id, upload_date, tags, visibility) VALUES
('Web Development Workshop Slides', 'slides', 'https://drive.google.com/slides/webdev-intro', 'Complete slide deck from the Introduction to Web Development workshop.', 1, '2024-11-14', '["HTML", "CSS", "JavaScript", "Beginner"]', 'public'),

('Python Data Science Notebook', 'code', 'https://github.com/nacos/resources/python-ds', 'Jupyter notebooks with examples from the Data Science Bootcamp.', 2, '2024-11-19', '["Python", "Data Science", "Pandas", "NumPy"]', 'public'),

('Cybersecurity Best Practices Guide', 'document', 'https://nacos.edu.ng/files/cybersec-guide.pdf', 'Comprehensive PDF guide covering online security fundamentals.', 4, '2024-11-24', '["Security", "Best Practices", "Privacy"]', 'public'),

('Git Cheat Sheet', 'document', 'https://nacos.edu.ng/files/git-cheatsheet.pdf', 'Quick reference for essential Git commands and workflows.', 6, '2024-10-19', '["Git", "Version Control", "Reference"]', 'public'),

('NACOS Week 2023 Recordings', 'video', 'https://youtube.com/playlist?list=nacos-week-2023', 'Full recordings of last year\'s NACOS Week sessions and keynotes.', NULL, '2024-01-15', '["NACOS Week", "Archives", "Keynotes"]', 'public'),

('Recommended Programming Books', 'link', 'https://nacos.edu.ng/reading-list', 'Curated list of essential books for computer science students.', NULL, '2024-09-01', '["Books", "Learning", "Reference"]', 'public');

-- ============================================
-- 6. PARTNERS (Sponsors & Mentors)
-- ============================================

INSERT INTO PARTNERS (company_name, partnership_type, status, contact_person, contact_email, company_logo, website_url, description, partnership_start_date, visibility) VALUES
('TechCorp Nigeria', 'sponsor', 'active', 'Mr. Adebayo Johnson', 'partnerships@techcorp.ng', 'techcorp-logo.png', 'https://techcorp.ng', 'Leading technology company providing financial support and internship opportunities.', '2024-01-15', 'public'),

('CodeMentor Hub', 'mentor', 'active', 'Mrs. Chidinma Okafor', 'info@codementor.ng', 'codementor-logo.png', 'https://codementor.ng', 'Professional mentorship program connecting students with industry experts.', '2024-03-20', 'public'),

('DataHub Africa', 'industry_partner', 'active', 'Dr. Emeka Nwosu', 'partnerships@datahub.africa', 'datahub-logo.png', 'https://datahub.africa', 'Data science consultancy offering workshops and real-world project collaborations.', '2024-02-10', 'public'),

('University Innovation Center', 'academic_partner', 'active', 'Prof. Amina Bello', 'innovation@university.edu.ng', 'uic-logo.png', 'https://innovation.university.edu.ng', 'University center providing workspace, equipment, and funding for student projects.', '2023-09-01', 'public'),

('Microsoft Student Partners', 'sponsor', 'pending', 'Global Partnerships Team', 'studentspartnerships@microsoft.com', 'microsoft-logo.png', 'https://studentambassadors.microsoft.com', 'Global tech giant offering Azure credits and training resources.', NULL, 'private');

-- ============================================
-- 7. MEMBER_EVENTS (Attendance Tracking)
-- ============================================

INSERT INTO MEMBER_EVENTS (member_id, event_id, attendance_status, feedback_rating, feedback_comment) VALUES
-- Git Workshop (event_id = 6, completed)
(1, 6, 'attended', 5, 'Excellent hands-on session! I can now use Git confidently.'),
(2, 6, 'attended', 4, 'Very informative, would have loved more advanced topics.'),
(5, 6, 'attended', 5, 'Perfect introduction to version control.'),
(6, 6, 'registered', NULL, NULL),
(9, 6, 'attended', 5, 'Great workshop, learned a lot about collaboration.'),

-- Networking Meetup (event_id = 7, completed)
(3, 7, 'attended', 4, 'Met some awesome developers from other departments!'),
(7, 7, 'attended', 5, 'Valuable connections made. Looking forward to collaborations.'),
(10, 7, 'attended', 4, 'Good networking opportunity.'),
(11, 7, 'absent', NULL, NULL),

-- Upcoming Web Dev Workshop (event_id = 1)
(1, 1, 'registered', NULL, NULL),
(2, 1, 'registered', NULL, NULL),
(3, 1, 'registered', NULL, NULL),
(4, 1, 'registered', NULL, NULL),

-- Upcoming Python Bootcamp (event_id = 2)
(2, 2, 'registered', NULL, NULL),
(9, 2, 'registered', NULL, NULL),
(10, 2, 'registered', NULL, NULL),
(18, 2, 'registered', NULL, NULL);

-- ============================================
-- 8. MEMBER_PROJECTS (Project Contributions)
-- ============================================

INSERT INTO MEMBER_PROJECTS (member_id, project_id, role_on_project, contribution_description, join_date, status) VALUES
-- NACOS Dashboard (project_id = 1)
(17, 1, 'Lead Developer', 'Architecture design, backend development, and database management.', '2024-10-01', 'active'),
(5, 1, 'Frontend Developer', 'Building responsive UI components and implementing dashboard pages.', '2024-10-05', 'active'),
(8, 1, 'Database Administrator', 'Database optimization and query performance tuning.', '2024-10-10', 'active'),

-- Smart Campus Navigator (project_id = 2, completed)
(7, 2, 'Lead Mobile Developer', 'Flutter app development and AR integration.', '2024-06-15', 'completed'),
(15, 2, 'Backend Developer', 'API development and Firebase integration.', '2024-06-20', 'completed'),

-- AI Exam Prep Assistant (project_id = 3)
(9, 3, 'Machine Learning Engineer', 'Developing NLP models for question generation.', '2024-08-20', 'active'),
(18, 3, 'Data Scientist', 'Training and evaluating ML models with course datasets.', '2024-08-25', 'active'),
(10, 3, 'Backend Developer', 'Flask API development and model deployment.', '2024-09-01', 'active'),

-- CyberShield (project_id = 4, completed)
(4, 4, 'Security Researcher', 'Vulnerability scanning algorithms and penetration testing.', '2024-05-10', 'completed'),
(12, 4, 'Python Developer', 'Tool development and integration with security frameworks.', '2024-05-15', 'completed'),

-- E-Learning Platform (project_id = 5)
(8, 5, 'Backend Lead', 'Laravel backend architecture and API development.', '2024-07-01', 'active'),
(6, 5, 'UI/UX Designer', 'Interface design and user experience optimization.', '2024-07-05', 'active'),

-- BlockVote (project_id = 6, completed)
(13, 6, 'Blockchain Developer', 'Smart contract development and Web3 integration.', '2024-03-15', 'completed'),
(5, 6, 'Frontend Developer', 'React UI for voting interface.', '2024-03-20', 'completed');

-- ============================================
-- 9. DOCUMENTS (Administrative Files)
-- ============================================
-- Note: These are placeholders. Actual files stored outside web root.

INSERT INTO DOCUMENTS (file_name, file_path, doc_type, description, uploaded_by, academic_session) VALUES
('2024-2025-Handover-Notes.pdf', '/secure/documents/handover_2024_2025.pdf', 'handover', 'Executive handover notes for 2024/2025 academic session.', 1, '2024/2025'),
('NACOS-Constitution-2024.pdf', '/secure/documents/constitution_2024.pdf', 'policy', 'Updated NACOS chapter constitution and bylaws.', 1, '2024/2025'),
('Budget-Report-Q3-2024.xlsx', '/secure/documents/budget_q3_2024.xlsx', 'financial', 'Third quarter financial report and expenditure breakdown.', 2, '2024/2025'),
('Executive-Meeting-Minutes-Oct-2024.docx', '/secure/documents/meeting_oct_2024.docx', 'meeting_minutes', 'Minutes from October 2024 executive meeting.', 3, '2024/2025'),
('Membership-Drive-Report-2024.pdf', '/secure/documents/membership_report_2024.pdf', 'report', 'Comprehensive report on 2024 membership registration drive.', 2, '2024/2025');

-- ============================================
-- SEED DATA COMPLETE
-- ============================================
-- Summary:
-- - 4 Administrators (password: Admin@2025)
-- - 20 Members (across all levels)
-- - 7 Projects
-- - 7 Events
-- - 6 Resources
-- - 5 Partners
-- - Multiple attendance and contribution records
-- - 5 Administrative documents
-- ============================================
