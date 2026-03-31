-- Smart Medicine Reminder App - Database Schema
-- Run this SQL in your MySQL/phpMyAdmin

CREATE DATABASE IF NOT EXISTS medicine_reminder;
USE medicine_reminder;

-- Users table
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(150) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    phone VARCHAR(20),
    date_of_birth DATE,
    gender ENUM('male','female','other'),
    avatar_color VARCHAR(7) DEFAULT '#4ecdc4',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Medicines table
CREATE TABLE medicines (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    dosage VARCHAR(50) NOT NULL,
    medicine_type ENUM('tablet','capsule','syrup','injection','drops','inhaler','cream','other') DEFAULT 'tablet',
    frequency ENUM('once_daily','twice_daily','thrice_daily','every_4h','every_6h','every_8h','weekly','custom') NOT NULL,
    times JSON NOT NULL COMMENT 'Array of times e.g. ["08:00","14:00","20:00"]',
    start_date DATE NOT NULL,
    end_date DATE,
    instructions TEXT,
    color VARCHAR(7) DEFAULT '#4ecdc4',
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Reminder logs table (tracks taken/skipped/missed)
CREATE TABLE reminder_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    medicine_id INT NOT NULL,
    user_id INT NOT NULL,
    scheduled_time DATETIME NOT NULL,
    status ENUM('taken','skipped','missed') NOT NULL,
    taken_at DATETIME,
    notes VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (medicine_id) REFERENCES medicines(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Emergency contacts table
CREATE TABLE emergency_contacts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    relation VARCHAR(50),
    phone VARCHAR(20) NOT NULL,
    email VARCHAR(150),
    notify_on_missed TINYINT(1) DEFAULT 0,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Health notes / diary
CREATE TABLE health_notes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    note TEXT NOT NULL,
    mood ENUM('great','good','okay','bad','terrible') DEFAULT 'okay',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Sample data (optional - for testing)
-- INSERT INTO users (full_name, email, password, phone) VALUES 
-- ('John Doe', 'john@example.com', '$2y$10$...hashed_password...', '+1234567890');
