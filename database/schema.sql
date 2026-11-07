-- Devs Arun — database schema (MySQL 5.7+/8, MariaDB 10.4+)
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS settings (
  k VARCHAR(80) NOT NULL PRIMARY KEY,
  v MEDIUMTEXT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS admin_users (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  email VARCHAR(190) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('owner','manager','viewer') NOT NULL DEFAULT 'manager',
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  last_login_at DATETIME NULL,
  last_login_ip VARCHAR(64) NULL,
  created_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS login_attempts (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(190) NOT NULL,
  ip_hash VARCHAR(64) NOT NULL,
  success TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL,
  KEY idx_ip (ip_hash, created_at),
  KEY idx_email (email, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rate_limits (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  k VARCHAR(120) NOT NULL,
  created_at DATETIME NOT NULL,
  KEY idx_k (k, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS audit_log (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  admin_id INT UNSIGNED NULL,
  action VARCHAR(80) NOT NULL,
  entity VARCHAR(40) NULL,
  entity_id BIGINT NULL,
  meta TEXT NULL,
  ip_hash VARCHAR(64) NULL,
  created_at DATETIME NOT NULL,
  KEY idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS services (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  slug VARCHAR(80) NOT NULL UNIQUE,
  title VARCHAR(150) NOT NULL,
  category VARCHAR(30) NOT NULL DEFAULT 'websites',
  icon VARCHAR(30) NOT NULL DEFAULT 'sparkles',
  tagline VARCHAR(190) NULL,
  summary VARCHAR(400) NULL,
  description TEXT NULL,
  price_from INT UNSIGNED NULL,
  price_to INT UNSIGNED NULL,
  price_suffix VARCHAR(30) NULL,
  price_label VARCHAR(80) NULL,
  price_usd VARCHAR(40) NULL,
  timeline VARCHAR(80) NULL,
  features TEXT NULL,
  deliverables TEXT NULL,
  process TEXT NULL,
  faqs TEXT NULL,
  ideal_for TEXT NULL,
  not_included TEXT NULL,
  keywords VARCHAR(400) NULL,
  overview TEXT NULL,
  benefits TEXT NULL,
  packages TEXT NULL,
  tech_stack TEXT NULL,
  is_buyable TINYINT(1) NOT NULL DEFAULT 0,
  buy_price INT UNSIGNED NULL,
  buy_label VARCHAR(80) NULL,
  is_featured TINYINT(1) NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  sort_order INT NOT NULL DEFAULT 100,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS projects (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  type VARCHAR(20) NOT NULL,
  title VARCHAR(150) NOT NULL,
  subtitle VARCHAR(190) NULL,
  description TEXT NULL,
  url VARCHAR(500) NULL,
  tags TEXT NULL,
  icon VARCHAR(30) NULL,
  metric VARCHAR(80) NULL,
  is_featured TINYINT(1) NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  sort_order INT NOT NULL DEFAULT 100,
  created_at DATETIME NOT NULL,
  KEY idx_type (type, is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS testimonials (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  role VARCHAR(150) NULL,
  quote TEXT NOT NULL,
  rating TINYINT NOT NULL DEFAULT 5,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  sort_order INT NOT NULL DEFAULT 100,
  created_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS leads (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  ref VARCHAR(20) NOT NULL UNIQUE,
  source VARCHAR(20) NOT NULL DEFAULT 'brief',
  service_id INT UNSIGNED NULL,
  service_label VARCHAR(150) NULL,
  project_title VARCHAR(190) NULL,
  description TEXT NULL,
  features TEXT NULL,
  budget VARCHAR(60) NULL,
  timeline VARCHAR(60) NULL,
  start_date DATE NULL,
  existing_website VARCHAR(500) NULL,
  reference_links TEXT NULL,
  name VARCHAR(120) NOT NULL,
  email VARCHAR(190) NOT NULL,
  phone VARCHAR(30) NULL,
  country_code VARCHAR(8) NULL,
  company VARCHAR(150) NULL,
  preferred_contact VARCHAR(20) NULL,
  call_time VARCHAR(60) NULL,
  heard_from VARCHAR(60) NULL,
  attachment_path VARCHAR(255) NULL,
  attachment_name VARCHAR(190) NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'new',
  priority VARCHAR(10) NOT NULL DEFAULT 'normal',
  value_estimate INT UNSIGNED NULL,
  follow_up_at DATE NULL,
  tags VARCHAR(255) NULL,
  landing VARCHAR(255) NULL,
  referrer VARCHAR(120) NULL,
  utm_source VARCHAR(80) NULL,
  utm_medium VARCHAR(80) NULL,
  utm_campaign VARCHAR(80) NULL,
  ip_hash VARCHAR(64) NULL,
  user_agent VARCHAR(255) NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NULL,
  KEY idx_status (status),
  KEY idx_created (created_at),
  KEY idx_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS lead_activities (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  lead_id INT UNSIGNED NOT NULL,
  admin_id INT UNSIGNED NULL,
  type VARCHAR(30) NOT NULL,
  body TEXT NOT NULL,
  is_public TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL,
  KEY idx_lead (lead_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS quotes (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  token VARCHAR(64) NOT NULL UNIQUE,
  lead_id INT UNSIGNED NULL,
  title VARCHAR(190) NOT NULL,
  description TEXT NULL,
  amount_paise BIGINT UNSIGNED NOT NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'sent',
  valid_until DATE NULL,
  created_by INT UNSIGNED NULL,
  created_at DATETIME NOT NULL,
  paid_at DATETIME NULL,
  KEY idx_lead (lead_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS orders (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  order_no VARCHAR(30) NOT NULL UNIQUE,
  access_token VARCHAR(64) NOT NULL,
  service_id INT UNSIGNED NULL,
  quote_id INT UNSIGNED NULL,
  lead_id INT UNSIGNED NULL,
  item_title VARCHAR(190) NOT NULL,
  amount_paise BIGINT UNSIGNED NOT NULL,
  currency VARCHAR(3) NOT NULL DEFAULT 'INR',
  customer_name VARCHAR(120) NOT NULL,
  customer_email VARCHAR(190) NOT NULL,
  customer_phone VARCHAR(30) NULL,
  notes TEXT NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'created',
  razorpay_order_id VARCHAR(60) NULL UNIQUE,
  razorpay_payment_id VARCHAR(60) NULL,
  payment_method VARCHAR(40) NULL,
  created_at DATETIME NOT NULL,
  paid_at DATETIME NULL,
  KEY idx_status (status, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS chat_sessions (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  token VARCHAR(64) NOT NULL UNIQUE,
  visitor_id VARCHAR(32) NULL,
  name VARCHAR(120) NULL,
  email VARCHAR(190) NULL,
  phone VARCHAR(30) NULL,
  lead_id INT UNSIGNED NULL,
  page VARCHAR(255) NULL,
  messages_count INT UNSIGNED NOT NULL DEFAULT 0,
  engine VARCHAR(30) NULL,
  ip_hash VARCHAR(64) NULL,
  created_at DATETIME NOT NULL,
  last_at DATETIME NOT NULL,
  KEY idx_last (last_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS chat_messages (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  session_id INT UNSIGNED NOT NULL,
  role VARCHAR(12) NOT NULL,
  content TEXT NOT NULL,
  created_at DATETIME NOT NULL,
  KEY idx_session (session_id, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS page_views (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  visitor_id VARCHAR(32) NOT NULL,
  session_id VARCHAR(32) NOT NULL,
  path VARCHAR(255) NOT NULL,
  referrer VARCHAR(120) NULL,
  utm_source VARCHAR(80) NULL,
  device VARCHAR(10) NULL,
  browser VARCHAR(20) NULL,
  created_at DATETIME NOT NULL,
  KEY idx_created (created_at),
  KEY idx_path (path(100), created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS events (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(40) NOT NULL,
  label VARCHAR(190) NULL,
  service_id INT UNSIGNED NULL,
  visitor_id VARCHAR(32) NULL,
  path VARCHAR(255) NULL,
  created_at DATETIME NOT NULL,
  KEY idx_name (name, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS email_log (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  to_email VARCHAR(190) NOT NULL,
  subject VARCHAR(250) NOT NULL,
  body_html MEDIUMTEXT NULL,
  kind VARCHAR(40) NULL,
  lead_id INT UNSIGNED NULL,
  admin_id INT UNSIGNED NULL,
  status VARCHAR(10) NOT NULL,
  error VARCHAR(500) NULL,
  created_at DATETIME NOT NULL,
  KEY idx_created (created_at),
  KEY idx_lead (lead_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS email_templates (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  subject VARCHAR(250) NOT NULL,
  body TEXT NOT NULL,
  created_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
