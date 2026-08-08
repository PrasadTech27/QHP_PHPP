-- =========================================================
-- QHP SuperApp Database Setup Script
-- Database: qhp_superapp
-- DBMS: MySQL / MariaDB (LAMP Stack)
-- =========================================================

CREATE DATABASE IF NOT EXISTS `qhp_superapp` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `qhp_superapp`;

-- ---------------------------------------------------------
-- 1. Users Table
-- ---------------------------------------------------------
CREATE TABLE IF NOT EXISTS `users` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `full_name` VARCHAR(100) NOT NULL,
    `email` VARCHAR(100) UNIQUE NOT NULL,
    `phone` VARCHAR(20),
    `password` VARCHAR(255) NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------
-- 2. Orders Table
-- ---------------------------------------------------------
CREATE TABLE IF NOT EXISTS `orders` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `total_amount` DECIMAL(10,2) NOT NULL,
    `payment_method` VARCHAR(50) DEFAULT 'Cash on Delivery',
    `delivery_address` TEXT,
    `status` VARCHAR(50) DEFAULT 'Pending',
    `otp` VARCHAR(10),
    `latitude` DECIMAL(10,8),
    `longitude` DECIMAL(11,8),
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------
-- 3. Order Items Table
-- ---------------------------------------------------------
CREATE TABLE IF NOT EXISTS `order_items` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `order_id` INT NOT NULL,
    `item_name` VARCHAR(150) NOT NULL,
    `price` DECIMAL(10,2) NOT NULL,
    `quantity` INT DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------
-- 4. Delivery Locations Table
-- ---------------------------------------------------------
CREATE TABLE IF NOT EXISTS `delivery_locations` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `order_id` INT NOT NULL,
    `driver_name` VARCHAR(100),
    `driver_phone` VARCHAR(20),
    `current_lat` DECIMAL(10,8),
    `current_lng` DECIMAL(11,8)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------
-- 5. Categories Table
-- ---------------------------------------------------------
CREATE TABLE IF NOT EXISTS `categories` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `icon` VARCHAR(50),
    `description` TEXT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------
-- 6. Slot Bookings Table
-- ---------------------------------------------------------
CREATE TABLE IF NOT EXISTS `slot_bookings` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `category_id` INT NOT NULL,
    `booking_date` DATE NOT NULL,
    `slot_number` INT NOT NULL,
    `amount_paid` DECIMAL(10,2) NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------
-- 7. Addresses Table
-- ---------------------------------------------------------
CREATE TABLE IF NOT EXISTS `addresses` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `address_text` TEXT NOT NULL,
    `is_primary` TINYINT(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------
-- 8. Account Deletion Requests Table
-- ---------------------------------------------------------
CREATE TABLE IF NOT EXISTS `account_deletion_requests` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `user_email` VARCHAR(100) NOT NULL,
    `status` VARCHAR(50) DEFAULT 'pending',
    `requested_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------
-- 9. Lodges Table
-- ---------------------------------------------------------
CREATE TABLE IF NOT EXISTS `lodges` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(255) NOT NULL,
    `location` VARCHAR(255) NOT NULL,
    `price_per_night` DECIMAL(10,2) NOT NULL,
    `image_url` VARCHAR(255) NOT NULL,
    `description` TEXT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------
-- 10. Car Rentals Table
-- ---------------------------------------------------------
CREATE TABLE IF NOT EXISTS `car_rentals` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `car_name` VARCHAR(255) NOT NULL,
    `category` VARCHAR(100) NOT NULL,
    `price_per_day` DECIMAL(10,2) NOT NULL,
    `image_url` VARCHAR(255) NOT NULL,
    `features` VARCHAR(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------
-- 11. Fancy Items Table
-- ---------------------------------------------------------
CREATE TABLE IF NOT EXISTS `fancy_items` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `item_name` VARCHAR(255) NOT NULL,
    `category` VARCHAR(100) NOT NULL,
    `price` DECIMAL(10,2) NOT NULL,
    `image_url` VARCHAR(255) NOT NULL,
    `description` TEXT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------
-- 12. Tourist Guides Table
-- ---------------------------------------------------------
CREATE TABLE IF NOT EXISTS `tourist_guides` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `guide_name` VARCHAR(255) NOT NULL,
    `languages` VARCHAR(255) NOT NULL,
    `experience_years` INT NOT NULL,
    `price_per_day` DECIMAL(10,2) NOT NULL,
    `image_url` VARCHAR(255) NOT NULL,
    `bio` TEXT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------
-- 13. Trip Packages Table
-- ---------------------------------------------------------
CREATE TABLE IF NOT EXISTS `trip_packages` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `package_name` VARCHAR(255) NOT NULL,
    `destination` VARCHAR(255) NOT NULL,
    `duration` VARCHAR(100) NOT NULL,
    `price_per_person` DECIMAL(10,2) NOT NULL,
    `image_url` VARCHAR(255) NOT NULL,
    `description` TEXT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =========================================================
-- INITIAL SEED DATA
-- =========================================================

-- Demo User (Password: password123)
INSERT INTO `users` (`full_name`, `email`, `phone`, `password`) 
VALUES ('John Doe', 'demo@qhp.com', '9876543210', '$2y$10$wE99S3B6lZqfW6sU18A2k.v34eI6QjOqgC22W5Nf1o5b7D0X5A6a2')
ON DUPLICATE KEY UPDATE `id`=`id`;

-- Sample Lodges
INSERT INTO `lodges` (`name`, `location`, `price_per_night`, `image_url`, `description`) VALUES 
('Grand Palace Lodge', 'City Center, Main Road', 1200.00, 'https://images.unsplash.com/photo-1566073771259-6a8506099945?q=80&w=800&auto=format&fit=crop', 'Deluxe AC Rooms with Free Wi-Fi and 24/7 Room Service.'),
('Royal Comfort Stay', 'Station Road, Near Metro', 950.00, 'https://images.unsplash.com/photo-1582719478250-c89cae4dc85b?q=80&w=800&auto=format&fit=crop', 'Affordable and hygienic rooms suitable for family and business travelers.'),
('Green Valley Resort', 'Hill View Avenue', 1800.00, 'https://images.unsplash.com/photo-1596394516093-501ba68a0ba6?q=80&w=800&auto=format&fit=crop', 'Scenic views with premium luxury suites and complimentary breakfast.');

-- Sample Car Rentals
INSERT INTO `car_rentals` (`car_name`, `category`, `price_per_day`, `image_url`, `features`) VALUES 
('Maruti Swift', 'Hatchback', 1500.00, 'https://images.unsplash.com/photo-1541899481282-d53bffe3c355?q=80&w=800&auto=format&fit=crop', '5 Seater • Manual • AC • Petrol'),
('Toyota Innova Crysta', 'SUV', 3200.00, 'https://images.unsplash.com/photo-1533473359331-0135ef1b58bf?q=80&w=800&auto=format&fit=crop', '7 Seater • Diesel • AC • Spacious'),
('Honda City', 'Sedan', 2200.00, 'https://images.unsplash.com/photo-1552519507-da3b142c6e3d?q=80&w=800&auto=format&fit=crop', '5 Seater • Automatic • AC • Luxury Comfort');

-- Sample Fancy Store Items
INSERT INTO `fancy_items` (`item_name`, `category`, `price`, `image_url`, `description`) VALUES 
('Bridal Makeup Kit', 'Cosmetics', 1250.00, 'https://images.unsplash.com/photo-1522337360788-8b13dee7a37e?q=80&w=800&auto=format&fit=crop', 'Complete professional cosmetic kit including lipsticks, palettes, and brushes.'),
('Designer Handbag', 'Accessories', 890.00, 'https://images.unsplash.com/photo-1584917865442-de89df76afd3?q=80&w=800&auto=format&fit=crop', 'Stylish, durable leather handbag suitable for daily use and parties.'),
('Artificial Diamond Necklace', 'Jewellery', 1500.00, 'https://images.unsplash.com/photo-1599643478518-a784e5dc4c8f?q=80&w=800&auto=format&fit=crop', 'Elegant party-wear necklace set with matching earrings.');

-- Sample Tourist Guides
INSERT INTO `tourist_guides` (`guide_name`, `languages`, `experience_years`, `price_per_day`, `image_url`, `bio`) VALUES 
('Rajesh Kumar', 'English, Telugu, Hindi', 6, 800.00, 'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?q=80&w=800&auto=format&fit=crop', 'Expert in historical monuments, local heritage tours, and cultural sightseeing.'),
('Priya Sharma', 'English, French, Hindi', 4, 1000.00, 'https://images.unsplash.com/photo-1573496359142-b8d87734a5a2?q=80&w=800&auto=format&fit=crop', 'Professional city guide with specialization in art galleries, food trails, and museums.'),
('Anand Verma', 'English, Telugu, Tamil', 8, 900.00, 'https://images.unsplash.com/photo-1500648767791-00dcc994a43e?q=80&w=800&auto=format&fit=crop', 'Adventure trekking guide and nature explorer for scenic viewpoints and hill stations.');

-- Sample Trip Packages
INSERT INTO `trip_packages` (`package_name`, `destination`, `duration`, `price_per_person`, `image_url`, `description`) VALUES 
('Goa Beach Escape', 'Goa, India', '3 Days / 2 Nights', 4500.00, 'https://images.unsplash.com/photo-1512343879784-a960bf40e7f2?q=80&w=800&auto=format&fit=crop', 'Enjoy sun-kissed beaches, water sports, and beachside nightlife.'),
('Kashmir Paradise Tour', 'Srinagar & Gulmarg', '5 Days / 4 Nights', 12500.00, 'https://images.unsplash.com/photo-1595815771614-ade9d652a65d?q=80&w=800&auto=format&fit=crop', 'Experience houseboats, snow peaks, gondola rides, and scenic valleys.'),
('Ooty & Kodaikanal Hills', 'Tamil Nadu, India', '4 Days / 3 Nights', 7800.00, 'https://images.unsplash.com/photo-1589182373726-e4f658ab50f0?q=80&w=800&auto=format&fit=crop', 'Explore botanical gardens, boat houses, and mist-covered green hills.');
