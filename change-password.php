<?php
require_once __DIR__ . '/auth/auth.php';
require_login(['patient', 'doctor', 'admin']);

$errorMessage = '';
$successMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    $currentPassword = is_string($currentPassword) ? $currentPassword : '';
    $newPassword = is_string($newPassword) ? $newPassword : '';
    $confirmPassword = is_string($confirmPassword) ? $confirmPassword : '';

    if ($currentPassword === '' || $newPassword === '' || $confirmPassword === '') {
        $errorMessage = 'Please complete all password fields.';
    } elseif (strlen($newPassword) < 8) {
        $errorMessage = 'Your new password must be at least 8 characters long.';
    } elseif ($newPassword !== $confirmPassword) {
        $errorMessage = 'The new password and confirmation do not match.';
    } elseif ($newPassword === $currentPassword) {
        $errorMessage = 'Choose a new password that differs from your current password.';
    } else {
        try {
            $db = getDB();
            $stmt = $db->prepare('SELECT password_hash FROM users WHERE id = ? AND role = ? AND status = \'active\'');
            $stmt->execute([current_user_id(), current_role()]);
            $user = $stmt->fetch();

            if (!$user || !password_verify($currentPassword, $user['password_hash'])) {
                $errorMessage = 'Your current password is incorrect.';
            } else {
                $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);
                $update = $db->prepare('UPDATE users SET password_hash = ?, force_password_change = FALSE WHERE id = ?');
                $update->execute([$passwordHash, current_user_id()]);

                $_SESSION['force_password_change'] = false;
                session_regenerate_id(true);
                unset($_SESSION['csrf_token'], $_SESSION['csrf_token_created_at']);

                set_flash('success', 'Your password has been updated successfully.');
                $dashboard = match (current_role()) {
                    'admin' => 'admin/dashboard.php',
                    'doctor' => 'doctor/dashboard.php',
                    default => 'patient/dashboard.php',
                };
                redirect($dashboard);
            }
        } catch (Throwable $e) {
            error_log('Password change failed.');
            $errorMessage = 'A system error occurred while updating your password. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Change Password - NovaCare</title>
    <link rel="stylesheet" href="assets/css/auth.css">
</head>
<body>
    <header class="auth-navbar">
        <a href="index.php" class="auth-logo"><span class="auth-logo-badge">+</span>NovaCare</a>
    </header>

    <main class="auth-main-container" style="max-width: 620px;">
        <section class="auth-card">
            <span class="auth-card-tag">Account security</span>
            <h1 class="auth-card-title">Change your password</h1>
            <p class="auth-card-subtitle">Enter your current password and choose a new one.</p>

            <?php if ($errorMessage !== ''): ?>
                <div class="auth-alert error"><span><?= htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8') ?></span></div>
            <?php endif; ?>
            <?php if ($successMessage !== ''): ?>
                <div class="auth-alert success"><span><?= htmlspecialchars($successMessage, ENT_QUOTES, 'UTF-8') ?></span></div>
            <?php endif; ?>

            <form method="POST" action="change-password.php" autocomplete="on">
                <?= csrf_field() ?>
                <div class="form-group">
                    <label for="current_password" class="form-label">Current password</label>
                    <input class="form-input" type="password" id="current_password" name="current_password" autocomplete="current-password" required>
                </div>
                <div class="form-group">
                    <label for="new_password" class="form-label">New password</label>
                    <input class="form-input" type="password" id="new_password" name="new_password" autocomplete="new-password" minlength="8" required>
                </div>
                <div class="form-group">
                    <label for="confirm_password" class="form-label">Confirm new password</label>
                    <input class="form-input" type="password" id="confirm_password" name="confirm_password" autocomplete="new-password" minlength="8" required>
                </div>
                <button type="submit" class="btn-auth-submit"><span>Update password</span></button>
            </form>
        </section>
    </main>
</body>
</html>
