-- Lab 05 - Event Registration
-- Import with: mysql -u root -p < database.sql

CREATE DATABASE IF NOT EXISTS lab05_event_registration
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE lab05_event_registration;

CREATE TABLE IF NOT EXISTS registrations (
  registration_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  participant_name VARCHAR(80)  NOT NULL,
  email            VARCHAR(150) NOT NULL,
  phone            CHAR(9)      NULL,
  workshop         VARCHAR(80)  NOT NULL,
  attendance_mode  ENUM('In person','Online') NOT NULL,
  seats            TINYINT UNSIGNED NOT NULL,
  special_requirements VARCHAR(300) NULL,
  terms_accepted   TINYINT(1)   NOT NULL DEFAULT 0,
  created_at       TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (registration_id),
  -- Same email may join different workshops, but only once per workshop.
  UNIQUE KEY uq_email_workshop (email, workshop),
  CONSTRAINT chk_seats CHECK (seats BETWEEN 1 AND 3)
) ENGINE=InnoDB;

-- Verification queries
-- SELECT * FROM registrations ORDER BY registration_id DESC;
-- SELECT workshop, COUNT(*) AS registrations, SUM(seats) AS seats FROM registrations GROUP BY workshop;
-- SELECT email, workshop, COUNT(*) FROM registrations GROUP BY email, workshop HAVING COUNT(*) > 1;  -- 0 rows
