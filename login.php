<?php
require_once __DIR__ . '/auth/auth.php';
require_once __DIR__ . '/database/db.php';
require_once __DIR__ . '/include/function.php';

$bookAppointmentUrl = 'patient/appointment.php';

// If user is already logged in, redirect them to their respective dashboard
if (is_logged_in()) {
    $role = current_role();
    if ($role === 'doctor') {
        redirect('doctor/dashboard.php');
    } elseif ($role === 'admin') {
        redirect('admin/dashboard.php');
    } else {
        redirect('patient/dashboard.php');
    }
}

$errorMessage = '';
$successMessage = '';

// Check for flash messages
$flash = get_flash();
if ($flash) {
    if ($flash['type'] === 'error') {
        $errorMessage = $flash['message'];
    } elseif ($flash['type'] === 'success') {
        $successMessage = $flash['message'];
    }
}

$requestedRole = $_POST['role'] ?? ($_GET['role'] ?? 'patient');
$selectedRole = is_string($requestedRole) && in_array($requestedRole, ['patient', 'doctor', 'admin'], true)
    ? $requestedRole
    : 'patient';
$identifierValue = $_POST['identifier'] ?? '';
$identifier = is_string($identifierValue) ? trim($identifierValue) : '';
$redirectValue = $_POST['redirect'] ?? ($_GET['redirect'] ?? '');
$redirectUrl = is_string($redirectValue) ? trim($redirectValue) : '';

// Handle Login Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $passwordValue = $_POST['password'] ?? '';
    $password = is_string($passwordValue) ? $passwordValue : '';
    $now = time();
    $loginAttempts = $_SESSION['login_attempts'] ?? [];
    if (!is_array($loginAttempts)) {
        $loginAttempts = [];
    }
    $loginAttempts = array_values(array_filter(
        $loginAttempts,
        static fn ($attempt): bool => is_int($attempt) && $attempt > $now - 900
    ));
    $_SESSION['login_attempts'] = $loginAttempts;

    if (count($loginAttempts) >= 5) {
        $errorMessage = 'Too many failed sign-in attempts. Please wait 15 minutes and try again.';
    } elseif (empty($identifier) || empty($password)) {
        $errorMessage = 'Please enter both your login identifier and password.';
    } else {
        try {
            $db = getDB();

            if ($selectedRole === 'doctor') {
                // Doctors can log in via email OR doctor_login_id
                $stmt = $db->prepare("
                    SELECT u.id, u.email, u.password_hash, u.role, u.status,
                           d.doctor_login_id, d.verification_status, d.name
                    FROM users u
                    JOIN doctors d ON d.user_id = u.id
                    WHERE (LOWER(u.email) = LOWER(?) OR UPPER(d.doctor_login_id) = UPPER(?))
                    LIMIT 1
                ");
                $stmt->execute([$identifier, $identifier]);
                $user = $stmt->fetch();
            } else {
                // Patients and Admins log in via email
                $stmt = $db->prepare("
                    SELECT u.id, u.email, u.password_hash, u.role, u.status
                    FROM users u
                    WHERE LOWER(u.email) = LOWER(?)
                    LIMIT 1
                ");
                $stmt->execute([$identifier]);
                $user = $stmt->fetch();
            }

            // Universal Admin Fallback: If entered email is an admin, accept under any tab
            if (!$user) {
                $admStmt = $db->prepare("
                    SELECT u.id, u.email, u.password_hash, u.role, u.status
                    FROM users u
                    WHERE LOWER(u.email) = LOWER(?) AND u.role = 'admin'
                    LIMIT 1
                ");
                $admStmt->execute([$identifier]);
                $user = $admStmt->fetch();
            }

            if ($user && password_verify($password, $user['password_hash'])) {
                // Check account verification and active status
                if ($user['role'] === 'doctor' && $user['status'] === 'pending') {
                    $errorMessage = 'Your doctor account is currently pending administrative verification. Please wait for confirmation.';
                } elseif ($user['status'] === 'rejected') {
                    $errorMessage = 'Your account has been rejected. Please contact administration for assistance.';
                } elseif ($user['status'] !== 'active') {
                    $errorMessage = 'Your account is not active. Please contact support.';
                } else {
                    // Valid credentials and active status
                    unset($_SESSION['login_attempts']);
                    login_user((int) $user['id'], $user['role']);

                    if ($user['role'] === 'doctor') {
                        redirect('doctor/dashboard.php');
                    } elseif ($user['role'] === 'admin') {
                        redirect('admin/dashboard.php');
                    } else {
                        $safeRedirect = safe_internal_redirect(
                            $redirectUrl,
                            ['patient/appointment.php', 'patient/dashboard.php']
                        );
                        if ($safeRedirect !== null) {
                            redirect($safeRedirect);
                        }
                        redirect('patient/dashboard.php');
                    }
                }
            } else {
                $loginAttempts[] = $now;
                $_SESSION['login_attempts'] = $loginAttempts;
                $errorMessage = count($loginAttempts) >= 5
                    ? 'Too many failed sign-in attempts. Please wait 15 minutes and try again.'
                    : 'Invalid email/ID or password. Please try again.';
            }

        } catch (Throwable $e) {
            error_log('Login error: ' . $e->getMessage());
            $errorMessage = 'A system error occurred. Please try again later.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign In - NovaCare</title>
    <link rel="stylesheet" href="assets/css/login/auth.css">
</head>
<body>

    <!-- Header Navigation -->
    <header class="auth-navbar">
        <a href="index.php" class="auth-logo">
            <span class="auth-logo-badge">+</span>
            NovaCare
        </a>

        <nav class="auth-nav-links">
            <a href="index.php#home">Home</a>
            <a href="index.php#care">Hospitals</a>
            <a href="index.php#doctors">Doctors</a>
            <a href="index.php#works">How It Works</a>
            <a href="index.php#faq">FAQ</a>
            <a href="index.php#contact">Contact</a>
        </nav>

        <div>
            <a href="<?= $bookAppointmentUrl ?>" class="btn-nav-book">Book Appointment</a>
        </div>
    </header>



    <!-- Main Content: Split Grid Layout -->
    <main class="auth-main-container">
        <div class="auth-split-grid">

            <!-- Left Visual Card -->
            <div class="auth-hero-visual">
                <img src="./assets/img/login.jpg" alt="Doctor and patient consultation" class="auth-hero-bg">
                <div class="auth-hero-overlay"></div>

                <div class="auth-hero-content">
                    <span class="auth-hero-tag">Your care, in one place</span>
                    <h1 class="auth-hero-title">Welcome back to care that feels connected.</h1>
                    <p class="auth-hero-desc">Access appointments, care details, and trusted providers through your private NovaCare account.</p>
                </div>

                <div class="auth-floating-badge">
                    <div class="auth-badge-icon">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
                            <path d="m9 12 2 2 4-4"/>
                        </svg>
                    </div>
                    <div class="auth-badge-text">
                        <h4>Trusted healthcare access</h4>
                        <p>Designed to safeguard your personal information throughout your healthcare journey.</p>
                    </div>
                </div>
            </div>

            <!-- Right White Sign In Card -->
            <div class="auth-card">
                <span class="auth-card-tag">Secure Sign In</span>
                <h2 class="auth-card-title">Welcome back.</h2>
                <p class="auth-card-subtitle">Choose how you use NovaCare, then enter your account details.</p>

                <!-- Feedback Alerts -->
                <?php if (!empty($errorMessage)): ?>
                    <div class="auth-alert error">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                        <span><?= htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8') ?></span>
                    </div>
                <?php endif; ?>

                <?php if (!empty($successMessage)): ?>
                    <div class="auth-alert success">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
                        <span><?= htmlspecialchars($successMessage, ENT_QUOTES, 'UTF-8') ?></span>
                    </div>
                <?php endif; ?>

                <form method="POST" action="login.php" autocomplete="on">
                    <?= csrf_field() ?>
                    <input type="hidden" name="role" id="roleInput" value="<?= htmlspecialchars($selectedRole, ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="redirect" value="<?= htmlspecialchars($redirectUrl, ENT_QUOTES, 'UTF-8') ?>">

                    <!-- Role Switcher -->
                    <div class="role-toggle-group">
                        <label class="role-toggle-label">I'm signing in as</label>
                        <div class="role-toggle-pills">
                            <button type="button" class="role-pill-btn <?= $selectedRole === 'patient' ? 'active' : '' ?>" id="patientTabBtn">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                                    <circle cx="12" cy="7" r="4"/>
                                </svg>
                                Patient
                            </button>

                            <button type="button" class="role-pill-btn <?= $selectedRole === 'doctor' ? 'active' : '' ?>" id="doctorTabBtn">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M4.8 2.3A.3.3 0 1 0 5 2H4a2 2 0 0 0-2 2v5a6 6 0 0 0 6 6v0a6 6 0 0 0 6-6V4a2 2 0 0 0-2-2h-1a.2.2 0 1 0 .3.3"/>
                                    <path d="M8 15v1a6 6 0 0 0 6 6v0a6 6 0 0 0 6-6v-4"/>
                                    <circle cx="20" cy="10" r="2"/>
                                </svg>
                                Doctor
                            </button>

                            <button type="button" class="role-pill-btn <?= $selectedRole === 'admin' ? 'active' : '' ?>" id="adminTabBtn">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
                                </svg>
                                Admin
                            </button>
                        </div>
                    </div>

                    <!-- Identifier Input (Email or Doctor Login ID) -->
                    <div class="form-group">
                        <label for="identifier" class="form-label" id="identifierLabel">Email address</label>
                        <div class="input-wrap">
                            <span class="input-icon-left">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/>
                                    <polyline points="22,6 12,13 2,6"/>
                                </svg>
                            </span>
                            <input type="text" id="identifierInput" name="identifier" class="form-input"
                                   placeholder="you@example.com"
                                   value="<?= htmlspecialchars($identifier, ENT_QUOTES, 'UTF-8') ?>" 
                                   required autofocus>
                        </div>
                    </div>

                    <!-- Password Input -->
                    <div class="form-group">
                        <label for="password" class="form-label">Password</label>
                        <div class="input-wrap">
                            <span class="input-icon-left">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
                                    <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                                </svg>
                            </span>
                            <input type="password" id="password" name="password" class="form-input" 
                                   placeholder="Enter your password" required>
                            <button type="button" class="password-toggle-btn" id="togglePassword">Show</button>
                        </div>
                    </div>

                    <!-- Remember me & Forgot Password -->
                    <div class="form-actions-row">
                        <label class="remember-label">
                            <input type="checkbox" name="remember" value="1">
                            Remember me
                        </label>
                        <a href="#" class="forgot-link">Forgot password?</a>
                    </div>

                    <!-- Submit Button -->
                    <button type="submit" class="btn-auth-submit">
                        <span>Log in securely</span>
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
                    </button>
                </form>

                <p class="auth-card-foot">
                    New to NovaCare? <a href="registration/account_selection.php">Create an account</a>
                </p>
            </div>

        </div>
    </main>

    <!-- Dark Bottom Footer Strip -->
    <footer class="auth-dark-footer">
        <div class="auth-dark-footer-inner">
            <div class="footer-brand-logo">
                <span>+</span>NovaCare
            </div>

            <div class="footer-support-text">
                Need help? +977 982-7012977 &bull; Mon&ndash;Sat, 9am&ndash;8pm
            </div>

            <div class="footer-badges">
                <span>Secure care coordination</span>
                <span>&bull;</span>
                <span>Privacy focused</span>
            </div>
        </div>
    </footer>

    <script>
        // Role Tab Switching
        const patientTabBtn = document.getElementById('patientTabBtn');
        const doctorTabBtn = document.getElementById('doctorTabBtn');
        const adminTabBtn = document.getElementById('adminTabBtn');
        const roleInput = document.getElementById('roleInput');
        const identifierLabel = document.getElementById('identifierLabel');
        const identifierInput = document.getElementById('identifierInput');

        patientTabBtn.addEventListener('click', () => {
            patientTabBtn.classList.add('active');
            doctorTabBtn.classList.remove('active');
            adminTabBtn.classList.remove('active');
            roleInput.value = 'patient';
            identifierLabel.textContent = 'Email address';
            identifierInput.placeholder = 'you@example.com';
        });

        doctorTabBtn.addEventListener('click', () => {
            doctorTabBtn.classList.add('active');
            patientTabBtn.classList.remove('active');
            adminTabBtn.classList.remove('active');
            roleInput.value = 'doctor';
            identifierLabel.textContent = 'Email or Doctor ID';
            identifierInput.placeholder = 'you@example.com or DOC-1234';
        });

        adminTabBtn.addEventListener('click', () => {
            adminTabBtn.classList.add('active');
            patientTabBtn.classList.remove('active');
            doctorTabBtn.classList.remove('active');
            roleInput.value = 'admin';
            identifierLabel.textContent = 'Administrator Email';
            identifierInput.placeholder = 'admin@example.com';
        });

        // Initialize based on PHP initial role
        if (roleInput.value === 'doctor') {
            doctorTabBtn.click();
        } else if (roleInput.value === 'admin') {
            adminTabBtn.click();
        }

        // Password Show/Hide Toggle
        const togglePassword = document.getElementById('togglePassword');
        const passwordInput = document.getElementById('password');

        togglePassword.addEventListener('click', () => {
            const isPassword = passwordInput.type === 'password';
            passwordInput.type = isPassword ? 'text' : 'password';
            togglePassword.textContent = isPassword ? 'Hide' : 'Show';
        });
    </script>

</body>
</html>
