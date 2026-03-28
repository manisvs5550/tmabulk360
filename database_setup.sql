-- =============================================
-- TMA Operations 360 — MySQL Database Setup
-- Run this in phpMyAdmin or MySQL Workbench
-- =============================================

-- 1. Create the database
CREATE DATABASE IF NOT EXISTS tmabulk360 CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE tmabulk360;

-- 2. Users table
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL,
    contact_number VARCHAR(50) DEFAULT NULL,
    ship_assigned VARCHAR(200) NOT NULL,
    language VARCHAR(5) NOT NULL DEFAULT 'en',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert default admin user (password: Admin)
INSERT IGNORE INTO users (username, password, ship_assigned, language)
VALUES ('Admin', '$2y$10$5ZL9ZA1LM5yQoUJJlilxbuCAkzMzo0Opc5Lr0mlD1hHz1cg8NTd9K', NULL, 'en');

-- 3. Vessels table
CREATE TABLE IF NOT EXISTS vessels (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(200) NOT NULL UNIQUE,
    pool_type VARCHAR(50) NOT NULL DEFAULT 'TMA Bulk Pool',
    dwt DECIMAL(10,2) DEFAULT NULL,
    draft DECIMAL(6,2) DEFAULT NULL,
    built INT DEFAULT NULL,
    yard VARCHAR(200) DEFAULT NULL,
    grain DECIMAL(10,2) DEFAULT NULL,
    bale DECIMAL(10,2) DEFAULT NULL,
    cranes VARCHAR(300) DEFAULT NULL,
    has_semi_box TINYINT(1) NOT NULL DEFAULT 0,
    has_open_hatch TINYINT(1) NOT NULL DEFAULT 0,
    has_electric_vent TINYINT(1) NOT NULL DEFAULT 0,
    has_a60 TINYINT(1) NOT NULL DEFAULT 0,
    has_grabber TINYINT(1) NOT NULL DEFAULT 0,
    general_arrangement VARCHAR(500) DEFAULT NULL,
    capacity_plan VARCHAR(500) DEFAULT NULL,
    time_charter VARCHAR(500) DEFAULT NULL,
    voyage_charter VARCHAR(500) DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_vessels_pool (pool_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Seed vessel data
INSERT IGNORE INTO vessels (name, pool_type, dwt, draft, built, yard, grain, bale, cranes, has_semi_box, has_open_hatch, has_electric_vent, has_a60, has_grabber) VALUES
('USUKI',                  'TMA Bulk Pool', 43300, 11.00, 2026, 'Huanghai',       53591, 53591, '4 x 36.0 ts (grabs)', 1, 0, 0, 0, 1),
('BROOMPARK (TC)',         'TMA Bulk Pool', 40552, 10.95, 2023, 'Jiangmen',        50515, 49321, '4 x 30.5 ts (grabs)', 1, 0, 0, 0, 1),
('EVA BRIGHT (TC)',        'TMA Bulk Pool', 40500, 10.95, 2023, 'Jiangmen',        50515, 49231, '4 x 30.5 ts (grabs)', 1, 0, 0, 0, 1),
('EVA CARLTON (TC)',       'TMA Bulk Pool', 40500, 10.95, 2023, 'Jiangmen',        50515, 49231, '4 x 30.5 ts (grabs)', 1, 0, 0, 0, 1),
('PARABOLICA (TC)',        'TMA Bulk Pool', 40387, 10.95, 2024, 'Jiangmen',        50515, 49321, '4 x 30.5 ts',         0, 0, 0, 0, 0),
('ULTRA SILVA (TC)',       'TMA Bulk Pool', 40213, 10.80, 2021, 'Jiangmen',        50313, 49124, '4 x 30.0 ts (grabs)', 1, 0, 0, 0, 1),
('WESTERN DONCASTER (TC)', 'TMA Bulk Pool', 39460, 10.62, 2019, 'Jiangmen',        49207, 48038, '4 x 30.5 ts (grabs)', 1, 0, 0, 0, 1),
('JASMUND',                'TMA Bulk Pool', 39234, 10.60, 2015, 'Taizhou Kouan',   47466, 46081, '2 x 36.0 ts + 2 x 50.0 ts', 1, 0, 1, 0, 1),
('SUMATRA',                'TMA Bulk Pool', 38943, 10.50, 2016, 'Huanghai',        50852, 48601, '4 x 35.0 ts (grabs)', 1, 0, 1, 0, 1),
('LEFKADA',                'TMA Bulk Pool', 37951, 10.53, 2016, 'Imabari',         46994, 45238, '4 x 30.5 ts',         0, 0, 0, 0, 0),
('GLENPARK (TC)',          'TMA Bulk Pool', 37839, 10.02, 2017, 'Naikai',          47125, 46207, '4 x 30.0 ts',         0, 0, 0, 0, 0),
('MOUNTPARK (TC)',         'TMA Bulk Pool', 37839, 10.02, 2016, 'Naikai',          47125, 46207, '4 x 30.0 ts',         0, 0, 0, 0, 0),
('ANDALUCIA',              'TMA Bulk Pool', 37430, 10.65, 2013, 'Guoyu',           46733, 45654, '4 x 30.0 ts',         0, 0, 0, 0, 0),
('CAPE GULL (TC)',         'TMA Bulk Pool', 36320, 10.71, 2013, 'Shikoku',         47089, 45414, '4 x 30.5 ts',         0, 0, 0, 0, 0),
('ABYSSINIAN',             'TMA Bulk Pool', 36064, 10.30, 2014, 'CSC Jingling',    46731, 45654, '4 x 30.5 ts',         0, 0, 0, 0, 0),
('ABTENAUER',              'TMA Bulk Pool', 36063, 10.30, 2014, 'CSC Jingling',    46731, 45654, '4 x 30.5 ts',         0, 0, 0, 0, 0),
('ASTURCON',               'TMA Bulk Pool', 36063, 10.30, 2014, 'CSC Jingling',    46731, 45654, '4 x 30.5 ts',         0, 0, 0, 0, 0),
('AZTECA',                 'TMA Bulk Pool', 36063, 10.30, 2014, 'CSC Jingling',    46731, 45654, '4 x 30.5 ts',         0, 0, 0, 0, 0),
('ARDENNES',               'TMA Bulk Pool', 36062, 10.30, 2013, 'CSC Jingling',    46731, 45654, '4 x 30.5 ts',         0, 0, 0, 0, 0),
('NORDIC MALMOE',          'TMA Bulk Pool', 35842, 10.34, 2012, 'Nantong',         46762, 45683, '4 x 30.0 ts',         0, 0, 0, 0, 0),
('AMELIE',                 'TMA Bulk Pool', 35783, 10.31, 2013, 'Guoyu',           46730, 45669, '4 x 30.0 ts',         0, 0, 0, 0, 0);

-- 4. Inventory items table
CREATE TABLE IF NOT EXISTS inventory_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    item_no INT NOT NULL UNIQUE,
    item_name VARCHAR(500) NOT NULL,
    min_qty INT DEFAULT NULL,
    remarks VARCHAR(500) DEFAULT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_inv_items_no (item_no)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Seed inventory items
INSERT IGNORE INTO inventory_items (item_no, item_name, min_qty, remarks) VALUES
(1, 'HIGH PRESSURE WATER JET-200 Bar', 2, '200Bar'),
(2, 'High pressure water jet -500 Bar', NULL, 'Reconditioned Old Equipment with broken nozzle or gun or similar issues'),
(3, 'HIGH PRESSURE WATER JET- SET OF SPARE PARTS FOR PUMPING ELEMENT', 2, NULL),
(4, 'HOLD CLEANING GUN (Combi gun etc)', 2, NULL),
(5, 'STAND FOR HOLD CLEANING GUN', 2, NULL),
(6, 'CHEMICAL APPLICATOR UNIT', 1, 'Air Operated Chemical Pump'),
(7, 'TELESCOPIC POLE FOR REACHING HIGH AREAS BY THE USE OF CHEMICAL APPLICATOR', 1, 'Mention how many meters long'),
(8, 'SPRAY FOAM SYSTEM WITH MINI GUN (CHEMICAL APPLICATION)', 1, NULL),
(9, 'ALUMINIUM/ STEEL SCAFFOLDING TOWER or SIMILAR EQUIPMENT', 1, NULL),
(10, 'MAN CAGE/BASKET/SIMILAR EQUIPMENT LIKE MOVABLE PLATFORMS, LADDER ETC USED TO REACH UPPER PARTS OF CARGO HOLDS BY THE USE OF SHIP\'S CRANE', 1, NULL),
(11, 'WOODEN STAGES', NULL, 'Gondola'),
(12, 'TELESCOPIC LADDER', 1, 'Maximum 6mtrs'),
(13, 'AIRLESS PAINT SPRAY MACHINE', 1, NULL),
(14, 'EXTENSION POLE FOR PAINT SPRAY MACHINE', 1, NULL),
(15, 'HEAVY DUTY DESCALING MACHINES FOR TANK TOPS (Rustibus, Scatol etc)', 2, 'Reconditioned Old Equipment'),
(16, 'PNEUMATIC SCALING HAMMER', 2, NULL),
(17, 'TELESCOPIC POLE', 2, '5mtrs'),
(18, 'FIXED AIR COMPRESSOR (For Deck use)', 1, NULL),
(19, 'ELECTRICAL SUBMERSIBLE PUMP capable of transferring cargo hold wash water from tanktop to overboard or in wash water tank', 1, NULL),
(20, 'WILDEN PUMP (diaphragm air pump) capable of transferring cargo hold wash water from tanktop to overboard or in wash water tank', 1, NULL),
(21, 'CHEMICAL PROTECTION SUIT', 2, NULL),
(22, 'RESPIRATION FACE MASK', 2, NULL),
(23, 'SPARE FILTER FOR FULL FACE MASK', 10, NULL);

-- 5. Inventory submissions table
CREATE TABLE IF NOT EXISTS inventory_submissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    inventory_id VARCHAR(20) NOT NULL,
    ship_id INT DEFAULT NULL,
    username VARCHAR(100) NOT NULL,
    item_no INT NOT NULL,
    item_name VARCHAR(500) NOT NULL,
    qty_requested INT NOT NULL,
    submitted_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_submissions_inv_id (inventory_id),
    INDEX idx_submissions_date (submitted_at DESC),
    INDEX idx_submissions_user (username),
    INDEX idx_submissions_ship (ship_id),
    CONSTRAINT fk_submissions_vessel FOREIGN KEY (ship_id) REFERENCES vessels(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
