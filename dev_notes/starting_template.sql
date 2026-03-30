-- Template application schema
-- Create DB then use it
-- CREATE DATABASE hackleyclubz DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
-- USE hackleyclubz;

SET NAMES utf8mb4;
SET time_zone = '+00:00';

-- Adults/users
CREATE TABLE users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  first_name VARCHAR(100) NOT NULL,
  last_name  VARCHAR(100) NOT NULL,
  email      VARCHAR(255) DEFAULT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  is_admin   TINYINT(1) NOT NULL DEFAULT 0,

  phone VARCHAR(30) DEFAULT NULL,

  -- Email verification + password resets
  email_verify_token VARCHAR(64) DEFAULT NULL,
  email_verified_at DATETIME DEFAULT NULL,
  password_reset_token_hash CHAR(64) DEFAULT NULL,
  password_reset_expires_at DATETIME DEFAULT NULL,

  -- Unsubscribe from emails
  unsubscribed TINYINT(1) NOT NULL DEFAULT 0,

  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE INDEX idx_users_email_verify_token ON users(email_verify_token);
CREATE INDEX idx_users_pwreset_expires ON users(password_reset_expires_at);

-- Events
CREATE TABLE events (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(255) NOT NULL,
  starts_at DATETIME NOT NULL,
  ends_at   DATETIME DEFAULT NULL,
  location VARCHAR(255) DEFAULT NULL,
  location_address TEXT DEFAULT NULL,
  description TEXT DEFAULT NULL,
  google_maps_url VARCHAR(512) DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX (starts_at)
) ENGINE=InnoDB;


CREATE TABLE rsvps (
  id INT AUTO_INCREMENT PRIMARY KEY,
  event_id INT NOT NULL,
  user_id INT NOT NULL,
  entered_by INT NULL, -- so admins can rsvp for other people
  comments TEXT DEFAULT NULL,
  answer ENUM('yes','maybe','no') NOT NULL DEFAULT 'yes',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_rsvps_event FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
  CONSTRAINT fk_rsvps_creator FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT,
  CONSTRAINT fk_rsvps_entered_by FOREIGN KEY (entered_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE INDEX idx_rsvps_event ON rsvps(event_id);
CREATE INDEX idx_rsvps_event ON rsvps(user_id);
CREATE INDEX idx_rsvps_entered_by ON rsvps(entered_by);

-- Settings key-value
CREATE TABLE settings (
  id INT AUTO_INCREMENT PRIMARY KEY,
  key_name VARCHAR(191) NOT NULL UNIQUE,
  value LONGTEXT NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Default settings
INSERT INTO settings (key_name, value) VALUES
  ('site_title', 'Hackley Clubz'),
  ('announcement', ''),
  ('timezone', '')
ON DUPLICATE KEY UPDATE value=VALUES(value);

-- Optional: seed an admin user (update email and password hash, then remove)
INSERT INTO users (first_name,last_name,email,password_hash,is_admin,email_verified_at)
VALUES ('Lilly','Rosenthal','lirosenthal@students.hackleyschool.org','$2y$10$9xH7Jq4v3o6s9k3y8i4rVOyWb0yBYZ5rW.0f9pZ.gG9K6l7lS6b2S',1,NOW());

-- ===== Files Storage (DB-backed uploads) =====

-- Public files (event photos, profile photos)
CREATE TABLE public_files (
  id INT AUTO_INCREMENT PRIMARY KEY,
  data LONGBLOB NOT NULL,
  content_type VARCHAR(100) DEFAULT NULL,
  original_filename VARCHAR(255) DEFAULT NULL,
  byte_length INT UNSIGNED DEFAULT NULL,
  sha256 CHAR(64) DEFAULT NULL,
  created_by_user_id INT DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_pf_created_by FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE INDEX idx_pf_sha256 ON public_files(sha256);
CREATE INDEX idx_pf_created_by ON public_files(created_by_user_id);
CREATE INDEX idx_pf_created_at ON public_files(created_at);

-- Link columns (added via ALTER to avoid circular FK creation order)
ALTER TABLE users
  ADD COLUMN photo_public_file_id INT NULL;

ALTER TABLE users
  ADD CONSTRAINT fk_users_photo_public_file
    FOREIGN KEY (photo_public_file_id) REFERENCES public_files(id) ON DELETE SET NULL;

ALTER TABLE events
  ADD COLUMN photo_public_file_id INT NULL;

ALTER TABLE events ADD COLUMN club_id INT NOT NULL;


ALTER TABLE events
  ADD CONSTRAINT fk_events_photo_public_file
    FOREIGN KEY (photo_public_file_id) REFERENCES public_files(id) ON DELETE SET NULL;


-- ===== Activity Log =====
CREATE TABLE activity_log (
  id INT AUTO_INCREMENT PRIMARY KEY,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  user_id INT NULL,
  action_type VARCHAR(64) NOT NULL,
  json_metadata LONGTEXT NULL,
  CONSTRAINT fk_activity_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE INDEX idx_al_created_at ON activity_log(created_at);
CREATE INDEX idx_al_user_id ON activity_log(user_id);
CREATE INDEX idx_al_action_type ON activity_log(action_type);

-- Create emails_sent table for logging all emails sent by the system
CREATE TABLE emails_sent (
  id INT AUTO_INCREMENT PRIMARY KEY,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  sent_by_user_id INT NULL,
  to_user_id int null,
  to_email VARCHAR(255) NOT NULL,
  to_name VARCHAR(255) DEFAULT NULL,
  cc_email VARCHAR(255) DEFAULT NULL,
  subject VARCHAR(500) NOT NULL,
  body_html LONGTEXT NOT NULL,
  success TINYINT(1) NOT NULL DEFAULT 0,
  error_message TEXT DEFAULT NULL,
  CONSTRAINT fk_emails_sent_user FOREIGN KEY (sent_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE INDEX idx_emails_sent_created_at ON emails_sent(created_at);
CREATE INDEX idx_emails_sent_by_user_id ON emails_sent(sent_by_user_id);
CREATE INDEX idx_emails_to_user_id ON emails_sent(to_user_id);
CREATE INDEX idx_emails_sent_to_email ON emails_sent(to_email);
CREATE INDEX idx_emails_sent_success ON emails_sent(success);


