-- Hackley Clubz — complete database schema
-- Run this on a fresh database to set up the full schema.
-- Migrations are available in db_migrations/ for upgrading existing installations.
--
-- CREATE DATABASE hackleyclubz DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
-- USE hackleyclubz;

SET NAMES utf8mb4;
SET time_zone = '+00:00';

-- ===== Settings =====
CREATE TABLE settings (
  id INT AUTO_INCREMENT PRIMARY KEY,
  key_name VARCHAR(191) NOT NULL UNIQUE,
  value LONGTEXT NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Default application settings
INSERT INTO settings (key_name, value) VALUES
  ('site_title',            'Hackley Clubz'),
  ('site_logo_file_id',     NULL),
  ('student_email_domains', 'students.hackleyschool.org'),
  ('adult_email_domains',   'hackleyschool.org'),
  ('timezone',              'America/New_York'),
  ('announcement',          '')
ON DUPLICATE KEY UPDATE value = VALUES(value);

-- ===== Public File Storage =====
-- Stores all uploaded binary content (profile photos, club hero images, event images, etc.)
-- Files may be cached on-disk by the application for performance; render_image.php serves
-- uncached files. Cache lives in cache/public/.
CREATE TABLE public_files (
  id                  INT AUTO_INCREMENT PRIMARY KEY,
  data                LONGBLOB NOT NULL,
  content_type        VARCHAR(100) DEFAULT NULL,
  original_filename   VARCHAR(255) DEFAULT NULL,
  byte_length         INT UNSIGNED DEFAULT NULL,
  sha256              CHAR(64) DEFAULT NULL,
  created_by_user_id  INT DEFAULT NULL,
  created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE INDEX idx_pf_sha256      ON public_files(sha256);
CREATE INDEX idx_pf_created_by  ON public_files(created_by_user_id);
CREATE INDEX idx_pf_created_at  ON public_files(created_at);

-- ===== Users =====
CREATE TABLE users (
  id                        INT AUTO_INCREMENT PRIMARY KEY,
  email                     VARCHAR(255) NOT NULL UNIQUE,
  first_name                VARCHAR(100) NOT NULL DEFAULT '',
  last_name                 VARCHAR(100) NOT NULL DEFAULT '',
  phone                     VARCHAR(30)  DEFAULT NULL,
  -- user_type is derived from email domain on registration:
  --   students.hackleyschool.org → 'student'
  --   hackleyschool.org          → 'adult'
  user_type                 ENUM('student','adult') NOT NULL DEFAULT 'student',
  is_admin                  TINYINT(1) NOT NULL DEFAULT 0,
  password_hash             VARCHAR(255) NOT NULL DEFAULT '',
  photo_public_file_id      INT NULL,

  -- Email verification
  email_verify_token        VARCHAR(64) DEFAULT NULL,
  email_verified_at         DATETIME    DEFAULT NULL,

  -- Password reset
  password_reset_token_hash CHAR(64)   DEFAULT NULL,
  password_reset_expires_at DATETIME   DEFAULT NULL,

  created_at                DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

  CONSTRAINT fk_users_photo FOREIGN KEY (photo_public_file_id)
    REFERENCES public_files(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE INDEX idx_users_email_verify_token  ON users(email_verify_token);
CREATE INDEX idx_users_pwreset_expires     ON users(password_reset_expires_at);

-- Back-fill the FK on public_files → users (avoids circular FK creation order)
ALTER TABLE public_files
  ADD CONSTRAINT fk_pf_created_by
    FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE SET NULL;

-- ===== Activity Log =====
CREATE TABLE activity_log (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  user_id       INT NULL,
  action_type   VARCHAR(64) NOT NULL,
  json_metadata LONGTEXT NULL,
  CONSTRAINT fk_activity_user FOREIGN KEY (user_id)
    REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE INDEX idx_al_created_at  ON activity_log(created_at);
CREATE INDEX idx_al_user_id     ON activity_log(user_id);
CREATE INDEX idx_al_action_type ON activity_log(action_type);

-- ===== Email Log =====
CREATE TABLE emails_sent (
  id                INT AUTO_INCREMENT PRIMARY KEY,
  created_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  sent_by_user_id   INT NULL,
  to_email          VARCHAR(255) NOT NULL,
  to_name           VARCHAR(255) DEFAULT NULL,
  cc_email          VARCHAR(255) DEFAULT NULL,
  subject           VARCHAR(500) NOT NULL,
  body_html         LONGTEXT NOT NULL,
  success           TINYINT(1) NOT NULL DEFAULT 0,
  error_message     TEXT DEFAULT NULL,
  CONSTRAINT fk_emails_sent_user FOREIGN KEY (sent_by_user_id)
    REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE INDEX idx_emails_sent_created_at    ON emails_sent(created_at);
CREATE INDEX idx_emails_sent_by_user_id    ON emails_sent(sent_by_user_id);
CREATE INDEX idx_emails_sent_to_email      ON emails_sent(to_email);
CREATE INDEX idx_emails_sent_success       ON emails_sent(success);

-- ===== Clubs =====
CREATE TABLE clubs (
  id                  INT AUTO_INCREMENT PRIMARY KEY,
  name                VARCHAR(255) NOT NULL,
  description         TEXT DEFAULT NULL,
  meets               VARCHAR(255) DEFAULT NULL,  -- e.g. "Tuesdays 3:30pm, Room 214"
  photo_public_file_id INT NULL,
  hero_public_file_id  INT NULL,
  is_secret           TINYINT(1) NOT NULL DEFAULT 0,
  created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_clubs_photo FOREIGN KEY (photo_public_file_id)
    REFERENCES public_files(id) ON DELETE SET NULL,
  CONSTRAINT fk_clubs_hero FOREIGN KEY (hero_public_file_id)
    REFERENCES public_files(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ===== Club Memberships =====
-- notification_setting: 'everything' | 'just_for_you' | 'nothing'
CREATE TABLE club_memberships (
  id                   INT AUTO_INCREMENT PRIMARY KEY,
  club_id              INT NOT NULL,
  user_id              INT NOT NULL,
  is_club_admin        TINYINT(1) NOT NULL DEFAULT 0,
  role                 VARCHAR(100) DEFAULT NULL,  -- e.g. "Treasurer"
  notification_setting ENUM('everything','just_for_you','nothing') NOT NULL DEFAULT 'everything',
  joined_at            DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_membership (club_id, user_id),
  CONSTRAINT fk_cm_club FOREIGN KEY (club_id)  REFERENCES clubs(id)  ON DELETE CASCADE,
  CONSTRAINT fk_cm_user FOREIGN KEY (user_id)  REFERENCES users(id)  ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE INDEX idx_cm_club_id ON club_memberships(club_id);
CREATE INDEX idx_cm_user_id ON club_memberships(user_id);

-- ===== Events =====
CREATE TABLE events (
  id                   INT AUTO_INCREMENT PRIMARY KEY,
  club_id              INT NOT NULL,
  name                 VARCHAR(255) NOT NULL,
  starts_at            DATETIME NOT NULL,
  ends_at              DATETIME DEFAULT NULL,
  location_name        VARCHAR(255) DEFAULT NULL,
  location_address     TEXT DEFAULT NULL,
  google_maps_url      VARCHAR(512) DEFAULT NULL,
  description          TEXT DEFAULT NULL,
  photo_public_file_id INT NULL,
  created_by_user_id   INT NULL,
  created_at           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_events_club  FOREIGN KEY (club_id)            REFERENCES clubs(id)        ON DELETE CASCADE,
  CONSTRAINT fk_events_photo FOREIGN KEY (photo_public_file_id) REFERENCES public_files(id) ON DELETE SET NULL,
  CONSTRAINT fk_events_creator FOREIGN KEY (created_by_user_id)  REFERENCES users(id)       ON DELETE SET NULL,
  INDEX idx_events_starts_at (starts_at),
  INDEX idx_events_club_id   (club_id)
) ENGINE=InnoDB;

-- ===== RSVPs =====
CREATE TABLE rsvps (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  event_id    INT NOT NULL,
  user_id     INT NOT NULL,
  entered_by  INT NULL,  -- admin-entered RSVPs
  answer      ENUM('yes','maybe','no') NOT NULL DEFAULT 'yes',
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_rsvp (event_id, user_id),
  CONSTRAINT fk_rsvps_event      FOREIGN KEY (event_id)   REFERENCES events(id) ON DELETE CASCADE,
  CONSTRAINT fk_rsvps_user       FOREIGN KEY (user_id)    REFERENCES users(id)  ON DELETE CASCADE,
  CONSTRAINT fk_rsvps_entered_by FOREIGN KEY (entered_by) REFERENCES users(id)  ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE INDEX idx_rsvps_event_id ON rsvps(event_id);
CREATE INDEX idx_rsvps_user_id  ON rsvps(user_id);
