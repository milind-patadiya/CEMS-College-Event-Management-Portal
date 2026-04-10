SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

DROP DATABASE IF EXISTS cems_db;
CREATE DATABASE cems_db DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE cems_db;

CREATE TABLE students (
    student_id    INT AUTO_INCREMENT PRIMARY KEY,
    full_name     VARCHAR(150) NOT NULL,
    enrollment_no VARCHAR(11)  NOT NULL UNIQUE,
    email         VARCHAR(150) NOT NULL UNIQUE,
    phone         VARCHAR(15)  DEFAULT NULL,
    password      VARCHAR(255) NOT NULL,
    created_at    TIMESTAMP    DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE faculty (
    faculty_id  INT AUTO_INCREMENT PRIMARY KEY,
    full_name   VARCHAR(150) NOT NULL,
    email       VARCHAR(150) NOT NULL UNIQUE,
    department  VARCHAR(100) DEFAULT NULL,
    username    VARCHAR(80)  NOT NULL UNIQUE,
    password    VARCHAR(255) NOT NULL,
    created_at  TIMESTAMP    DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE admins (
    admin_id   INT AUTO_INCREMENT PRIMARY KEY,
    full_name  VARCHAR(150) NOT NULL,
    username   VARCHAR(80)  NOT NULL UNIQUE,
    password   VARCHAR(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE events (
    event_id         INT AUTO_INCREMENT PRIMARY KEY,
    event_name       VARCHAR(200) NOT NULL,
    description      TEXT         DEFAULT NULL,
    venue            VARCHAR(200) DEFAULT NULL,
    event_date       DATE         NOT NULL,
    event_time       TIME         DEFAULT NULL,
    capacity         INT          DEFAULT NULL,
    status           ENUM('Pending','Approved','Rejected') NOT NULL DEFAULT 'Approved',
    rejection_reason VARCHAR(500) DEFAULT NULL,
    created_by       INT          NOT NULL,
    created_at       TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES faculty(faculty_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE registrations (
    registration_id   INT AUTO_INCREMENT PRIMARY KEY,
    student_id        INT NOT NULL,
    event_id          INT NOT NULL,
    registration_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_reg (student_id, event_id),
    FOREIGN KEY (student_id) REFERENCES students(student_id) ON DELETE CASCADE,
    FOREIGN KEY (event_id)   REFERENCES events(event_id)    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE attendance (
    attendance_id INT AUTO_INCREMENT PRIMARY KEY,
    student_id    INT NOT NULL,
    event_id      INT NOT NULL,
    status        ENUM('Pending','Present','Absent') NOT NULL DEFAULT 'Absent',
    marked_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_att (student_id, event_id),
    FOREIGN KEY (student_id) REFERENCES students(student_id) ON DELETE CASCADE,
    FOREIGN KEY (event_id)   REFERENCES events(event_id)    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE certificates (
    certificate_id  INT AUTO_INCREMENT PRIMARY KEY,
    event_id        INT NOT NULL,
    student_id      INT NOT NULL,
    certificate_url VARCHAR(500) DEFAULT NULL,
    issued_date     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_cert (student_id, event_id),
    FOREIGN KEY (student_id) REFERENCES students(student_id) ON DELETE CASCADE,
    FOREIGN KEY (event_id)   REFERENCES events(event_id)    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE announcements (
    announcement_id INT AUTO_INCREMENT PRIMARY KEY,
    title           VARCHAR(200) NOT NULL,
    content         TEXT         NOT NULL,
    event_id        INT          DEFAULT NULL,
    posted_by       INT          NOT NULL,
    posted_by_role  ENUM('faculty','admin') NOT NULL DEFAULT 'faculty',
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (event_id) REFERENCES events(event_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO admins (full_name, username, password) VALUES
('Administrator', 'admin', '$2y$12$ba4Bgo4aK.0Jr0SLrsH5Mu4CUamRuxUZco9IcDrE2XbgBZI2PvxRq');

INSERT INTO faculty (full_name, email, department, username, password) VALUES
('Prof. Amit Shah', 'amit@cems.edu', 'Computer Science', 'amit_shah', '$2y$12$0nQxHGbmdmbqzcnABrcfSulPOWM7dJVG/iscUcPOBZL/s5iA2jNpa');

INSERT INTO faculty (full_name, email, department, username, password) VALUES
('Dr. Priya Mehta', 'priya@cems.edu', 'Information Technology', 'priya_mehta', '$2y$12$Nygv8TtY05h6F56SUGPHreICRDxCqdrxIDbE7OSI9RGorBqedmEDG');

INSERT INTO events (event_name, description, venue, event_date, event_time, capacity, status, created_by) VALUES
('Tech Symposium 2026', 'Annual technical symposium featuring guest lectures, paper presentations and workshops on emerging technologies.', 'Main Auditorium', '2026-04-15', '10:00:00', 200, 'Approved', 1),
('Cultural Fest — Utsav', 'Annual cultural festival with dance, music, drama and art competitions. Open to all students.', 'College Ground', '2026-04-20', '09:00:00', 500, 'Approved', 1),
('Workshop: Web Development', 'Hands-on workshop covering HTML, CSS, JavaScript and PHP basics for beginners.', 'Computer Lab 3', '2026-04-25', '11:00:00', 40, 'Approved', 2),
('Hackathon 2026', 'A 24-hour coding competition. Form teams of 2-4 and solve real-world problems.', 'Innovation Lab', '2026-05-10', '08:00:00', 100, 'Approved', 1),
('Sports Day 2026', 'Inter-department sports competition — cricket, football, badminton and athletics.', 'Sports Complex', '2026-05-15', '08:30:00', 300, 'Approved', 2),
('AI & ML Seminar', 'Expert seminar on the latest trends in Artificial Intelligence and Machine Learning.', 'Seminar Hall B', '2026-05-20', '14:00:00', 80, 'Approved', 1);

INSERT INTO announcements (title, content, event_id, posted_by, posted_by_role) VALUES
('Tech Symposium — Registration Open', 'Registrations for the Annual Tech Symposium are now open. Last date to register is 10th April 2026. All CS and IT students are encouraged to participate.', 1, 1, 'faculty'),
('Web Dev Workshop — Limited Seats', 'The Web Development Workshop has only 40 seats. Register early to secure your spot. Bring your own laptop.', 3, 2, 'faculty'),
('Hackathon 2026 — Team Registration', 'Form your teams now for Hackathon 2026. Teams of 2 to 4 members. Problem statements will be released on the day of the event.', 4, 1, 'faculty'),
('Sports Day Volunteers Needed', 'We are looking for volunteers to help organize Sports Day. Interested students can contact the Sports Committee.', 5, 2, 'faculty'),
('Campus Wi-Fi Maintenance', 'Campus Wi-Fi will be down for maintenance on Sunday from 10 PM to 2 AM. Plan accordingly.', NULL, 1, 'faculty');



CREATE TABLE otp_rate_limit (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    email_hash VARCHAR(64) NOT NULL,
    ip_hash    VARCHAR(64) NOT NULL,
    sent_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_email (email_hash),
    INDEX idx_ip    (ip_hash),
    INDEX idx_sent  (sent_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

COMMIT;
