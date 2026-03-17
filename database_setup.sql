-- =============================================
-- TMA Operations 360 — PostgreSQL Database Setup
-- Run this in pgAdmin Query Tool
-- =============================================

-- 1. Create the database (run this separately first, or create via pgAdmin UI)
-- CREATE DATABASE tmabulk360;

-- 2. Connect to tmabulk360 database, then run the following:

-- Users table
CREATE TABLE IF NOT EXISTS users (
    id SERIAL PRIMARY KEY,
    username VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    ship_assigned VARCHAR(200) DEFAULT NULL,
    language VARCHAR(5) NOT NULL DEFAULT 'en',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- Insert default admin user (password: Admin)
INSERT INTO users (username, password, ship_assigned, language)
VALUES ('Admin', '$2y$10$YfWxPExGg1K3yJgFqbMCbuJvKPkMnSm4UXuBNG7GnwFq5RqzJxOaW', NULL, 'en')
ON CONFLICT (username) DO NOTHING;

-- Vessels table
CREATE TABLE IF NOT EXISTS vessels (
    id SERIAL PRIMARY KEY,
    name VARCHAR(200) NOT NULL,
    pool_type VARCHAR(50) NOT NULL DEFAULT 'TMA Bulk Pool',
    dwt DECIMAL(10,2) DEFAULT NULL,
    draft DECIMAL(6,2) DEFAULT NULL,
    built INTEGER DEFAULT NULL,
    yard VARCHAR(200) DEFAULT NULL,
    grain DECIMAL(10,2) DEFAULT NULL,
    bale DECIMAL(10,2) DEFAULT NULL,
    cranes VARCHAR(300) DEFAULT NULL,
    has_semi_box BOOLEAN NOT NULL DEFAULT FALSE,
    has_open_hatch BOOLEAN NOT NULL DEFAULT FALSE,
    has_electric_vent BOOLEAN NOT NULL DEFAULT FALSE,
    has_a60 BOOLEAN NOT NULL DEFAULT FALSE,
    has_grabber BOOLEAN NOT NULL DEFAULT FALSE,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_vessels_pool ON vessels (pool_type);

-- Inventory submissions table
CREATE TABLE IF NOT EXISTS inventory_submissions (
    id SERIAL PRIMARY KEY,
    username VARCHAR(100) NOT NULL,
    item_no INTEGER NOT NULL,
    item_name VARCHAR(500) NOT NULL,
    qty_requested INTEGER NOT NULL,
    submitted_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- Index for faster history queries
CREATE INDEX idx_submissions_date ON inventory_submissions (submitted_at DESC);
CREATE INDEX idx_submissions_user ON inventory_submissions (username);
