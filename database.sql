-- F Mail Database Schema

CREATE TABLE IF NOT EXISTS `site_settings` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `key` VARCHAR(255) NOT NULL UNIQUE,
  `val` TEXT,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `accounts` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `address` VARCHAR(255) NOT NULL UNIQUE,
  `password` VARCHAR(255) NOT NULL,
  `token` TEXT,
  `account_id` VARCHAR(255),
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `api_keys` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT,
  `api_key` VARCHAR(64) NOT NULL UNIQUE,
  `status` ENUM('active', 'revoked') DEFAULT 'active',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `FOREIGN KEY` (`user_id`) REFERENCES `accounts`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `activity_log` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `action` VARCHAR(255) NOT NULL,
  `details` TEXT,
  `ip_address` VARCHAR(45),
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Default Settings
INSERT IGNORE INTO `site_settings` (`key`, `val`) VALUES 
('brand_name', 'F Mail'),
('admin_password', 'admin123'),
('allowed_domains', 'dollicons.com,oakon.com,teihu.com,raleigh-construction.com,pastryofistanbul.com,questtechsystems.com'),
('smtp_host', 'smtp.gmail.com'),
('smtp_port', '587'),
('smtp_user', 'your-email@gmail.com'),
('smtp_pass', 'your-app-password'),
('smtp_enc', 'tls'),
('favicon_url', 'data:image/svg+xml,<svg viewBox=''0 0 240 180'' xmlns=''http://www.w3.org/2000/svg''><rect width=''40'' height=''40'' rx=''8'' fill=''%234F46E5''/><text x=''10'' y=''30'' fill=''white'' style=''font: italic bold 25px sans-serif;''>F</text></svg>'),
('more_links', '[{"label":"Facebook","url":"https://facebook.com","image":"https://upload.wikimedia.org/wikipedia/commons/b/b8/2021_Facebook_icon.svg"},{"label":"Twitter","url":"https://twitter.com","image":"https://upload.wikimedia.org/wikipedia/commons/6/6f/Logo_of_Twitter.svg"},{"label":"Instagram","url":"https://instagram.com","image":"https://upload.wikimedia.org/wikipedia/commons/e/e7/Instagram_logo_2016.svg"},{"label":"Telegram","url":"https://telegram.org","image":"https://upload.wikimedia.org/wikipedia/commons/8/82/Telegram_logo.svg"},{"label":"YouTube","url":"https://youtube.com","image":"https://upload.wikimedia.org/wikipedia/commons/0/09/YouTube_full-color_icon_%282017%29.svg"},{"label":"GitHub","url":"https://github.com","image":"https://upload.wikimedia.org/wikipedia/commons/9/91/Octicons-mark-github.svg"}]'),
('super_links', '[]');
