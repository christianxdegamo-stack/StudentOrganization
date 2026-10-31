-- =====================================================================
-- OrgSync: Student Organization Event & Membership Management System
-- Database script for MySQL / MariaDB (XAMPP + phpMyAdmin)
-- Import this file in phpMyAdmin (Import tab). It creates the database
-- "orgsync_db", all tables, and demo records.
-- WARNING: re-importing this file RESETS all data (tables are dropped).
-- =====================================================================

CREATE DATABASE IF NOT EXISTS orgsync_db
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE orgsync_db;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS activity_logs;
DROP TABLE IF EXISTS event_attendance;
DROP TABLE IF EXISTS event_registrations;
DROP TABLE IF EXISTS events;
DROP TABLE IF EXISTS memberships;
DROP TABLE IF EXISTS organizations;
DROP TABLE IF EXISTS students;
DROP TABLE IF EXISTS users;
SET FOREIGN_KEY_CHECKS = 1;

-- ---------------------------------------------------------------------
-- 1. students  (from proposal; unchanged)
-- ---------------------------------------------------------------------
CREATE TABLE students (
  student_id    VARCHAR(20)  NOT NULL,
  first_name    VARCHAR(50)  NOT NULL,
  last_name     VARCHAR(50)  NOT NULL,
  email         VARCHAR(100) NOT NULL,
  course        VARCHAR(50)  NOT NULL,
  year_level    INT          NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  PRIMARY KEY (student_id),
  UNIQUE KEY uq_students_email (email),
  KEY idx_students_course (course),
  KEY idx_students_year (year_level),
  CONSTRAINT chk_students_year CHECK (year_level BETWEEN 1 AND 5)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 2. users  (NEW) - login accounts for Super Admins (School Affairs).
--    Students and Club Officers log in with their students record;
--    an officer is a student whose membership has member_role = 'Officer'.
-- ---------------------------------------------------------------------
CREATE TABLE users (
  user_id       INT          NOT NULL AUTO_INCREMENT,
  username      VARCHAR(50)  NOT NULL,
  full_name     VARCHAR(100) NOT NULL,
  email         VARCHAR(100) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  role          VARCHAR(20)  NOT NULL DEFAULT 'super_admin',
  is_active     TINYINT(1)   NOT NULL DEFAULT 1,
  created_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (user_id),
  UNIQUE KEY uq_users_username (username),
  UNIQUE KEY uq_users_email (email)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 3. organizations  (from proposal; unchanged)
-- ---------------------------------------------------------------------
CREATE TABLE organizations (
  org_id       INT          NOT NULL AUTO_INCREMENT,
  org_name     VARCHAR(100) NOT NULL,
  category     VARCHAR(50)  NOT NULL,
  description  TEXT         NULL,
  date_created DATE         NOT NULL,
  PRIMARY KEY (org_id),
  UNIQUE KEY uq_org_name (org_name),
  KEY idx_org_category (category),
  CONSTRAINT chk_org_category CHECK (category IN ('Academic','Non-Academic','Cultural','Sports'))
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 4. memberships  (NEW - proposal describes membership data but had no table)
--    status: Pending/Rejected were added so students can APPLY and officers
--    can approve/reject. Proposal values Active/Inactive are kept.
-- ---------------------------------------------------------------------
CREATE TABLE memberships (
  membership_id INT         NOT NULL AUTO_INCREMENT,
  student_id    VARCHAR(20) NOT NULL,
  org_id        INT         NOT NULL,
  date_joined   DATE        NOT NULL,
  status        VARCHAR(20) NOT NULL DEFAULT 'Pending',
  member_role   VARCHAR(20) NOT NULL DEFAULT 'Member',
  PRIMARY KEY (membership_id),
  UNIQUE KEY uq_membership (student_id, org_id),   -- prevents duplicate memberships
  KEY idx_mem_org (org_id),
  KEY idx_mem_status (status),
  CONSTRAINT fk_mem_student FOREIGN KEY (student_id) REFERENCES students(student_id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_mem_org FOREIGN KEY (org_id) REFERENCES organizations(org_id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT chk_mem_status CHECK (status IN ('Pending','Active','Inactive','Rejected')),
  CONSTRAINT chk_mem_role CHECK (member_role IN ('Officer','Member'))
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 5. events  (proposal + NEW event_description column)
-- ---------------------------------------------------------------------
CREATE TABLE events (
  event_id          INT          NOT NULL AUTO_INCREMENT,
  org_id            INT          NOT NULL,
  event_title       VARCHAR(150) NOT NULL,
  event_description TEXT         NULL,
  event_date        DATETIME     NOT NULL,
  venue             VARCHAR(100) NOT NULL,
  capacity          INT          NOT NULL DEFAULT 100,
  PRIMARY KEY (event_id),
  KEY idx_event_org (org_id),
  KEY idx_event_date (event_date),
  CONSTRAINT fk_event_org FOREIGN KEY (org_id) REFERENCES organizations(org_id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT chk_event_capacity CHECK (capacity > 0)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 6. event_registrations  (NEW - sign-up is separate from actual attendance)
-- ---------------------------------------------------------------------
CREATE TABLE event_registrations (
  registration_id     INT         NOT NULL AUTO_INCREMENT,
  event_id            INT         NOT NULL,
  student_id          VARCHAR(20) NOT NULL,
  registration_date   TIMESTAMP   NOT NULL DEFAULT CURRENT_TIMESTAMP,
  registration_status VARCHAR(20) NOT NULL DEFAULT 'Registered',
  PRIMARY KEY (registration_id),
  UNIQUE KEY uq_registration (event_id, student_id), -- prevents duplicate registration
  KEY idx_reg_student (student_id),
  CONSTRAINT fk_reg_event FOREIGN KEY (event_id) REFERENCES events(event_id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_reg_student FOREIGN KEY (student_id) REFERENCES students(student_id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT chk_reg_status CHECK (registration_status IN ('Registered','Cancelled'))
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 7. event_attendance  (proposal; default status changed from
--    'Attended' to 'Present' so it matches Present/Excused in the proposal)
-- ---------------------------------------------------------------------
CREATE TABLE event_attendance (
  attendance_id INT         NOT NULL AUTO_INCREMENT,
  event_id      INT         NOT NULL,
  student_id    VARCHAR(20) NOT NULL,
  time_in       TIMESTAMP   NOT NULL DEFAULT CURRENT_TIMESTAMP,
  status        VARCHAR(20) NOT NULL DEFAULT 'Present',
  PRIMARY KEY (attendance_id),
  UNIQUE KEY uq_attendance (event_id, student_id),   -- prevents duplicate attendance
  KEY idx_att_student (student_id),
  CONSTRAINT fk_att_event FOREIGN KEY (event_id) REFERENCES events(event_id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_att_student FOREIGN KEY (student_id) REFERENCES students(student_id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT chk_att_status CHECK (status IN ('Present','Excused'))
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 8. activity_logs  (NEW) - audit trail. user_id holds a student ID or
--    "ADMIN-<n>" for Super Admins, so it is intentionally not a foreign key.
-- ---------------------------------------------------------------------
CREATE TABLE activity_logs (
  log_id     INT          NOT NULL AUTO_INCREMENT,
  user_id    VARCHAR(30)  NOT NULL,
  activity   VARCHAR(255) NOT NULL,
  created_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (log_id),
  KEY idx_log_created (created_at),
  KEY idx_log_user (user_id)
) ENGINE=InnoDB;

-- =====================================================================
-- DEMO DATA  (all names are fictional)
-- Demo passwords:  Admin@123  /  Officer@123  /  Student@123
-- =====================================================================

INSERT INTO users (username, full_name, email, password_hash) VALUES
('admin', 'School Affairs Administrator', 'admin@orgsync.test',
 '$2y$12$TAPc5uyZK9qyy8XTl7xOAeNg6n41/ABMG.ikvGbmjYDFTucstnck6');

-- Officers (Officer@123): 2022-0101, 2022-0102, 2023-0103
-- Other students (Student@123)
INSERT INTO students (student_id, first_name, last_name, email, course, year_level, password_hash) VALUES
('2022-0101','Maria','Santos','maria.santos@orgsync.test','BSIT',4,'$2y$12$R4xpE0tWCMzaN8Z7mEKw5.m.P2dSTWQwJazfkuFvoVK5m2IYu4pY.'),
('2022-0102','Juan','Dela Cruz','juan.delacruz@orgsync.test','BSBA',4,'$2y$12$R4xpE0tWCMzaN8Z7mEKw5.m.P2dSTWQwJazfkuFvoVK5m2IYu4pY.'),
('2023-0103','Ana','Reyes','ana.reyes@orgsync.test','BSED',3,'$2y$12$R4xpE0tWCMzaN8Z7mEKw5.m.P2dSTWQwJazfkuFvoVK5m2IYu4pY.'),
('2023-0104','Carlo','Mendoza','carlo.mendoza@orgsync.test','BSIT',3,'$2y$12$RyXs8EaZXMbFf/rb3gy1RejMQo5i2Q.p0o6AZEWVfECr9WfBRseOy'),
('2023-0105','Liza','Fernandez','liza.fernandez@orgsync.test','BSBA',3,'$2y$12$RyXs8EaZXMbFf/rb3gy1RejMQo5i2Q.p0o6AZEWVfECr9WfBRseOy'),
('2023-0106','Mark','Villanueva','mark.villanueva@orgsync.test','BSED',3,'$2y$12$RyXs8EaZXMbFf/rb3gy1RejMQo5i2Q.p0o6AZEWVfECr9WfBRseOy'),
('2024-0107','Grace','Navarro','grace.navarro@orgsync.test','BSCS',2,'$2y$12$RyXs8EaZXMbFf/rb3gy1RejMQo5i2Q.p0o6AZEWVfECr9WfBRseOy'),
('2024-0108','Paolo','Ramos','paolo.ramos@orgsync.test','BSIT',2,'$2y$12$RyXs8EaZXMbFf/rb3gy1RejMQo5i2Q.p0o6AZEWVfECr9WfBRseOy'),
('2024-0109','Jenny','Cruz','jenny.cruz@orgsync.test','BSN',2,'$2y$12$RyXs8EaZXMbFf/rb3gy1RejMQo5i2Q.p0o6AZEWVfECr9WfBRseOy'),
('2025-0110','Rico','Aquino','rico.aquino@orgsync.test','BSED',1,'$2y$12$RyXs8EaZXMbFf/rb3gy1RejMQo5i2Q.p0o6AZEWVfECr9WfBRseOy'),
('2025-0111','Tina','Bautista','tina.bautista@orgsync.test','BSN',1,'$2y$12$RyXs8EaZXMbFf/rb3gy1RejMQo5i2Q.p0o6AZEWVfECr9WfBRseOy'),
('2025-0112','Leo','Garcia','leo.garcia@orgsync.test','BSCS',1,'$2y$12$RyXs8EaZXMbFf/rb3gy1RejMQo5i2Q.p0o6AZEWVfECr9WfBRseOy');

INSERT INTO organizations (org_name, category, description, date_created) VALUES
('Computer Society','Academic','A community of students who love programming, networking, and technology.', DATE_SUB(CURDATE(), INTERVAL 900 DAY)),
('Entrepreneurs Guild','Academic','Builds business skills through pitch nights, seminars, and mentoring.', DATE_SUB(CURDATE(), INTERVAL 700 DAY)),
('Cultural Dance Troupe','Cultural','Preserves and performs traditional and modern dances at campus events.', DATE_SUB(CURDATE(), INTERVAL 1200 DAY)),
('Varsity Volleyball Club','Sports','Training, friendly matches, and intramural volleyball tournaments.', DATE_SUB(CURDATE(), INTERVAL 500 DAY)),
('Community Outreach Society','Non-Academic','Organizes feeding programs, tree planting, and other volunteer work.', DATE_SUB(CURDATE(), INTERVAL 800 DAY));

INSERT INTO memberships (student_id, org_id, date_joined, status, member_role) VALUES
('2022-0101',1,DATE_SUB(CURDATE(), INTERVAL 400 DAY),'Active','Officer'),
('2022-0102',1,DATE_SUB(CURDATE(), INTERVAL 300 DAY),'Active','Member'),
('2023-0104',1,DATE_SUB(CURDATE(), INTERVAL 200 DAY),'Active','Member'),
('2025-0110',1,DATE_SUB(CURDATE(), INTERVAL 150 DAY),'Active','Member'),
('2024-0108',1,DATE_SUB(CURDATE(), INTERVAL 2 DAY),'Pending','Member'),
('2022-0102',2,DATE_SUB(CURDATE(), INTERVAL 350 DAY),'Active','Officer'),
('2023-0103',2,DATE_SUB(CURDATE(), INTERVAL 250 DAY),'Active','Member'),
('2023-0105',2,DATE_SUB(CURDATE(), INTERVAL 220 DAY),'Active','Member'),
('2024-0109',2,DATE_SUB(CURDATE(), INTERVAL 180 DAY),'Inactive','Member'),
('2023-0103',3,DATE_SUB(CURDATE(), INTERVAL 500 DAY),'Active','Officer'),
('2023-0106',3,DATE_SUB(CURDATE(), INTERVAL 260 DAY),'Active','Member'),
('2024-0107',3,DATE_SUB(CURDATE(), INTERVAL 120 DAY),'Active','Member'),
('2025-0110',3,DATE_SUB(CURDATE(), INTERVAL 3 DAY),'Pending','Member'),
('2023-0105',4,DATE_SUB(CURDATE(), INTERVAL 140 DAY),'Active','Member'),
('2025-0111',4,DATE_SUB(CURDATE(), INTERVAL 60 DAY),'Active','Member'),
('2025-0112',4,DATE_SUB(CURDATE(), INTERVAL 20 DAY),'Rejected','Member'),
('2022-0101',5,DATE_SUB(CURDATE(), INTERVAL 280 DAY),'Active','Member'),
('2024-0108',5,DATE_SUB(CURDATE(), INTERVAL 90 DAY),'Active','Member'),
('2025-0112',5,DATE_SUB(CURDATE(), INTERVAL 45 DAY),'Active','Member');

INSERT INTO events (org_id, event_title, event_description, event_date, venue, capacity) VALUES
(1,'Python Basics Bootcamp','Hands-on introduction to Python for beginners.', TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 10 DAY),'13:00:00'),'Computer Lab 2',30),
(1,'Intro to Web Development Workshop','Build your first web page with HTML, CSS and PHP.', TIMESTAMP(DATE_ADD(CURDATE(), INTERVAL 7 DAY),'09:00:00'),'Computer Lab 1',40),
(2,'Startup Pitch Night','Student teams pitch business ideas to invited judges. Limited seats!', TIMESTAMP(DATE_ADD(CURDATE(), INTERVAL 10 DAY),'18:00:00'),'Business Hall',3),
(3,'Cultural Night Rehearsal','Open rehearsal for the cultural night program.', TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 5 DAY),'16:00:00'),'Gymnasium',50),
(3,'Foundation Day Dance Showcase','Annual dance showcase for the school foundation day.', TIMESTAMP(DATE_ADD(CURDATE(), INTERVAL 21 DAY),'17:00:00'),'Main Auditorium',100),
(4,'Volleyball Skills Clinic','Beginner-friendly clinic with the varsity coach.', TIMESTAMP(DATE_ADD(CURDATE(), INTERVAL 14 DAY),'15:00:00'),'Covered Court',20),
(5,'Tree Planting Drive','Join us in planting 200 seedlings around the campus.', TIMESTAMP(DATE_ADD(CURDATE(), INTERVAL 30 DAY),'07:30:00'),'Campus Grounds',60),
(5,'Community Feeding Program','Volunteers prepared and served meals to the nearby community.', TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 20 DAY),'10:00:00'),'Barangay Hall',40);

INSERT INTO event_registrations (event_id, student_id, registration_status) VALUES
(1,'2022-0101','Registered'),(1,'2022-0102','Registered'),(1,'2023-0104','Registered'),(1,'2024-0107','Registered'),
(2,'2023-0104','Registered'),(2,'2024-0107','Registered'),(2,'2024-0108','Registered'),
(3,'2022-0102','Registered'),(3,'2023-0103','Registered'),(3,'2023-0105','Registered'),(3,'2023-0106','Cancelled'),
(4,'2023-0103','Registered'),(4,'2023-0106','Registered'),(4,'2024-0107','Registered'),
(5,'2023-0106','Registered'),(5,'2024-0107','Registered'),(5,'2025-0110','Registered'),
(6,'2023-0105','Registered'),(6,'2025-0111','Registered'),
(7,'2022-0101','Registered'),(7,'2024-0108','Registered'),(7,'2025-0112','Registered'),
(8,'2022-0101','Registered'),(8,'2024-0108','Registered'),(8,'2025-0112','Registered');

INSERT INTO event_attendance (event_id, student_id, time_in, status) VALUES
(1,'2022-0101', TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 10 DAY),'12:50:00'),'Present'),
(1,'2022-0102', TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 10 DAY),'13:05:00'),'Present'),
(1,'2023-0104', TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 10 DAY),'13:00:00'),'Excused'),
(1,'2024-0107', TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 10 DAY),'13:10:00'),'Present'),
(4,'2023-0103', TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 5 DAY),'15:55:00'),'Present'),
(4,'2023-0106', TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 5 DAY),'16:05:00'),'Present'),
(8,'2022-0101', TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 20 DAY),'09:45:00'),'Present'),
(8,'2024-0108', TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 20 DAY),'10:02:00'),'Present'),
(8,'2025-0112', TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 20 DAY),'10:00:00'),'Excused');

INSERT INTO activity_logs (user_id, activity, created_at) VALUES
('ADMIN-1','Created organization: Computer Society', DATE_SUB(NOW(), INTERVAL 30 DAY)),
('2022-0101','Created event: Intro to Web Development Workshop', DATE_SUB(NOW(), INTERVAL 6 DAY)),
('2024-0108','Applied for membership in Computer Society', DATE_SUB(NOW(), INTERVAL 2 DAY)),
('2025-0110','Applied for membership in Cultural Dance Troupe', DATE_SUB(NOW(), INTERVAL 3 DAY)),
('2023-0103','Recorded attendance for Cultural Night Rehearsal', DATE_SUB(NOW(), INTERVAL 5 DAY));
