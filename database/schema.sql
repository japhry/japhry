-- Advanced Garage Management System - Database Schema
-- Version: 1.0
-- Dialect: MySQL

SET NAMES utf8mb4;
SET time_zone = '+00:00';
SET foreign_key_checks = 0; -- Disable temporarily for table creation order
SET sql_mode = 'NO_AUTO_VALUE_ON_ZERO';

--
-- Table structure for table `branches`
--
DROP TABLE IF EXISTS `branches`;
CREATE TABLE `branches` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(255) NOT NULL,
  `address` TEXT DEFAULT NULL,
  `phone` VARCHAR(50) DEFAULT NULL,
  `email` VARCHAR(255) DEFAULT NULL,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name_unique` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Table structure for table `users`
--
DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `branch_id` INT UNSIGNED DEFAULT NULL, -- Can be NULL for system-wide admins
  `username` VARCHAR(100) NOT NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `email` VARCHAR(255) NOT NULL,
  `full_name` VARCHAR(255) DEFAULT NULL,
  `role` ENUM('system_admin', 'branch_admin', 'mechanic', 'staff', 'customer') NOT NULL DEFAULT 'staff',
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `last_login_at` TIMESTAMP NULL DEFAULT NULL,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username_unique` (`username`),
  UNIQUE KEY `email_unique` (`email`),
  KEY `branch_id_foreign` (`branch_id`),
  CONSTRAINT `users_branch_id_fk` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Table structure for table `customers` (expanded from initial thought of just user role)
--
DROP TABLE IF EXISTS `customers`;
CREATE TABLE `customers` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NULL, -- Optional link to a user account if customer can log in
  `full_name` VARCHAR(255) NOT NULL,
  `phone` VARCHAR(50) DEFAULT NULL,
  `email` VARCHAR(255) DEFAULT NULL,
  `address` TEXT DEFAULT NULL,
  `company_name` VARCHAR(255) DEFAULT NULL,
  `tin_number` VARCHAR(100) DEFAULT NULL,
  `vrn_number` VARCHAR(100) DEFAULT NULL,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `customer_email_unique` (`email`),
  KEY `customer_user_id_fk` (`user_id`),
  CONSTRAINT `customers_user_id_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Table structure for table `vehicles`
--
DROP TABLE IF EXISTS `vehicles`;
CREATE TABLE `vehicles` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `customer_id` INT UNSIGNED NOT NULL,
  `make` VARCHAR(100) DEFAULT NULL,
  `model` VARCHAR(100) DEFAULT NULL,
  `year` YEAR DEFAULT NULL,
  `vin` VARCHAR(100) DEFAULT NULL,
  `license_plate` VARCHAR(50) DEFAULT NULL,
  `color` VARCHAR(50) DEFAULT NULL,
  `notes` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `vin_unique` (`vin`),
  UNIQUE KEY `license_plate_unique` (`license_plate`),
  KEY `vehicle_customer_id_fk` (`customer_id`),
  CONSTRAINT `vehicles_customer_id_fk` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Table structure for table `services` (garage services)
--
DROP TABLE IF EXISTS `services`;
CREATE TABLE `services` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(255) NOT NULL,
  `description` TEXT DEFAULT NULL,
  `default_price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `estimated_time_hours` DECIMAL(5,2) DEFAULT NULL, -- Estimated time in hours
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `service_name_unique` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Table structure for table `inventory_categories`
--
DROP TABLE IF EXISTS `inventory_categories`;
CREATE TABLE `inventory_categories` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(255) NOT NULL,
  `description` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `inv_category_name_unique` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Table structure for table `inventory_items` (parts)
--
DROP TABLE IF EXISTS `inventory_items`;
CREATE TABLE `inventory_items` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `category_id` INT UNSIGNED NULL,
  `branch_id` INT UNSIGNED NULL, -- Can be NULL if item is global, or specific to a branch
  `name` VARCHAR(255) NOT NULL,
  `description` TEXT DEFAULT NULL,
  `sku` VARCHAR(100) DEFAULT NULL, -- Stock Keeping Unit
  `quantity_on_hand` INT NOT NULL DEFAULT 0,
  `unit_price` DECIMAL(10,2) NOT NULL DEFAULT 0.00, -- Selling price
  `cost_price` DECIMAL(10,2) DEFAULT 0.00, -- Purchase price
  `supplier_id` INT UNSIGNED NULL, -- Link to a suppliers table (to be created)
  `reorder_level` INT DEFAULT 0,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `sku_branch_unique` (`sku`, `branch_id`), -- SKU should be unique per branch if branch_id is not NULL
  KEY `item_category_id_fk` (`category_id`),
  KEY `item_branch_id_fk` (`branch_id`),
  -- KEY `item_supplier_id_fk` (`supplier_id`), -- Add when suppliers table exists
  CONSTRAINT `item_category_id_fk` FOREIGN KEY (`category_id`) REFERENCES `inventory_categories` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `item_branch_id_fk` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
  -- CONSTRAINT `item_supplier_id_fk` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


--
-- Table structure for table `job_cards`
--
DROP TABLE IF EXISTS `job_cards`;
CREATE TABLE `job_cards` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `job_card_number` VARCHAR(50) NOT NULL, -- e.g., JC-YYYYMMDD-XXXX
  `branch_id` INT UNSIGNED NOT NULL,
  `vehicle_id` INT UNSIGNED NOT NULL,
  `customer_id` INT UNSIGNED NOT NULL, -- Denormalized for easier access, but vehicle implies customer
  `assigned_mechanic_id` INT UNSIGNED DEFAULT NULL, -- User with 'mechanic' role
  `status` ENUM('pending_approval', 'approved', 'in_progress', 'awaiting_parts', 'completed', 'invoiced', 'paid', 'cancelled') NOT NULL DEFAULT 'pending_approval',
  `date_received` DATE NOT NULL,
  `date_promised_completion` DATE DEFAULT NULL,
  `date_actual_completion` DATE DEFAULT NULL,
  `customer_complaints` TEXT DEFAULT NULL, -- What the customer reported
  `mechanic_findings` TEXT DEFAULT NULL, -- What the mechanic found
  `estimated_cost` DECIMAL(12,2) DEFAULT NULL,
  `actual_cost` DECIMAL(12,2) DEFAULT NULL,
  `payment_status` ENUM('unpaid', 'partially_paid', 'paid') NOT NULL DEFAULT 'unpaid',
  `internal_notes` TEXT DEFAULT NULL,
  `created_by_user_id` INT UNSIGNED NOT NULL,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `job_card_number_unique` (`job_card_number`),
  KEY `jc_branch_id_fk` (`branch_id`),
  KEY `jc_vehicle_id_fk` (`vehicle_id`),
  KEY `jc_customer_id_fk` (`customer_id`),
  KEY `jc_mechanic_id_fk` (`assigned_mechanic_id`),
  KEY `jc_created_by_user_id_fk` (`created_by_user_id`),
  CONSTRAINT `jc_branch_id_fk` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON UPDATE CASCADE, -- No DELETE to preserve job card history
  CONSTRAINT `jc_vehicle_id_fk` FOREIGN KEY (`vehicle_id`) REFERENCES `vehicles` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `jc_customer_id_fk` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `jc_mechanic_id_fk` FOREIGN KEY (`assigned_mechanic_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `jc_created_by_user_id_fk` FOREIGN KEY (`created_by_user_id`) REFERENCES `users` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Table structure for table `job_card_services` (Services performed in a job card)
--
DROP TABLE IF EXISTS `job_card_services`;
CREATE TABLE `job_card_services` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `job_card_id` INT UNSIGNED NOT NULL,
  `service_id` INT UNSIGNED NOT NULL,
  `description_override` VARCHAR(255) DEFAULT NULL, -- If service description needs to be modified for this job
  `quantity` DECIMAL(8,2) NOT NULL DEFAULT 1.00,
  `unit_price` DECIMAL(10,2) NOT NULL, -- Price at the time of adding to job card
  `total_price` DECIMAL(12,2) NOT NULL, -- quantity * unit_price
  `notes` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `jcs_job_card_id_fk` (`job_card_id`),
  KEY `jcs_service_id_fk` (`service_id`),
  CONSTRAINT `jcs_job_card_id_fk` FOREIGN KEY (`job_card_id`) REFERENCES `job_cards` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `jcs_service_id_fk` FOREIGN KEY (`service_id`) REFERENCES `services` (`id`) ON UPDATE CASCADE -- No DELETE to preserve history
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Table structure for table `job_card_parts` (Parts used in a job card)
--
DROP TABLE IF EXISTS `job_card_parts`;
CREATE TABLE `job_card_parts` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `job_card_id` INT UNSIGNED NOT NULL,
  `inventory_item_id` INT UNSIGNED NOT NULL,
  `description_override` VARCHAR(255) DEFAULT NULL, -- If part description needs to be modified
  `quantity_used` INT NOT NULL,
  `unit_price` DECIMAL(10,2) NOT NULL, -- Price at the time of adding to job card
  `total_price` DECIMAL(12,2) NOT NULL, -- quantity_used * unit_price
  `notes` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `jcp_job_card_id_fk` (`job_card_id`),
  KEY `jcp_inventory_item_id_fk` (`inventory_item_id`),
  CONSTRAINT `jcp_job_card_id_fk` FOREIGN KEY (`job_card_id`) REFERENCES `job_cards` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `jcp_inventory_item_id_fk` FOREIGN KEY (`inventory_item_id`) REFERENCES `inventory_items` (`id`) ON UPDATE CASCADE -- No DELETE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Table structure for table `quotations`
--
DROP TABLE IF EXISTS `quotations`;
CREATE TABLE `quotations` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `quotation_number` VARCHAR(50) NOT NULL, -- e.g., Q-YYYYMMDD-XXXX
  `branch_id` INT UNSIGNED NOT NULL,
  `customer_id` INT UNSIGNED NOT NULL,
  `vehicle_id` INT UNSIGNED NULL, -- Optional, if quote is for a specific vehicle
  `date_issued` DATE NOT NULL,
  `valid_until_date` DATE DEFAULT NULL,
  `status` ENUM('draft', 'sent', 'accepted', 'rejected', 'expired') NOT NULL DEFAULT 'draft',
  `sub_total` DECIMAL(12,2) DEFAULT 0.00,
  `discount_amount` DECIMAL(12,2) DEFAULT 0.00,
  `tax_amount` DECIMAL(12,2) DEFAULT 0.00,
  `total_amount` DECIMAL(12,2) DEFAULT 0.00,
  `terms_and_conditions` TEXT DEFAULT NULL,
  `notes` TEXT DEFAULT NULL,
  `created_by_user_id` INT UNSIGNED NOT NULL,
  `job_card_id` INT UNSIGNED NULL, -- If this quotation was converted to a job card
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `quotation_number_unique` (`quotation_number`),
  KEY `q_branch_id_fk` (`branch_id`),
  KEY `q_customer_id_fk` (`customer_id`),
  KEY `q_vehicle_id_fk` (`vehicle_id`),
  KEY `q_created_by_user_id_fk` (`created_by_user_id`),
  KEY `q_job_card_id_fk` (`job_card_id`),
  CONSTRAINT `q_branch_id_fk` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`),
  CONSTRAINT `q_customer_id_fk` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`),
  CONSTRAINT `q_vehicle_id_fk` FOREIGN KEY (`vehicle_id`) REFERENCES `vehicles` (`id`),
  CONSTRAINT `q_created_by_user_id_fk` FOREIGN KEY (`created_by_user_id`) REFERENCES `users` (`id`),
  CONSTRAINT `q_job_card_id_fk` FOREIGN KEY (`job_card_id`) REFERENCES `job_cards` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Table structure for table `quotation_items` (Services or parts in a quotation)
--
DROP TABLE IF EXISTS `quotation_items`;
CREATE TABLE `quotation_items` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `quotation_id` INT UNSIGNED NOT NULL,
  `item_type` ENUM('service', 'part') NOT NULL,
  `item_id` INT UNSIGNED NOT NULL, -- Corresponds to service_id or inventory_item_id
  `description` VARCHAR(255) NOT NULL, -- Copied from service/part, can be overridden
  `quantity` DECIMAL(8,2) NOT NULL,
  `unit_price` DECIMAL(10,2) NOT NULL,
  `total_price` DECIMAL(12,2) NOT NULL,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `qi_quotation_id_fk` (`quotation_id`),
  CONSTRAINT `qi_quotation_id_fk` FOREIGN KEY (`quotation_id`) REFERENCES `quotations` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


--
-- Table structure for table `invoices`
--
DROP TABLE IF EXISTS `invoices`;
CREATE TABLE `invoices` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `invoice_number` VARCHAR(50) NOT NULL, -- e.g., INV-YYYYMMDD-XXXX
  `job_card_id` INT UNSIGNED NULL, -- Can be NULL if invoice is not from a job card (e.g. direct part sale)
  `quotation_id` INT UNSIGNED NULL, -- If invoice generated from a quotation
  `branch_id` INT UNSIGNED NOT NULL,
  `customer_id` INT UNSIGNED NOT NULL,
  `date_issued` DATE NOT NULL,
  `date_due` DATE DEFAULT NULL,
  `status` ENUM('draft', 'sent', 'paid', 'partially_paid', 'overdue', 'cancelled', 'void') NOT NULL DEFAULT 'draft',
  `sub_total` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `discount_type` ENUM('percentage', 'fixed') DEFAULT NULL,
  `discount_value` DECIMAL(10,2) DEFAULT 0.00,
  `discount_amount` DECIMAL(12,2) DEFAULT 0.00,
  `tax_rate_percentage` DECIMAL(5,2) DEFAULT 0.00, -- e.g., 18.00 for 18% VAT
  `tax_amount` DECIMAL(12,2) DEFAULT 0.00,
  `total_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `amount_paid` DECIMAL(12,2) DEFAULT 0.00,
  `balance_due` DECIMAL(12,2) GENERATED ALWAYS AS (`total_amount` - `amount_paid`) STORED,
  `payment_terms` TEXT DEFAULT NULL,
  `notes_to_customer` TEXT DEFAULT NULL,
  `internal_notes` TEXT DEFAULT NULL,
  `created_by_user_id` INT UNSIGNED NOT NULL,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `invoice_number_unique` (`invoice_number`),
  KEY `inv_job_card_id_fk` (`job_card_id`),
  KEY `inv_quotation_id_fk` (`quotation_id`),
  KEY `inv_branch_id_fk` (`branch_id`),
  KEY `inv_customer_id_fk` (`customer_id`),
  KEY `inv_created_by_user_id_fk` (`created_by_user_id`),
  CONSTRAINT `inv_job_card_id_fk` FOREIGN KEY (`job_card_id`) REFERENCES `job_cards` (`id`) ON DELETE SET NULL,
  CONSTRAINT `inv_quotation_id_fk` FOREIGN KEY (`quotation_id`) REFERENCES `quotations` (`id`) ON DELETE SET NULL,
  CONSTRAINT `inv_branch_id_fk` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`),
  CONSTRAINT `inv_customer_id_fk` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`),
  CONSTRAINT `inv_created_by_user_id_fk` FOREIGN KEY (`created_by_user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Table structure for table `invoice_items`
--
DROP TABLE IF EXISTS `invoice_items`;
CREATE TABLE `invoice_items` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `invoice_id` INT UNSIGNED NOT NULL,
  `item_type` ENUM('service', 'part', 'misc') NOT NULL,
  `item_id` INT UNSIGNED NULL, -- Corresponds to service_id or inventory_item_id, NULL for misc
  `description` VARCHAR(255) NOT NULL,
  `quantity` DECIMAL(8,2) NOT NULL,
  `unit_price` DECIMAL(10,2) NOT NULL,
  `sub_total` DECIMAL(12,2) NOT NULL, -- quantity * unit_price
  `discount_amount` DECIMAL(10,2) DEFAULT 0.00,
  `tax_amount` DECIMAL(10,2) DEFAULT 0.00,
  `total_price` DECIMAL(12,2) NOT NULL, -- sub_total - discount + tax
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ii_invoice_id_fk` (`invoice_id`),
  CONSTRAINT `ii_invoice_id_fk` FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Table structure for table `payments`
--
DROP TABLE IF EXISTS `payments`;
CREATE TABLE `payments` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `invoice_id` INT UNSIGNED NOT NULL,
  `payment_date` DATE NOT NULL,
  `amount_paid` DECIMAL(12,2) NOT NULL,
  `payment_method` ENUM('cash', 'credit_card', 'bank_transfer', 'cheque', 'mobile_money', 'other') NOT NULL,
  `reference_number` VARCHAR(255) DEFAULT NULL,
  `notes` TEXT DEFAULT NULL,
  `processed_by_user_id` INT UNSIGNED NOT NULL,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `p_invoice_id_fk` (`invoice_id`),
  KEY `p_processed_by_user_id_fk` (`processed_by_user_id`),
  CONSTRAINT `p_invoice_id_fk` FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`id`) ON DELETE RESTRICT, -- Don't delete invoice if payments exist
  CONSTRAINT `p_processed_by_user_id_fk` FOREIGN KEY (`processed_by_user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- More tables to consider for future steps:
-- `suppliers`
-- `purchase_orders`
-- `hr_employees` (more detailed than users)
-- `hr_payroll`
-- `hr_leave_requests`
-- `system_settings`
-- `audit_logs`
-- `notifications`
-- `compliance_documents`
-- `vehicle_service_history` (could be derived from job_cards)

SET foreign_key_checks = 1; -- Re-enable foreign key checks

-- Basic seed data (examples)
INSERT INTO `branches` (`name`, `address`, `phone`, `email`) VALUES
('Main Branch HQ', '123 Garage Street, Capital City', '555-0100', 'main@garage.system'),
('Northside Branch', '456 Auto Avenue, Northtown', '555-0200', 'north@garage.system');

-- System Admin (cannot be deleted easily, has full power)
-- Password: 'adminpassword' (BCRYPT HASH - REPLACE WITH A SECURELY GENERATED ONE)
INSERT INTO `users` (`branch_id`, `username`, `password_hash`, `email`, `full_name`, `role`, `is_active`) VALUES
(NULL, 'sysadmin', '$2y$10$9Q8X3qX7YqZ8W7e6r5c3Buw8n7o5p2a1G.zS4h9J0kL6mN1xO2b8K', 'admin@garage.system', 'System Administrator', 'system_admin', 1);

-- Branch Admin for Main Branch
-- Password: 'branchadminpass'
INSERT INTO `users` (`branch_id`, `username`, `password_hash`, `email`, `full_name`, `role`, `is_active`) VALUES
((SELECT id from branches WHERE name = 'Main Branch HQ'), 'mainadmin', '$2y$10$yJ3K9xP6sL2nB7fA1gH4zOu5kR8tV0wE9uI5oD3bC6xG.N7pY4q/O', 'mainadmin@garage.system', 'Main Branch Admin', 'branch_admin', 1);

-- Mechanic at Main Branch
-- Password: 'mechanicpass'
INSERT INTO `users` (`branch_id`, `username`, `password_hash`, `email`, `full_name`, `role`, `is_active`) VALUES
((SELECT id from branches WHERE name = 'Main Branch HQ'), 'mech01', '$2y$10$sF8gH2dJ5kL9mN3pA7qR1tO.uV6xW0bC4zE8sY2fI5oP.D9gJ3kL6', 'mechanic1@garage.system', 'John Doe (Mechanic)', 'mechanic', 1);

-- Staff at Main Branch
-- Password: 'staffpass'
INSERT INTO `users` (`branch_id`, `username`, `password_hash`, `email`, `full_name`, `role`, `is_active`) VALUES
((SELECT id from branches WHERE name = 'Main Branch HQ'), 'staff01', '$2y$10$aB1cD2eF3gH4jK5lM6nO7p.R8sT9uV0wX1yZ2bA3cE4dF5gH6jK7', 'staff1@garage.system', 'Jane Smith (Staff)', 'staff', 1);

-- Example Customer
INSERT INTO `customers` (`full_name`, `phone`, `email`, `address`, `company_name`, `tin_number`, `vrn_number`) VALUES
('Alice Wonderland', '555-1234', 'alice@example.com', '1 Rabbit Hole Lane', 'Wonderland Transports', '123-456-789', 'WDR-123T');

-- Example Vehicle for Alice
INSERT INTO `vehicles` (`customer_id`, `make`, `model`, `year`, `vin`, `license_plate`, `color`) VALUES
((SELECT id from customers WHERE email = 'alice@example.com'), 'Toyota', 'Corolla', '2020', 'VN123XYZ789ABC', 'T123ABC', 'Blue');

-- Example Services
INSERT INTO `services` (`name`, `default_price`, `description`, `estimated_time_hours`) VALUES
('Standard Oil Change', 50.00, 'Includes up to 5 quarts of conventional oil and standard filter.', 1.0),
('Tire Rotation', 25.00, 'Rotate all four tires.', 0.5),
('Brake Inspection', 30.00, 'Inspect front and rear brakes, measure pads/shoes.', 0.75);

-- Example Inventory Category
INSERT INTO `inventory_categories` (`name`) VALUES ('Filters'), ('Lubricants'), ('Brake Parts');

-- Example Inventory Items for Main Branch
INSERT INTO `inventory_items` (`branch_id`, `category_id`, `name`, `sku`, `quantity_on_hand`, `unit_price`, `cost_price`, `reorder_level`) VALUES
((SELECT id from branches WHERE name = 'Main Branch HQ'), (SELECT id from inventory_categories WHERE name = 'Filters'), 'Oil Filter Bosch 3323', 'BCH-3323', 50, 10.00, 6.50, 10),
((SELECT id from branches WHERE name = 'Main Branch HQ'), (SELECT id from inventory_categories WHERE name = 'Lubricants'), 'Synthetic Oil 5W-30 (1 Qt)', 'SYN-5W30-QT', 100, 8.50, 5.00, 20);

-- Note on Passwords: The included password hashes are for example purposes.
-- In a real application, you would:
-- 1. Have a user registration process where passwords are set.
-- 2. Use PHP's password_hash() function to create these hashes.
-- 3. NEVER store plain text passwords.
-- The example hashes are for:
-- sysadmin: adminpassword
-- mainadmin: branchadminpass
-- mech01: mechanicpass
-- staff01: staffpass
-- It's highly recommended to change these immediately or implement a proper user creation interface.

-- To generate a password hash in PHP for testing:
-- echo password_hash('your_password_here', PASSWORD_DEFAULT);

COMMIT;
