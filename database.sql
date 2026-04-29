CREATE DATABASE IF NOT EXISTS `asfes`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE `asfes`;

CREATE TABLE IF NOT EXISTS users (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150) NOT NULL,
  email VARCHAR(190) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  role VARCHAR(50) NOT NULL,
  department VARCHAR(150) NOT NULL,
  student_code VARCHAR(50) DEFAULT NULL,
  created_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS courses (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(50) NOT NULL,
  title VARCHAR(200) NOT NULL,
  instructor_id INT UNSIGNED NOT NULL,
  department VARCHAR(150) NOT NULL,
  semester VARCHAR(50) NOT NULL,
  status VARCHAR(50) NOT NULL DEFAULT 'Active',
  CONSTRAINT fk_courses_instructor
    FOREIGN KEY (instructor_id) REFERENCES users (id)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS feedback (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  student_id INT UNSIGNED NOT NULL,
  course_id INT UNSIGNED DEFAULT NULL,
  category VARCHAR(50) NOT NULL,
  target_role VARCHAR(50) NOT NULL,
  subject VARCHAR(255) NOT NULL,
  message TEXT NOT NULL,
  is_anonymous TINYINT(1) NOT NULL DEFAULT 1,
  severity VARCHAR(20) NOT NULL DEFAULT 'Medium',
  status VARCHAR(20) NOT NULL DEFAULT 'Submitted',
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  CONSTRAINT fk_feedback_student
    FOREIGN KEY (student_id) REFERENCES users (id)
    ON DELETE CASCADE,
  CONSTRAINT fk_feedback_course
    FOREIGN KEY (course_id) REFERENCES courses (id)
    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS responses (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  feedback_id INT UNSIGNED NOT NULL,
  responder_id INT UNSIGNED NOT NULL,
  message TEXT NOT NULL,
  status VARCHAR(20) NOT NULL,
  created_at DATETIME NOT NULL,
  CONSTRAINT fk_responses_feedback
    FOREIGN KEY (feedback_id) REFERENCES feedback (id)
    ON DELETE CASCADE,
  CONSTRAINT fk_responses_responder
    FOREIGN KEY (responder_id) REFERENCES users (id)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO users (id, name, email, password_hash, role, department, student_code, created_at) VALUES
  (1, 'Amanuel Getachew', 'student@astu.edu', '$2y$12$TEgOWetnk67RlvTsmYBjHOIfRMjFtLJSXaIZiD2o4zFHOl7tDdqf.', 'student', 'Computer Science', 'STU-48291', '2026-04-29 09:00:00'),
  (2, 'Dr. Abebech Alemu', 'instructor@astu.edu', '$2y$12$TEgOWetnk67RlvTsmYBjHOIfRMjFtLJSXaIZiD2o4zFHOl7tDdqf.', 'instructor', 'Computer Science', NULL, '2026-04-29 09:00:00'),
  (3, 'Dr. Tesfaye Belachew', 'department@astu.edu', '$2y$12$TEgOWetnk67RlvTsmYBjHOIfRMjFtLJSXaIZiD2o4zFHOl7tDdqf.', 'department', 'Computer Science', NULL, '2026-04-29 09:00:00'),
  (4, 'Ms. Muna Mohammed', 'studentaffairs@astu.edu', '$2y$12$TEgOWetnk67RlvTsmYBjHOIfRMjFtLJSXaIZiD2o4zFHOl7tDdqf.', 'student_affairs', 'Student Affairs', NULL, '2026-04-29 09:00:00'),
  (5, 'Mr. Dawit Solomon', 'admin@astu.edu', '$2y$12$TEgOWetnk67RlvTsmYBjHOIfRMjFtLJSXaIZiD2o4zFHOl7tDdqf.', 'admin', 'Academic Directorate', NULL, '2026-04-29 09:00:00');

INSERT INTO courses (id, code, title, instructor_id, department, semester, status) VALUES
  (1, 'CS101', 'Introduction to Computer Science', 2, 'Computer Science', 'Fall 2024', 'Active'),
  (2, 'CS204', 'Database Systems', 2, 'Computer Science', 'Fall 2024', 'Active'),
  (3, 'CS305', 'Software Engineering Capstone', 2, 'Computer Science', 'Spring 2025', 'Active'),
  (4, 'CS312', 'Human Computer Interaction', 2, 'Computer Science', 'Spring 2025', 'Active');

INSERT INTO feedback (
  id, student_id, course_id, category, target_role, subject, message, is_anonymous, severity, status, created_at, updated_at
) VALUES
  (1, 1, 1, 'instructor', 'instructor', 'Lecture pacing and explanation clarity', 'The explanations are strong, but the pace in the second half of the lecture sometimes moves too quickly for revision notes.', 0, 'Medium', 'Seen', '2026-04-23 09:00:00', '2026-04-29 09:00:00'),
  (2, 1, 2, 'assessment', 'department', 'Assessment fairness and workload', 'The assignment deadlines for the course are clustered too tightly around the midterm period.', 0, 'High', 'Responded', '2026-04-21 09:00:00', '2026-04-29 09:00:00'),
  (3, 1, NULL, 'harassment', 'student_affairs', 'Confidential student support request', 'I want to report a sensitive issue and request a private follow-up from Student Affairs only.', 1, 'High', 'Submitted', '2026-04-27 09:00:00', '2026-04-29 09:00:00'),
  (4, 1, 1, 'course', 'instructor', 'Course materials and examples', 'The tutorials are helpful, especially the worked examples before quizzes.', 1, 'Low', 'Closed', '2026-04-18 09:00:00', '2026-04-29 09:00:00');

INSERT INTO responses (id, feedback_id, responder_id, message, status, created_at) VALUES
  (1, 2, 3, 'The department has reviewed the assessment schedule and will rebalance the assignment windows for the next cycle.', 'Responded', '2026-04-28 09:00:00'),
  (2, 4, 2, 'Thank you for the encouraging feedback. We will keep the worked examples in future lecture notes.', 'Closed', '2026-04-26 09:00:00');
