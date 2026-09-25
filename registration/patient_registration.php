<?php
require_once __DIR__ . '/../auth/auth.php';
require_once __DIR__ . '/../database/db.php';
require_once __DIR__ . '/../include/function.php';

// Redirect if already logged in
if (is_logged_in()) {
    $role = current_role();
    if ($role === 'doctor') {
        redirect('../doctor/dashboard.php');
    } elseif ($role === 'admin') {
        redirect('../admin/dashboard.php');
    } else {
        redirect('../patient/dashboard.php');
    }
}

$errorMessage = '';
$formData = [
    'name' => '',
    'email' => '',
    'phone' => '',
    'date_of_birth' => '',
    'gender' => '',
    'blood_group' => '',
    'address' => '',
    'emergency_name' => '',
    'emergency_phone' => ''
];

// Handle Registration Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $formData['name'] = trim($_POST['name'] ?? '');
    $formData['email'] = trim($_POST['email'] ?? '');
    $formData['phone'] = trim($_POST['phone'] ?? '');
    $formData['date_of_birth'] = trim($_POST['date_of_birth'] ?? '');
    $formData['gender'] = trim($_POST['gender'] ?? '');
    $formData['blood_group'] = trim($_POST['blood_group'] ?? '');
    $formData['address'] = trim($_POST['address'] ?? '');
    $formData['emergency_name'] = trim($_POST['emergency_name'] ?? '');
    $formData['emergency_phone'] = trim($_POST['emergency_phone'] ?? '');

    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    // Validation
    if (empty($formData['name']) || empty($formData['email']) || empty($formData['phone']) || empty($formData['date_of_birth']) || empty($formData['gender'])) {
        $errorMessage = 'Please fill in all required personal information fields.';
    } elseif (!filter_var($formData['email'], FILTER_VALIDATE_EMAIL)) {
        $errorMessage = 'Please enter a valid email address.';
    } elseif (strlen($password) < 6) {
        $errorMessage = 'Password must be at least 6 characters long.';
    } elseif ($password !== $confirmPassword) {
        $errorMessage = 'Passwords do not match.';
    } else {
        try {
            $db = getDB();

            // Check if email already registered
            $checkStmt = $db->prepare("SELECT 1 FROM users WHERE LOWER(email) = LOWER(?) LIMIT 1");
            $checkStmt->execute([$formData['email']]);
            if ($checkStmt->fetch()) {
                $errorMessage = 'An account with this email address already exists. Please sign in instead.';
            } else {
                $db->beginTransaction();

                // 1. Create user record
                $passwordHash = password_hash($password, PASSWORD_DEFAULT);
                $userStmt = $db->prepare("
                    INSERT INTO users (email, password_hash, role, status, created_at)
                    VALUES (?, ?, 'patient', 'active', CURRENT_TIMESTAMP)
                    RETURNING id
                ");
                $userStmt->execute([$formData['email'], $passwordHash]);
                $userId = (int) $userStmt->fetchColumn();

                // 2. Create patient profile
                $patientStmt = $db->prepare("
                    INSERT INTO patients (
                        user_id, name, phone, date_of_birth, gender, 
                        address, blood_group, emergency_contact_name, emergency_contact_phone
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $patientStmt->execute([
                    $userId,
                    $formData['name'],
                    $formData['phone'],
                    $formData['date_of_birth'],
                    $formData['gender'],
                    $formData['address'] ?: null,
                    $formData['blood_group'] ?: null,
                    $formData['emergency_name'] ?: null,
                    $formData['emergency_phone'] ?: null
                ]);

                $db->commit();

                // Log the patient in
                login_user($userId, 'patient');
                set_flash('success', 'Welcome to NovaCare! Your patient profile has been created successfully.');

                redirect('../patient/dashboard.php');
            }

        } catch (Throwable $e) {
            if (isset($db) && $db->inTransaction()) {
                $db->rollBack();
            }
            error_log('Patient registration error: ' . $e->getMessage());
            $errorMessage = 'An unexpected error occurred during registration. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patient Registration - NovaCare</title>
    <link rel="stylesheet" href="../assets/css/auth.css">
</head>
<body>

    <!-- Subnav / Back link -->
    <div class="auth-subnav">
        <a href="account_selection.php" class="auth-back-link">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
            Back to account selection
        </a>
    </div>

    <!-- Centered Form Container -->
    <main class="auth-main-container">
        <div class="auth-centered-container">
            <div class="auth-card">
                <span class="auth-card-tag">Patient Registration</span>
                <h1 class="auth-card-title">Create your secure profile.</h1>
                <p class="auth-card-subtitle">A few essential details help us personalize your appointment booking and care experience.</p>

                <!-- Feedback Alert -->
                <?php if (!empty($errorMessage)): ?>
                    <div class="auth-alert error">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                        <span><?= htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8') ?></span>
                    </div>
                <?php endif; ?>

                <form method="POST" action="patient_registration.php" autocomplete="on">
                    
                    <!-- Full Name -->
                    <div class="form-group">
                        <label for="name" class="form-label">Full Name *</label>
                        <div class="input-wrap">
                            <span class="input-icon-left">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                                    <circle cx="12" cy="7" r="4"/>
                                </svg>
                            </span>
                            <input type="text" id="name" name="name" class="form-input" 
                                   placeholder="e.g. Sarah Jenkins"
                                   value="<?= htmlspecialchars($formData['name'], ENT_QUOTES, 'UTF-8') ?>" required>
                        </div>
                    </div>

                    <!-- Email & Phone Row -->
                    <div class="form-row-2">
                        <div class="form-group">
                            <label for="email" class="form-label">Email Address *</label>
                            <div class="input-wrap">
                                <span class="input-icon-left">
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/>
                                        <polyline points="22,6 12,13 2,6"/>
                                    </svg>
                                </span>
                                <input type="email" id="email" name="email" class="form-input" 
                                       placeholder="sarah@example.com"
                                       value="<?= htmlspecialchars($formData['email'], ENT_QUOTES, 'UTF-8') ?>" required>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="phone" class="form-label">Phone Number *</label>
                            <div class="input-wrap">
                                <span class="input-icon-left">
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/>
                                    </svg>
                                </span>
                                <input type="tel" id="phone" name="phone" class="form-input" 
                                       placeholder="+1 (555) 000-0000"
                                       value="<?= htmlspecialchars($formData['phone'], ENT_QUOTES, 'UTF-8') ?>" required>
                            </div>
                        </div>
                    </div>

                    <!-- Date of Birth & Gender Row -->
                    <div class="form-row-2">
                        <div class="form-group">
                            <label for="date_of_birth" class="form-label">Date of Birth *</label>
                            <div class="input-wrap">
                                <input type="date" id="date_of_birth" name="date_of_birth" class="form-input no-icon" 
                                       value="<?= htmlspecialchars($formData['date_of_birth'], ENT_QUOTES, 'UTF-8') ?>" required>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="gender" class="form-label">Gender *</label>
                            <div class="input-wrap">
                                <select id="gender" name="gender" class="form-select no-icon" required>
                                    <option value="">Select gender</option>
                                    <option value="Female" <?= $formData['gender'] === 'Female' ? 'selected' : '' ?>>Female</option>
                                    <option value="Male" <?= $formData['gender'] === 'Male' ? 'selected' : '' ?>>Male</option>
                                    <option value="Other" <?= $formData['gender'] === 'Other' ? 'selected' : '' ?>>Other</option>
                                    <option value="Prefer not to say" <?= $formData['gender'] === 'Prefer not to say' ? 'selected' : '' ?>>Prefer not to say</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Blood Group & Address Row -->
                    <div class="form-row-2">
                        <div class="form-group">
                            <label for="blood_group" class="form-label">Blood Group</label>
                            <div class="input-wrap">
                                <select id="blood_group" name="blood_group" class="form-select no-icon">
                                    <option value="">Unknown / Optional</option>
                                    <option value="A+" <?= $formData['blood_group'] === 'A+' ? 'selected' : '' ?>>A+</option>
                                    <option value="A-" <?= $formData['blood_group'] === 'A-' ? 'selected' : '' ?>>A-</option>
                                    <option value="B+" <?= $formData['blood_group'] === 'B+' ? 'selected' : '' ?>>B+</option>
                                    <option value="B-" <?= $formData['blood_group'] === 'B-' ? 'selected' : '' ?>>B-</option>
                                    <option value="AB+" <?= $formData['blood_group'] === 'AB+' ? 'selected' : '' ?>>AB+</option>
                                    <option value="AB-" <?= $formData['blood_group'] === 'AB-' ? 'selected' : '' ?>>AB-</option>
                                    <option value="O+" <?= $formData['blood_group'] === 'O+' ? 'selected' : '' ?>>O+</option>
                                    <option value="O-" <?= $formData['blood_group'] === 'O-' ? 'selected' : '' ?>>O-</option>
                                </select>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="address" class="form-label">Home Address</label>
                            <div class="input-wrap">
                                <input type="text" id="address" name="address" class="form-input no-icon" 
                                       placeholder="City, State"
                                       value="<?= htmlspecialchars($formData['address'], ENT_QUOTES, 'UTF-8') ?>">
                            </div>
                        </div>
                    </div>

                    <!-- Emergency Contact Row -->
                    <div class="form-row-2">
                        <div class="form-group">
                            <label for="emergency_name" class="form-label">Emergency Contact Name</label>
                            <div class="input-wrap">
                                <input type="text" id="emergency_name" name="emergency_name" class="form-input no-icon" 
                                       placeholder="Contact person"
                                       value="<?= htmlspecialchars($formData['emergency_name'], ENT_QUOTES, 'UTF-8') ?>">
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="emergency_phone" class="form-label">Emergency Phone</label>
                            <div class="input-wrap">
                                <input type="tel" id="emergency_phone" name="emergency_phone" class="form-input no-icon" 
                                       placeholder="Emergency phone number"
                                       value="<?= htmlspecialchars($formData['emergency_phone'], ENT_QUOTES, 'UTF-8') ?>">
                            </div>
                        </div>
                    </div>

                    <!-- Password & Confirm Password Row -->
                    <div class="form-row-2">
                        <div class="form-group">
                            <label for="password" class="form-label">Create Password *</label>
                            <div class="input-wrap">
                                <span class="input-icon-left">
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
                                        <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                                    </svg>
                                </span>
                                <input type="password" id="password" name="password" class="form-input" 
                                       placeholder="At least 6 characters" required>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="confirm_password" class="form-label">Confirm Password *</label>
                            <div class="input-wrap">
                                <span class="input-icon-left">
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
                                        <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                                    </svg>
                                </span>
                                <input type="password" id="confirm_password" name="confirm_password" class="form-input" 
                                       placeholder="Re-enter password" required>
                            </div>
                        </div>
                    </div>

                    <!-- Submit Button -->
                    <button type="submit" class="btn-auth-submit" style="margin-top: 10px;">
                        <span>Complete Registration</span>
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
                    </button>
                </form>

                <p class="auth-card-foot">
                    Already have an account? <a href="../login.php">Sign in here</a>
                </p>
            </div>
        </div>
    </main>

    <!-- Dark Footer Strip -->
    <footer class="auth-dark-footer">
        <div class="auth-dark-footer-inner">
            <div class="footer-brand-logo">
                <span>+</span>NovaCare
            </div>

            <div class="footer-support-text">
                Need help? +1 800 682 2273 &bull; Mon&ndash;Sat, 9am&ndash;8pm
            </div>

            <div class="footer-badges">
                <span>Secure care coordination</span>
                <span>&bull;</span>
                <span>HIPAA-ready</span>
            </div>
        </div>
    </footer>

</body>
</html>
