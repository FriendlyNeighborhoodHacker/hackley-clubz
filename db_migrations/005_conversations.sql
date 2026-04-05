-- Migration 005: Add chat/conversation tables
-- Run against an existing installation to add the conversations feature.
-- NOTE: Indexes are declared inline inside CREATE TABLE (MySQL does not support
--       CREATE INDEX IF NOT EXISTS), so re-running this file is safe because
--       CREATE TABLE IF NOT EXISTS skips the whole statement when the table exists.

-- ===== Conversations =====
CREATE TABLE IF NOT EXISTS conversations (
  id                  INT AUTO_INCREMENT PRIMARY KEY,
  club_id             INT NOT NULL,
  name                VARCHAR(255) NOT NULL,
  is_secret           TINYINT(1) NOT NULL DEFAULT 0,
  type                ENUM('general','leadership','custom') NOT NULL DEFAULT 'custom',
  created_by_user_id  INT NULL,
  created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_conv_club_id   (club_id),
  KEY idx_conv_club_type (club_id, type),
  CONSTRAINT fk_conv_club    FOREIGN KEY (club_id)            REFERENCES clubs(id)  ON DELETE CASCADE,
  CONSTRAINT fk_conv_creator FOREIGN KEY (created_by_user_id) REFERENCES users(id)  ON DELETE SET NULL
) ENGINE=InnoDB;

-- ===== Conversation Memberships =====
CREATE TABLE IF NOT EXISTS conversation_memberships (
  id              INT AUTO_INCREMENT PRIMARY KEY,
  conversation_id INT NOT NULL,
  user_id         INT NOT NULL,
  joined_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_conv_membership (conversation_id, user_id),
  KEY idx_convm_conv_id (conversation_id),
  KEY idx_convm_user_id (user_id),
  CONSTRAINT fk_convm_conv FOREIGN KEY (conversation_id) REFERENCES conversations(id) ON DELETE CASCADE,
  CONSTRAINT fk_convm_user FOREIGN KEY (user_id)         REFERENCES users(id)         ON DELETE CASCADE
) ENGINE=InnoDB;

-- ===== Messages =====
CREATE TABLE IF NOT EXISTS messages (
  id              INT AUTO_INCREMENT PRIMARY KEY,
  conversation_id INT NOT NULL,
  user_id         INT NULL,
  body            TEXT NOT NULL,
  is_pinned       TINYINT(1) NOT NULL DEFAULT 0,
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  deleted_at      DATETIME NULL DEFAULT NULL,
  KEY idx_msg_conv_created (conversation_id, created_at),
  KEY idx_msg_user_id      (user_id),
  KEY idx_msg_is_pinned    (conversation_id, is_pinned),
  CONSTRAINT fk_msg_conv FOREIGN KEY (conversation_id) REFERENCES conversations(id) ON DELETE CASCADE,
  CONSTRAINT fk_msg_user FOREIGN KEY (user_id)         REFERENCES users(id)         ON DELETE SET NULL
) ENGINE=InnoDB;

-- ===== Message Reactions =====
CREATE TABLE IF NOT EXISTS message_reactions (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  message_id INT NOT NULL,
  user_id    INT NOT NULL,
  reaction   VARCHAR(20) NOT NULL DEFAULT 'heart',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_reaction (message_id, user_id, reaction),
  KEY idx_react_message_id (message_id),
  KEY idx_react_user_id    (user_id),
  CONSTRAINT fk_react_message FOREIGN KEY (message_id) REFERENCES messages(id) ON DELETE CASCADE,
  CONSTRAINT fk_react_user    FOREIGN KEY (user_id)    REFERENCES users(id)    ON DELETE CASCADE
) ENGINE=InnoDB;
