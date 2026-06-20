-- ============================================================
--  SmartAttend Database Schema
--  Run this file in MySQL 8.0 command line:
--    mysql -u root -p
--    source C:/xampp/htdocs/smartattend-backend/database.sql
-- ============================================================

-- Step 1: Create and select the database
CREATE DATABASE IF NOT EXISTS smartattend;
USE smartattend;

-- ============================================================
-- TABLE 1: departments
-- Stores each college department + its HOD
-- ============================================================
CREATE TABLE departments (
    dept_id      VARCHAR(10)  PRIMARY KEY,          -- e.g. 'CSE', 'ECE'
    dept_name    VARCHAR(100) NOT NULL,
    hod_name     VARCHAR(100),
    hod_email    VARCHAR(100),
    created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ============================================================
-- TABLE 2: mentors
-- One mentor handles multiple students
-- ============================================================
CREATE TABLE mentors (
    mentor_id    INT AUTO_INCREMENT PRIMARY KEY,
    name         VARCHAR(100) NOT NULL,
    email        VARCHAR(100) NOT NULL UNIQUE,
    password     VARCHAR(255) NOT NULL,             -- bcrypt hash
    dept_id      VARCHAR(10),
    phone        VARCHAR(15),
    created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (dept_id) REFERENCES departments(dept_id)
);

-- ============================================================
-- TABLE 3: hods
-- One HOD per department
-- ============================================================
CREATE TABLE hods (
    hod_id       INT AUTO_INCREMENT PRIMARY KEY,
    name         VARCHAR(100) NOT NULL,
    email        VARCHAR(100) NOT NULL UNIQUE,
    password     VARCHAR(255) NOT NULL,
    dept_id      VARCHAR(10),
    created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (dept_id) REFERENCES departments(dept_id)
);

-- ============================================================
-- TABLE 4: students
-- Each student is linked to one mentor and one department
-- ============================================================
CREATE TABLE students (
    student_id   INT AUTO_INCREMENT PRIMARY KEY,
    usn          VARCHAR(20) NOT NULL UNIQUE,       -- e.g. 4NM21CS042
    name         VARCHAR(100) NOT NULL,
    email        VARCHAR(100) NOT NULL UNIQUE,
    password     VARCHAR(255) NOT NULL,
    dept_id      VARCHAR(10),
    mentor_id    INT,                               -- auto-assigned on login
    semester     INT,
    section      VARCHAR(5),
    phone        VARCHAR(15),
    created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (dept_id)   REFERENCES departments(dept_id),
    FOREIGN KEY (mentor_id) REFERENCES mentors(mentor_id)
);

-- ============================================================
-- TABLE 5: leave_requests
-- One row per leave application submitted by a student
-- Status flow:
--   pending_mentor → mentor_approved → hod_approved
--   → proof_uploaded → mentor_verified → hod_verified
--   → attendance_updated   OR   rejected
-- ============================================================
CREATE TABLE leave_requests (
    req_id              INT AUTO_INCREMENT PRIMARY KEY,
    req_code            VARCHAR(15) NOT NULL UNIQUE,  -- e.g. SA-2024
    student_id          INT NOT NULL,
    mentor_id           INT NOT NULL,
    hod_id              INT,
    leave_start_date    DATE NOT NULL,
    leave_end_date      DATE NOT NULL,
    reason              VARCHAR(255) NOT NULL,
    category            ENUM(
                            'hackathon',
                            'sports_cultural',
                            'medical',
                            'personal',
                            'intercollege',
                            'other'
                        ) NOT NULL,
    description         TEXT,
    permission_file     VARCHAR(255),               -- path to uploaded file
    status              ENUM(
                            'pending_mentor',
                            'mentor_approved',
                            'hod_approved',
                            'proof_uploaded',
                            'mentor_verified',
                            'hod_verified',
                            'attendance_updated',
                            'rejected'
                        ) DEFAULT 'pending_mentor',
    rejection_reason    TEXT,
    rejected_by         ENUM('mentor','hod'),
    proof_deadline      DATE,                       -- set after hod_approved
    created_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(student_id),
    FOREIGN KEY (mentor_id)  REFERENCES mentors(mentor_id),
    FOREIGN KEY (hod_id)     REFERENCES hods(hod_id)
);

-- ============================================================
-- TABLE 6: leave_subjects
-- Each leave request can have multiple missed classes
-- One row per subject per day
-- ============================================================
CREATE TABLE leave_subjects (
    subject_id      INT AUTO_INCREMENT PRIMARY KEY,
    req_id          INT NOT NULL,
    subject_name    VARCHAR(100) NOT NULL,
    faculty_email   VARCHAR(100) NOT NULL,
    class_date      DATE NOT NULL,
    notified        TINYINT(1) DEFAULT 0,           -- 1 = faculty emailed
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (req_id) REFERENCES leave_requests(req_id) ON DELETE CASCADE
);

-- ============================================================
-- TABLE 7: proof_documents
-- Uploaded proof after provisional approval
-- ============================================================
CREATE TABLE proof_documents (
    proof_id        INT AUTO_INCREMENT PRIMARY KEY,
    req_id          INT NOT NULL UNIQUE,
    proof_type      ENUM(
                        'certificate',
                        'event_id',
                        'medical_cert',
                        'doctors_letter',
                        'photos',
                        'other'
                    ) NOT NULL,
    file_path       VARCHAR(255) NOT NULL,
    description     TEXT,
    uploaded_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    mentor_verified TINYINT(1) DEFAULT 0,
    hod_verified    TINYINT(1) DEFAULT 0,
    FOREIGN KEY (req_id) REFERENCES leave_requests(req_id)
);

-- ============================================================
-- TABLE 8: notifications
-- In-app notifications for all three roles
-- ============================================================
CREATE TABLE notifications (
    notif_id        INT AUTO_INCREMENT PRIMARY KEY,
    user_type       ENUM('student','mentor','hod') NOT NULL,
    user_id         INT NOT NULL,
    req_id          INT,
    title           VARCHAR(255) NOT NULL,
    message         TEXT NOT NULL,
    is_read         TINYINT(1) DEFAULT 0,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (req_id) REFERENCES leave_requests(req_id) ON DELETE SET NULL
);

-- ============================================================
-- TABLE 9: xmpp_messages
-- Stores XMPP chat messages between student ↔ mentor/hod
-- ============================================================
CREATE TABLE xmpp_messages (
    msg_id          INT AUTO_INCREMENT PRIMARY KEY,
    req_id          INT,                            -- linked to leave request
    sender_jid      VARCHAR(150) NOT NULL,          -- XMPP JID e.g. arjun@college.edu
    receiver_jid    VARCHAR(150) NOT NULL,
    message         TEXT NOT NULL,
    msg_type        ENUM('chat','notification') DEFAULT 'chat',
    delivered       TINYINT(1) DEFAULT 0,
    sent_at         TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (req_id) REFERENCES leave_requests(req_id) ON DELETE SET NULL
);

-- ============================================================
-- TABLE 10: sessions
-- PHP session tracking per user
-- ============================================================
CREATE TABLE sessions (
    session_id      VARCHAR(128) PRIMARY KEY,
    user_type       ENUM('student','mentor','hod') NOT NULL,
    user_id         INT NOT NULL,
    ip_address      VARCHAR(45),
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at      TIMESTAMP
);

-- ============================================================
-- SAMPLE DATA — departments, mentors, HOD, students
-- ============================================================

INSERT INTO departments (dept_id, dept_name, hod_name, hod_email) VALUES
('CSE', 'Computer Science & Engineering', 'Prof. Ramesh K', 'ramesh.hod@college.edu'),
('ECE', 'Electronics & Communication',   'Prof. Sunita M', 'sunita.hod@college.edu'),
('ME',  'Mechanical Engineering',         'Prof. Vivek R',  'vivek.hod@college.edu');

-- ============================================================
-- IMPORTANT: Passwords are inserted via generate_passwords.php
-- Run that file ONCE in your browser after importing this SQL:
--   http://localhost/smartattend-backend/generate_passwords.php
-- It will insert all sample users with correct bcrypt hashes.
-- Default password for all users: Password@123
-- ============================================================
