SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS payments;
DROP TABLE IF EXISTS bookings;
DROP TABLE IF EXISTS court_pricing;
DROP TABLE IF EXISTS pricing_slots;
DROP TABLE IF EXISTS courts;
DROP TABLE IF EXISTS owners;
DROP TABLE IF EXISTS users;
SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE users (
    user_id INT NOT NULL AUTO_INCREMENT,
    username VARCHAR(50) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    phone_number VARCHAR(20) NULL,
    email VARCHAR(100) NULL,
    role ENUM('customer', 'staff', 'admin', 'owner') NOT NULL DEFAULT 'customer',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    user_balance DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    profile_image_url TEXT NULL,
    PRIMARY KEY (user_id),
    UNIQUE KEY uq_users_username (username),
    UNIQUE KEY uq_users_phone_number (phone_number),
    UNIQUE KEY uq_users_email (email),
    INDEX idx_users_role (role)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE owners (
    owner_id INT NOT NULL AUTO_INCREMENT,
    user_id INT NOT NULL,
    court_count INT NOT NULL DEFAULT 0,
    revenue DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (owner_id),
    UNIQUE KEY uq_owners_user_id (user_id),
    CONSTRAINT fk_owners_user FOREIGN KEY (user_id)
        REFERENCES users(user_id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE courts (
    court_id INT NOT NULL AUTO_INCREMENT,
    court_name VARCHAR(50) NOT NULL,
    court_type VARCHAR(50) NULL,
    price_per_hour DECIMAL(10,2) NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    description TEXT NULL,
    image_url TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    facility_id INT NULL,
    address TEXT NULL,
    opening_hours JSON NULL,
    phone_number VARCHAR(20) NULL,
    facilities JSON NULL,
    court_image TEXT NULL,
    owner_id INT NULL,
    PRIMARY KEY (court_id),
    INDEX idx_courts_owner (owner_id),
    CONSTRAINT fk_courts_owner FOREIGN KEY (owner_id)
        REFERENCES users(user_id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE bookings (
    booking_id INT NOT NULL AUTO_INCREMENT,
    user_id INT NOT NULL,
    court_id INT NOT NULL,
    start_time DATETIME NOT NULL,
    end_time DATETIME NOT NULL,
    total_price DECIMAL(10,2) NOT NULL,
    status ENUM('pending', 'confirmed', 'cancelled', 'completed') NOT NULL DEFAULT 'pending',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    time_slot_id INT NULL,
    PRIMARY KEY (booking_id),
    INDEX idx_bookings_user (user_id),
    INDEX idx_bookings_court_time (court_id, start_time),
    CONSTRAINT fk_bookings_user FOREIGN KEY (user_id)
        REFERENCES users(user_id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_bookings_court FOREIGN KEY (court_id)
        REFERENCES courts(court_id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE payments (
    payment_id INT NOT NULL AUTO_INCREMENT,
    booking_id INT NOT NULL,
    amount_paid DECIMAL(10,2) NOT NULL,
    payment_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    payment_method VARCHAR(50) NULL,
    transaction_reference VARCHAR(100) NULL,
    PRIMARY KEY (payment_id),
    CONSTRAINT fk_payments_booking FOREIGN KEY (booking_id)
        REFERENCES bookings(booking_id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE pricing_slots (
    slot_id INT NOT NULL AUTO_INCREMENT,
    slot_name VARCHAR(50) NOT NULL,
    start_hour INT NOT NULL,
    end_hour INT NOT NULL,
    day_type VARCHAR(20) NOT NULL,
    multiplier DECIMAL(3,2) NOT NULL DEFAULT 1.00,
    is_peak TINYINT(1) NOT NULL DEFAULT 0,
    description TEXT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    PRIMARY KEY (slot_id),
    UNIQUE KEY uq_pricing_slot (start_hour, end_hour, day_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE court_pricing (
    id INT NOT NULL AUTO_INCREMENT,
    court_id INT NOT NULL,
    slot_id INT NOT NULL,
    custom_price DECIMAL(10,2) NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    PRIMARY KEY (id),
    UNIQUE KEY uq_court_pricing (court_id, slot_id),
    CONSTRAINT fk_court_pricing_court FOREIGN KEY (court_id)
        REFERENCES courts(court_id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_court_pricing_slot FOREIGN KEY (slot_id)
        REFERENCES pricing_slots(slot_id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
