CREATE DATABASE IF NOT EXISTS drivepk;
USE drivepk;

ALTER TABLE users ADD COLUMN IF NOT EXISTS login_id VARCHAR(150) NULL AFTER id;
ALTER TABLE users ADD COLUMN IF NOT EXISTS cnic VARCHAR(20) DEFAULT NULL AFTER role;
ALTER TABLE users ADD COLUMN IF NOT EXISTS city VARCHAR(50) DEFAULT NULL AFTER cnic;
ALTER TABLE users ADD COLUMN IF NOT EXISTS company_name VARCHAR(120) DEFAULT NULL AFTER city;
ALTER TABLE users ADD COLUMN IF NOT EXISTS status ENUM('active','pending','blocked') NOT NULL DEFAULT 'active' AFTER company_name;

ALTER TABLE users MODIFY role ENUM('user','customer','owner','admin') NOT NULL DEFAULT 'customer';
UPDATE users SET role = 'customer' WHERE role = 'user';
UPDATE users SET login_id = email WHERE login_id IS NULL OR login_id = '';
ALTER TABLE users MODIFY role ENUM('customer','owner','admin') NOT NULL DEFAULT 'customer';
ALTER TABLE users MODIFY login_id VARCHAR(150) NOT NULL;
CREATE UNIQUE INDEX IF NOT EXISTS users_login_id_unique ON users (login_id);

ALTER TABLE cars ADD COLUMN IF NOT EXISTS owner_id INT DEFAULT NULL AFTER id;
ALTER TABLE cars ADD COLUMN IF NOT EXISTS gallery_images TEXT DEFAULT NULL AFTER image;

ALTER TABLE bookings ADD COLUMN IF NOT EXISTS owner_note VARCHAR(255) DEFAULT NULL AFTER notes;
ALTER TABLE bookings MODIFY status ENUM('pending','confirmed','active','completed','cancelled','rejected') NOT NULL DEFAULT 'pending';

INSERT INTO users (login_id, full_name, email, phone, password, role, status)
VALUES
('drivepk-admin', 'DrivePK Admin', 'admin@drivepk.com', '+92 300 0000000',
 'Admin@123', 'admin', 'active')
ON DUPLICATE KEY UPDATE
    login_id = VALUES(login_id),
    full_name = VALUES(full_name),
    password = VALUES(password),
    role = VALUES(role),
    status = VALUES(status);

INSERT INTO users (login_id, full_name, email, phone, password, role, cnic, city, company_name, status)
VALUES
('owner@drivepk.com', 'Sample Car Owner', 'owner@drivepk.com', '+92 300 1111111',
 'Owner@123', 'owner',
 '35201-0000000-1', 'Lahore', 'Owner Demo Motors', 'active')
ON DUPLICATE KEY UPDATE
    login_id = VALUES(login_id),
    full_name = VALUES(full_name),
    password = VALUES(password),
    role = VALUES(role),
    status = VALUES(status);

UPDATE cars
   SET owner_id = (SELECT id FROM users WHERE email = 'owner@drivepk.com' LIMIT 1)
 WHERE owner_id IS NULL
   AND EXISTS (SELECT 1 FROM users WHERE email = 'owner@drivepk.com');

UPDATE cars
   SET gallery_images = CASE
        WHEN LOWER(CONCAT(brand, ' ', model)) = 'toyota corolla' THEN '["corolla.jpg","corolla-rear.jpg","corolla-interior.jpg"]'
        WHEN LOWER(CONCAT(brand, ' ', model)) = 'honda civic' THEN '["civic.jpg","civic-exterior.jpg","civic-interior.jpg"]'
        WHEN LOWER(CONCAT(brand, ' ', model)) = 'toyota fortuner' THEN '["fortuner.jpg","fortuner-front.jpg","fortuner-side.jpg"]'
        WHEN LOWER(CONCAT(brand, ' ', model)) = 'suzuki cultus' THEN '["cultus.jpg","cultus-front.jpg","cultus-side.jpg"]'
        WHEN LOWER(CONCAT(brand, ' ', model)) = 'honda br-v' THEN '["brv.jpg","brv-front.jpg","brv-interior.jpg"]'
        WHEN LOWER(CONCAT(brand, ' ', model)) = 'hyundai tucson' THEN '["tucson.jpg","tucson-front.png","tucson-interior.jpg"]'
        ELSE CONCAT('["', image, '"]')
    END
 WHERE gallery_images IS NULL OR gallery_images = '';

CREATE TABLE IF NOT EXISTS car_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    owner_id INT NOT NULL,
    brand VARCHAR(50) NOT NULL,
    model VARCHAR(50) NOT NULL,
    year YEAR NOT NULL,
    type ENUM('sedan','suv','hatchback','luxury') DEFAULT 'sedan',
    fuel_type ENUM('petrol','diesel','hybrid') DEFAULT 'petrol',
    transmission ENUM('auto','manual','cvt') DEFAULT 'auto',
    seats TINYINT NOT NULL DEFAULT 5,
    price_per_day INT NOT NULL,
    image VARCHAR(200) DEFAULT 'corolla.jpg',
    gallery_images TEXT DEFAULT NULL,
    city VARCHAR(50) DEFAULT NULL,
    description TEXT DEFAULT NULL,
    status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
    admin_note VARCHAR(255) DEFAULT NULL,
    approved_car_id INT DEFAULT NULL,
    reviewed_by INT DEFAULT NULL,
    reviewed_at DATETIME DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX owner_idx (owner_id),
    INDEX status_idx (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
