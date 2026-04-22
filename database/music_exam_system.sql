CREATE DATABASE IF NOT EXISTS music_exam_system CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE music_exam_system;

SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS answers;
DROP TABLE IF EXISTS attempts;
DROP TABLE IF EXISTS exam_questions;
DROP TABLE IF EXISTS question_options;
DROP TABLE IF EXISTS questions;
DROP TABLE IF EXISTS exams;
DROP TABLE IF EXISTS categories;
DROP TABLE IF EXISTS users;

SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'student') NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE
);

CREATE TABLE questions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    question_text TEXT NOT NULL,
    image_path VARCHAR(255) DEFAULT NULL,
    category_id INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_questions_category
        FOREIGN KEY (category_id) REFERENCES categories(id)
        ON DELETE SET NULL ON UPDATE CASCADE
);

CREATE TABLE question_options (
    id INT AUTO_INCREMENT PRIMARY KEY,
    question_id INT NOT NULL,
    option_text VARCHAR(255) NOT NULL,
    is_correct TINYINT(1) NOT NULL DEFAULT 0,
    CONSTRAINT fk_options_question
        FOREIGN KEY (question_id) REFERENCES questions(id)
        ON DELETE CASCADE ON UPDATE CASCADE
);

CREATE TABLE exams (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(150) NOT NULL,
    duration_minutes INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE exam_questions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    exam_id INT NOT NULL,
    question_id INT NOT NULL,
    UNIQUE KEY unique_exam_question (exam_id, question_id),
    CONSTRAINT fk_exam_questions_exam
        FOREIGN KEY (exam_id) REFERENCES exams(id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_exam_questions_question
        FOREIGN KEY (question_id) REFERENCES questions(id)
        ON DELETE CASCADE ON UPDATE CASCADE
);

CREATE TABLE attempts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    exam_id INT NOT NULL,
    score DECIMAL(5,2) DEFAULT 0,
    start_time DATETIME NOT NULL,
    end_time DATETIME DEFAULT NULL,
    CONSTRAINT fk_attempts_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_attempts_exam
        FOREIGN KEY (exam_id) REFERENCES exams(id)
        ON DELETE CASCADE ON UPDATE CASCADE
);

CREATE TABLE answers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    attempt_id INT NOT NULL,
    question_id INT NOT NULL,
    selected_option_id INT DEFAULT NULL,
    is_correct TINYINT(1) NOT NULL DEFAULT 0,
    CONSTRAINT fk_answers_attempt
        FOREIGN KEY (attempt_id) REFERENCES attempts(id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_answers_question
        FOREIGN KEY (question_id) REFERENCES questions(id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_answers_option
        FOREIGN KEY (selected_option_id) REFERENCES question_options(id)
        ON DELETE SET NULL ON UPDATE CASCADE
);

INSERT INTO categories (name) VALUES
('Audio Fundamentals'),
('MIDI'),
('Sound Synthesis'),
('Digital Signal Processing');

-- Password for both sample users: 123456
INSERT INTO users (name, email, password, role) VALUES
('Professor Admin', 'admin@university.gr', '$2y$10$1oN9sY0TtM3D1C8tBf6F0e6wzjXvZ0m0m4Lx6b3DXvQqWfYvM7Yza', 'admin'),
('Student Demo', 'student@university.gr', '$2y$10$1oN9sY0TtM3D1C8tBf6F0e6wzjXvZ0m0m4Lx6b3DXvQqWfYvM7Yza', 'student');

INSERT INTO exams (title, duration_minutes) VALUES
('Music Informatics - Midterm', 20);

INSERT INTO questions (question_text, image_path, category_id) VALUES
('What does MIDI primarily transmit?', NULL, 2),
('Which sampling rate is standard for audio CDs?', NULL, 1);

INSERT INTO question_options (question_id, option_text, is_correct) VALUES
(1, 'Audio waveform data', 0),
(1, 'Performance/event data', 1),
(1, 'Only image data', 0),
(1, 'Speaker calibration data', 0),

(2, '22,050 Hz', 0),
(2, '44,100 Hz', 1),
(2, '48,000 Hz', 0),
(2, '96,000 Hz', 0);

INSERT INTO exam_questions (exam_id, question_id) VALUES
(1, 1),
(1, 2);