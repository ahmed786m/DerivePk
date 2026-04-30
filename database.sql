DROP DATABASE IF EXISTS `drivepk`;
CREATE DATABASE `drivepk` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE `drivepk`.`users` (
    `id`           INT AUTO_INCREMENT PRIMARY KEY,
    `login_id`     VARCHAR(150) NOT NULL UNIQUE,
    `full_name`    VARCHAR(100) NOT NULL,
    `email`        VARCHAR(150) NOT NULL UNIQUE,
    `phone`        VARCHAR(20)  NOT NULL,
    `password`     VARCHAR(255) NOT NULL,
    `role`         ENUM('customer','owner','admin') NOT NULL DEFAULT 'customer',
    `cnic`         VARCHAR(20)  DEFAULT NULL,
    `city`         VARCHAR(50)  DEFAULT NULL,
    `company_name` VARCHAR(120) DEFAULT NULL,
    `status`       ENUM('active','pending','blocked') NOT NULL DEFAULT 'active',
    `created_at`   TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

INSERT INTO `drivepk`.`users` (`login_id`, `full_name`, `email`, `phone`, `password`, `role`, `status`)
VALUES
('drivepk-admin', 'DrivePK Admin', 'admin@drivepk.com', '+92 300 0000000',
 'Admin@123', 'admin', 'active');

INSERT INTO `drivepk`.`users` (`login_id`, `full_name`, `email`, `phone`, `password`, `role`, `cnic`, `city`, `company_name`, `status`)
VALUES
('owner@drivepk.com', 'Sample Car Owner', 'owner@drivepk.com', '+92 300 1111111',
 'Owner@123', 'owner',
 '35201-0000000-1', 'Lahore', 'Owner Demo Motors', 'active');

SET @sample_owner_id = (SELECT `id` FROM `drivepk`.`users` WHERE `email` = 'owner@drivepk.com' LIMIT 1);

CREATE TABLE `drivepk`.`cars` (
    `id`            INT AUTO_INCREMENT PRIMARY KEY,
    `owner_id`      INT          DEFAULT NULL,
    `brand`         VARCHAR(50)  NOT NULL,
    `model`         VARCHAR(50)  NOT NULL,
    `year`          YEAR         NOT NULL,
    `type`          ENUM('sedan','suv','hatchback','luxury') DEFAULT 'sedan',
    `fuel_type`     ENUM('petrol','diesel','hybrid')         DEFAULT 'petrol',
    `transmission`  ENUM('auto','manual','cvt')              DEFAULT 'auto',
    `seats`         TINYINT      NOT NULL DEFAULT 5,
    `price_per_day` INT          NOT NULL,
    `image`         VARCHAR(200) DEFAULT 'corolla.jpg',
    `gallery_images` TEXT        DEFAULT NULL,
    `available`     TINYINT(1)   NOT NULL DEFAULT 1,
    `created_at`    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `cars_owner_fk` FOREIGN KEY (`owner_id`) REFERENCES `drivepk`.`users`(`id`) ON DELETE SET NULL
);

INSERT INTO `drivepk`.`cars` (`owner_id`, `brand`, `model`, `year`, `type`, `fuel_type`, `transmission`, `seats`, `price_per_day`, `image`, `gallery_images`, `available`) VALUES
(@sample_owner_id, 'Toyota',  'Corolla',  2023, 'sedan',    'petrol', 'auto',   5,  8000, 'corolla.jpg',  '["corolla.jpg","corolla-rear.jpg","corolla-interior.jpg"]', 1),
(@sample_owner_id, 'Honda',   'Civic',    2023, 'sedan',    'petrol', 'auto',   5,  9500, 'civic.jpg',    '["civic.jpg","civic-exterior.jpg","civic-interior.jpg"]', 1),
(@sample_owner_id, 'Toyota',  'Fortuner', 2022, 'suv',      'diesel', 'auto',   7, 18000, 'fortuner.jpg', '["fortuner.jpg","fortuner-front.jpg","fortuner-side.jpg"]', 1),
(@sample_owner_id, 'Suzuki',  'Cultus',   2023, 'hatchback','petrol', 'manual', 5,  5500, 'cultus.jpg',   '["cultus.jpg","cultus-front.jpg","cultus-side.jpg"]', 1),
(@sample_owner_id, 'Honda',   'BR-V',     2023, 'suv',      'petrol', 'cvt',    7, 12000, 'brv.jpg',      '["brv.jpg","brv-front.jpg","brv-interior.jpg"]', 1),
(@sample_owner_id, 'Hyundai', 'Tucson',   2023, 'suv',      'hybrid', 'auto',   5, 15000, 'tucson.jpg',   '["tucson.jpg","tucson-front.png","tucson-interior.jpg"]', 0);

CREATE TABLE `drivepk`.`car_requests` (
    `id`              INT AUTO_INCREMENT PRIMARY KEY,
    `owner_id`        INT          NOT NULL,
    `brand`           VARCHAR(50)  NOT NULL,
    `model`           VARCHAR(50)  NOT NULL,
    `year`            YEAR         NOT NULL,
    `type`            ENUM('sedan','suv','hatchback','luxury') DEFAULT 'sedan',
    `fuel_type`       ENUM('petrol','diesel','hybrid')         DEFAULT 'petrol',
    `transmission`    ENUM('auto','manual','cvt')              DEFAULT 'auto',
    `seats`           TINYINT      NOT NULL DEFAULT 5,
    `price_per_day`   INT          NOT NULL,
    `image`           VARCHAR(200) DEFAULT 'corolla.jpg',
    `gallery_images`  TEXT         DEFAULT NULL,
    `city`            VARCHAR(50)  DEFAULT NULL,
    `description`     TEXT         DEFAULT NULL,
    `status`          ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
    `admin_note`      VARCHAR(255) DEFAULT NULL,
    `approved_car_id` INT          DEFAULT NULL,
    `reviewed_by`     INT          DEFAULT NULL,
    `reviewed_at`     DATETIME     DEFAULT NULL,
    `created_at`      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `car_requests_owner_fk` FOREIGN KEY (`owner_id`) REFERENCES `drivepk`.`users`(`id`) ON DELETE CASCADE,
    CONSTRAINT `car_requests_car_fk` FOREIGN KEY (`approved_car_id`) REFERENCES `drivepk`.`cars`(`id`) ON DELETE SET NULL,
    CONSTRAINT `car_requests_admin_fk` FOREIGN KEY (`reviewed_by`) REFERENCES `drivepk`.`users`(`id`) ON DELETE SET NULL
);

CREATE TABLE `drivepk`.`bookings` (
    `id`           INT AUTO_INCREMENT PRIMARY KEY,
    `booking_ref`  VARCHAR(20)  NOT NULL UNIQUE,
    `user_id`      INT          DEFAULT NULL,
    `car_id`       INT          NOT NULL,
    `full_name`    VARCHAR(100) NOT NULL,
    `email`        VARCHAR(150) NOT NULL,
    `phone`        VARCHAR(20)  NOT NULL,
    `cnic`         VARCHAR(20)  NOT NULL,
    `pickup_date`  DATE         NOT NULL,
    `dropoff_date` DATE         NOT NULL,
    `pickup_city`  VARCHAR(50)  NOT NULL,
    `total_days`   INT          NOT NULL,
    `total_price`  INT          NOT NULL,
    `notes`        TEXT         DEFAULT NULL,
    `owner_note`   VARCHAR(255) DEFAULT NULL,
    `status`       ENUM('pending','confirmed','active','completed','cancelled','rejected') NOT NULL DEFAULT 'pending',
    `created_at`   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `bookings_user_fk` FOREIGN KEY (`user_id`) REFERENCES `drivepk`.`users`(`id`) ON DELETE SET NULL,
    CONSTRAINT `bookings_car_fk`  FOREIGN KEY (`car_id`)  REFERENCES `drivepk`.`cars`(`id`)  ON DELETE CASCADE
);

CREATE TABLE `drivepk`.`messages` (
    `id`         INT AUTO_INCREMENT PRIMARY KEY,
    `name`       VARCHAR(100) NOT NULL,
    `email`      VARCHAR(150) NOT NULL,
    `phone`      VARCHAR(20)  DEFAULT NULL,
    `subject`    VARCHAR(50)  DEFAULT 'other',
    `message`    TEXT         NOT NULL,
    `is_read`    TINYINT(1)   NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
