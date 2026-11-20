-- Cleaners4u quote form database (MySQL 5.7+ / MariaDB 10.3+).
-- Import into the database named in config.php, e.g.:
--   mysql -u USER -p DATABASE < database/schema.sql
-- One row per enquiry: step 1 inserts it, step 2 updates the same row.

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `leads` (
  `id`                       BIGINT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `lead_ref`                 VARCHAR(20)      NOT NULL COMMENT 'Customer-facing reference, e.g. C4U-261120-7K3QX',
  `status`                   ENUM('partial','complete') NOT NULL DEFAULT 'partial' COMMENT 'partial = step 1 only, complete = both steps',
  `session_hash`             CHAR(64)         NOT NULL COMMENT 'SHA-256 of the PHP session id that owns the lead',

  -- Step 1: contact details
  `name`                     VARCHAR(50)      NOT NULL,
  `email`                    VARCHAR(100)     NOT NULL,
  `phone`                    VARCHAR(20)      NOT NULL COMMENT 'Digits only, e.g. 0412345678',
  `suburb_hint`              VARCHAR(80)      NULL     COMMENT 'Suburb from the landing page suburb checker',
  `form_id`                  VARCHAR(50)      NULL,
  `terms_accepted`           TINYINT(1)       NOT NULL DEFAULT 0,

  -- Step 1: source and tracking
  `gclid`                    VARCHAR(255)     NULL,
  `gbraid`                   VARCHAR(255)     NULL,
  `gad_source`               VARCHAR(50)      NULL,
  `gad_campaignid`           VARCHAR(50)      NULL,
  `device_id`                VARCHAR(64)      NULL,
  `current_url`              VARCHAR(500)     NULL     COMMENT 'Page the form was sent from',
  `referrer`                 VARCHAR(500)     NULL,
  `ip_address`               VARCHAR(45)      NOT NULL,
  `user_agent`               VARCHAR(255)     NULL,
  `created_at`               DATETIME         NOT NULL COMMENT 'Step 1 submitted (site time zone)',

  -- Step 2: the clean
  `clean_type`               VARCHAR(40)      NULL,
  `clean_date`               DATE             NULL,
  `clean_date_unsure`        TINYINT(1)       NOT NULL DEFAULT 0,
  `suburb`                   VARCHAR(80)      NULL,
  `property_type`            VARCHAR(40)      NULL,
  `bedrooms`                 VARCHAR(3)       NULL,
  `bathrooms`                VARCHAR(3)       NULL,
  `addons`                   VARCHAR(255)     NULL     COMMENT 'Comma separated add-ons',
  `carpet_bedrooms`          TINYINT UNSIGNED NULL,
  `carpet_living`            TINYINT UNSIGNED NULL,
  `windows_count`            TINYINT UNSIGNED NULL,
  `walls_count`              TINYINT UNSIGNED NULL,
  `notes`                    TEXT             NULL,

  -- Step 2: move (10% off) and utilities
  `additional_services`      VARCHAR(255)     NULL,
  `combined_booking`         TINYINT(1)       NOT NULL DEFAULT 0 COMMENT '1 = move added, send to the moving sales team too',
  `move_date`                DATE             NULL,
  `move_other_suburb`        VARCHAR(80)      NULL     COMMENT 'Moving to (or from, for a move-in clean)',
  `utilities`                VARCHAR(100)     NULL,
  `utility_consent`          TINYINT(1)       NOT NULL DEFAULT 0,
  `utility_consent_text`     VARCHAR(500)     NULL,
  `utility_consent_at`       DATETIME         NULL,
  `step2_ip_address`         VARCHAR(45)      NULL,
  `completed_at`             DATETIME         NULL     COMMENT 'Step 2 submitted (site time zone)',

  -- Email delivery
  `step1_admin_mailed_at`    DATETIME         NULL,
  `step1_customer_mailed_at` DATETIME         NULL,
  `step2_admin_mailed_at`    DATETIME         NULL,
  `step2_customer_mailed_at` DATETIME         NULL,
  `last_mail_error`          VARCHAR(500)     NULL,

  `updated_at`               TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_lead_ref` (`lead_ref`),
  KEY `idx_duplicate` (`email`, `phone`, `created_at`),
  KEY `idx_ip_created` (`ip_address`, `created_at`),
  KEY `idx_status_created` (`status`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
