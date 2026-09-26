-- ============================================================
-- NovaCare / HMS Database Schema (PostgreSQL)
-- ============================================================

-- Drop existing views first to avoid dependency conflicts on re-import
DROP VIEW IF EXISTS doctor_profiles CASCADE;
DROP VIEW IF EXISTS patient_profiles CASCADE;
DROP VIEW IF EXISTS admin_profiles CASCADE;

-- ============================================================
-- 1. USERS
-- ============================================================
CREATE TABLE IF NOT EXISTS users (
    id SERIAL PRIMARY KEY,
    email VARCHAR(150) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role VARCHAR(10) NOT NULL CHECK (role IN ('patient', 'doctor', 'admin')),
    status VARCHAR(10) NOT NULL DEFAULT 'pending' CHECK (status IN ('pending', 'active', 'rejected')),
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- ============================================================
-- 2. PATIENTS
-- ============================================================
CREATE TABLE IF NOT EXISTS patients (
    user_id INTEGER PRIMARY KEY REFERENCES users (id) ON DELETE CASCADE,
    name VARCHAR(150) NOT NULL,
    phone VARCHAR(20) NOT NULL,
    date_of_birth DATE NOT NULL,
    gender VARCHAR(20) NOT NULL,
    address VARCHAR(255),
    blood_group VARCHAR(5),
    emergency_contact_name VARCHAR(150),
    emergency_contact_phone VARCHAR(20)
);

-- ============================================================
-- 3. DOCTORS
-- ============================================================
CREATE TABLE IF NOT EXISTS doctors (
    user_id INTEGER PRIMARY KEY REFERENCES users (id) ON DELETE CASCADE,
    doctor_login_id VARCHAR(20) UNIQUE,
    name VARCHAR(150) NOT NULL,
    phone VARCHAR(20) NOT NULL,
    specialization VARCHAR(150) NOT NULL,
    qualification VARCHAR(150) NOT NULL,
    license_no VARCHAR(100) UNIQUE NOT NULL,
    experience_years INTEGER NOT NULL DEFAULT 0 CHECK (experience_years >= 0),
    image_path VARCHAR(255),
    verification_status VARCHAR(10) NOT NULL DEFAULT 'pending' CHECK (
        verification_status IN ('pending', 'verified', 'rejected')
    ),
    rejection_reason VARCHAR(255),
    verified_at TIMESTAMPTZ,
    verified_by_admin_id INTEGER REFERENCES users (id) ON DELETE SET NULL
);

-- ============================================================
-- 4. ADMINS
-- ============================================================
CREATE TABLE IF NOT EXISTS admins (
    user_id INTEGER PRIMARY KEY REFERENCES users (id) ON DELETE CASCADE,
    name VARCHAR(150) NOT NULL
);

-- ============================================================
-- 5. HOSPITALS
-- ============================================================
CREATE TABLE IF NOT EXISTS hospitals (
    id SERIAL PRIMARY KEY,
    name VARCHAR(200) NOT NULL,
    address VARCHAR(255) NOT NULL,
    phone VARCHAR(50) NOT NULL,
    email VARCHAR(150) NOT NULL,
    emergency_phone VARCHAR(50),
    departments TEXT,
    description TEXT,
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- ============================================================
-- 6. DOCTOR_HOSPITAL (Doctor ↔ Hospital affiliation)
-- ============================================================
CREATE TABLE IF NOT EXISTS doctor_hospital (
    id SERIAL PRIMARY KEY,
    doctor_id INTEGER NOT NULL REFERENCES doctors (user_id) ON DELETE CASCADE,
    hospital_id INTEGER NOT NULL REFERENCES hospitals (id) ON DELETE CASCADE,
    join_date DATE NOT NULL DEFAULT CURRENT_DATE,
    leave_date DATE,
    status VARCHAR(20) NOT NULL DEFAULT 'active' CHECK (status IN ('active', 'inactive', 'left', 'pending')),
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (doctor_id, hospital_id)
);

-- ============================================================
-- 7. SCHEDULES
-- ============================================================
CREATE TABLE IF NOT EXISTS schedules (
    id SERIAL PRIMARY KEY,
    doctor_hospital_id INTEGER REFERENCES doctor_hospital (id) ON DELETE CASCADE,
    doctor_id INTEGER REFERENCES doctors (user_id) ON DELETE CASCADE,
    hospital_id INTEGER REFERENCES hospitals (id) ON DELETE CASCADE,
    day_of_week VARCHAR(20) NOT NULL CHECK (
        day_of_week IN (
            'Monday', 'Tuesday', 'Wednesday', 'Thursday', 
            'Friday', 'Saturday', 'Sunday'
        )
    ),
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    slot_duration_minutes INTEGER NOT NULL DEFAULT 15 CHECK (slot_duration_minutes > 0),
    max_patients_per_slot INTEGER NOT NULL DEFAULT 1 CHECK (max_patients_per_slot > 0),
    status VARCHAR(25) NOT NULL DEFAULT 'pending_approval' CHECK (
        status IN ('active', 'pending_approval', 'approved', 'rejected', 'archived')
    ),
    change_reason TEXT,
    requested_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    reviewed_at TIMESTAMPTZ,
    approved_at TIMESTAMPTZ,
    approved_by_admin_id INTEGER REFERENCES admins (user_id) ON DELETE SET NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CHECK (end_time > start_time)
);

-- ============================================================
-- 8. APPOINTMENTS
-- ============================================================
CREATE TABLE IF NOT EXISTS appointments (
    id SERIAL PRIMARY KEY,
    appointment_token VARCHAR(50) UNIQUE NOT NULL,
    patient_id INTEGER NOT NULL REFERENCES patients (user_id) ON DELETE CASCADE,
    schedule_id INTEGER REFERENCES schedules (id) ON DELETE SET NULL,
    doctor_id INTEGER REFERENCES doctors (user_id) ON DELETE SET NULL,
    hospital_id INTEGER REFERENCES hospitals (id) ON DELETE SET NULL,
    appointment_date DATE NOT NULL,
    slot_time TIME NOT NULL,
    reason TEXT,
    status VARCHAR(35) NOT NULL DEFAULT 'pending' CHECK (
        status IN (
            'pending',
            'pending_hospital_approval',
            'confirmed',
            'rejected',
            'rejected_by_hospital',
            'completed',
            'cancelled'
        )
    ),
    rejection_reason TEXT,
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- ============================================================
-- COMPATIBILITY VIEWS (for admin & dashboard scripts)
-- ============================================================
CREATE OR REPLACE VIEW doctor_profiles AS
SELECT 
    d.*,
    u.email,
    u.status AS user_status,
    u.created_at AS user_created_at
FROM doctors d
JOIN users u ON u.id = d.user_id;

CREATE OR REPLACE VIEW patient_profiles AS
SELECT 
    p.*,
    u.email,
    u.status AS user_status,
    u.created_at AS user_created_at
FROM patients p
JOIN users u ON u.id = p.user_id;

CREATE OR REPLACE VIEW admin_profiles AS
SELECT 
    a.*,
    u.email,
    u.status AS user_status,
    u.created_at AS user_created_at
FROM admins a
JOIN users u ON u.id = a.user_id;

-- ============================================================
-- INDEXES
-- ============================================================
CREATE INDEX IF NOT EXISTS idx_users_role ON users (role);
CREATE INDEX IF NOT EXISTS idx_users_status ON users (status);
CREATE INDEX IF NOT EXISTS idx_doctors_verification ON doctors (verification_status);
CREATE INDEX IF NOT EXISTS idx_doctor_hospital_doctor ON doctor_hospital (doctor_id);
CREATE INDEX IF NOT EXISTS idx_doctor_hospital_hospital ON doctor_hospital (hospital_id);
CREATE INDEX IF NOT EXISTS idx_schedules_doctor ON schedules (doctor_id);
CREATE INDEX IF NOT EXISTS idx_schedules_hospital ON schedules (hospital_id);
CREATE INDEX IF NOT EXISTS idx_schedules_status ON schedules (status);
CREATE INDEX IF NOT EXISTS idx_appointments_patient ON appointments (patient_id);
CREATE INDEX IF NOT EXISTS idx_appointments_doctor ON appointments (doctor_id);
CREATE INDEX IF NOT EXISTS idx_appointments_hospital ON appointments (hospital_id);
CREATE INDEX IF NOT EXISTS idx_appointments_date ON appointments (appointment_date);
CREATE INDEX IF NOT EXISTS idx_appointments_status ON appointments (status);

-- ============================================================
-- SEED DATA
-- ============================================================

-- 1. Default Admin User (admin@example.com / Password123!)
INSERT INTO users (id, email, password_hash, role, status)
VALUES (
    1,
    'admin@example.com',
    '$2y$12$EdJecX7BBhbwggWDimla8OWtYBeJbNRzNYthzFnOUfTZg4BmBw6DS',
    'admin',
    'active'
)
ON CONFLICT (id) DO NOTHING;

INSERT INTO admins (user_id, name)
VALUES (1, 'NovaCare System Admin')
ON CONFLICT (user_id) DO NOTHING;

-- 2. Partner Hospitals Seed
INSERT INTO hospitals (id, name, address, phone, email, emergency_phone, departments, description, is_active)
VALUES 
    (1, 'Central Care Hospital', '104 Medical Plaza, Downtown', '+1 800 555 0101', 'info@centralcare.org', '+1 800 555 9991', 'Cardiology, Pediatrics, Emergency, Radiology', 'Leading multidisciplinary hospital providing 24/7 acute and specialized care.', TRUE),
    (2, 'Saint Luke Specialty Center', '720 Pine Street, Metro District', '+1 800 555 0102', 'contact@saintluke.org', '+1 800 555 9992', 'Orthopedics, Neurology, Physical Therapy', 'State-of-the-art facility focused on musculoskeletal and neurological rehabilitation.', TRUE),
    (3, 'Mercy Women & Children Hospital', '55 Orchard Blvd, Westside', '+1 800 555 0103', 'care@mercywc.org', '+1 800 555 9993', 'Pediatrics, Gynecology, Obstetrics', 'Dedicated care for mothers, infants, and growing families with top neonatology specialists.', TRUE),
    (4, 'Summit Family Health Center', '330 Ridgeview Way, North Hills', '+1 800 555 0104', 'wellness@summithealth.org', '+1 800 555 9994', 'Primary Care, Internal Medicine, Dermatology', 'Community-centered clinic offering comprehensive routine and preventative health services.', TRUE)
ON CONFLICT (id) DO NOTHING;

-- Sync sequences for serial primary keys
SELECT setval('users_id_seq', (SELECT GREATEST(MAX(id), 1) FROM users));
SELECT setval('hospitals_id_seq', (SELECT GREATEST(MAX(id), 1) FROM hospitals));