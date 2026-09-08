-- ============================================================
-- QuizCraft Database Schema
-- Steps:
-- 1. Open phpMyAdmin → http://localhost/phpmyadmin
-- 2. Click "Import" → Select this file → Click "Go"
-- ============================================================

CREATE DATABASE IF NOT EXISTS quizcraft
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE quizcraft;

-- ------------------------------------------------------------
-- Table 1: quizzes
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS quizzes (
    id            INT          NOT NULL AUTO_INCREMENT,
    title         VARCHAR(255) NOT NULL,
    subject       VARCHAR(255) NOT NULL,
    total_marks   INT          NOT NULL DEFAULT 0,
    question      INT          NOT NULL DEFAULT 0 COMMENT 'total number of questions',
    quiz_result   INT          NOT NULL DEFAULT 0 COMMENT 'number of attempts / results',
    created_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- Table 2: questions
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS questions (
    id              INT          NOT NULL AUTO_INCREMENT,
    quiz_id         INT          NOT NULL,
    question_text   TEXT         NOT NULL,
    question_type   ENUM('MCQ','TF') NOT NULL DEFAULT 'MCQ',
    option_a        VARCHAR(500) DEFAULT NULL,
    option_b        VARCHAR(500) DEFAULT NULL,
    option_c        VARCHAR(500) DEFAULT NULL,
    option_d        VARCHAR(500) DEFAULT NULL,
    correct_answer  VARCHAR(10)  NOT NULL COMMENT 'A / B / C / D  or  True / False',
    marks           INT          NOT NULL DEFAULT 1,
    PRIMARY KEY (id),
    CONSTRAINT fk_questions_quiz
        FOREIGN KEY (quiz_id) REFERENCES quizzes(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- Table 3: quiz_results
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS quiz_results (
    id            INT          NOT NULL AUTO_INCREMENT,
    quiz_id       INT          NOT NULL,
    student_name  VARCHAR(255) NOT NULL,
    score         INT          NOT NULL DEFAULT 0,
    submitted_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    CONSTRAINT fk_results_quiz
        FOREIGN KEY (quiz_id) REFERENCES quizzes(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
