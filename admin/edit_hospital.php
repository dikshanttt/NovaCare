<?php
require_once __DIR__ . '/../auth/auth.php';
require_once __DIR__ . '/../include/function.php';
require_once __DIR__ . '/../database/db.php';

require_login(['admin']);

$db = getDB();
$hospitalId = (int) ($_POST['hospital_id'] ?? $_GET['hospital_id'] ?? 0);

if ($hospitalId < 1) {
    http_response_code(400);
    exit('A valid hospital ID is required.');
}

$stmt = $db->prepare('SELECT * FROM hospitals WHERE id = ?');
$stmt->execute([$hospitalId]);
$hospital = $stmt->fetch();

if (!$hospital) {
    http_response_code(404);
    exit('Hospital not found.');
}

$fields = [
    'name' => $hospital['name'],
    'address' => $hospital['address'],
    'phone' => $hospital['phone'],
    'email' => $hospital['email'],
    'emergency_phone' => $hospital['emergency_phone'] ?? '',
    'departments' => $hospital['departments'] ?? '',
    'description' => $hospital['description'] ?? '',
];
$errorMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    foreach ($fields as $key => $_value) {
        $posted = $_POST[$key] ?? '';
        $fields[$key] = is_string($posted) ? trim($posted) : '';
    }

    if ($fields['name'] === '' || $fields['address'] === '' || $fields['phone'] === '' || $fields['email'] === '') {
        $errorMessage = 'Please fill in all required fields (Name, Address, Phone, Email).';
    } elseif (!filter_var($fields['email'], FILTER_VALIDATE_EMAIL)) {
        $errorMessage = 'Please enter a valid email address.';
    } elseif (
        mb_strlen($fields['name']) > 200 ||
        mb_strlen($fields['address']) > 255 ||
        mb_strlen($fields['phone']) > 50 ||
        mb_strlen($fields['email']) > 150 ||
        mb_strlen($fields['emergency_phone']) > 50
    ) {
        $errorMessage = 'One or more fields exceed the allowed length.';
    } else {
        $update = $db->prepare('
            UPDATE hospitals
            SET name = ?, address = ?, phone = ?, email = ?, emergency_phone = ?,
                departments = ?, description = ?
            WHERE id = ?
        ');
        $update->execute([
            $fields['name'],
            $fields['address'],
            $fields['phone'],
            $fields['email'],
            $fields['emergency_phone'] !== '' ? $fields['emergency_phone'] : null,
            $fields['departments'] !== '' ? $fields['departments'] : null,
            $fields['description'] !== '' ? $fields['description'] : null,
            $hospitalId,
        ]);

        set_flash('success', 'Hospital details updated successfully.');
        redirect('/admin/hospitals.php');
    }
}

function hospital_edit_escape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Hospital | HAMS Console</title>
    <link rel="stylesheet" href="../assets/css/admin/admin_style.css">
    <link rel="stylesheet" href="../assets/css/admin/hospitals.css">
</head>
<body class="admin-page">
    <main class="adm-content">
        <div class="adm-body">
            <section class="welcome-row">
                <div>
                    <p class="eyebrow">Hospital Network</p>
                    <h1>Edit Hospital</h1>
                    <p class="welcome-text">Update the facility details shown across NovaCare.</p>
                </div>
                <a class="btn-cancel" href="hospitals.php">Back to hospitals</a>
            </section>

            <?php if ($errorMessage !== ''): ?>
                <div class="hospital-edit-error" role="alert"><?= hospital_edit_escape($errorMessage) ?></div>
            <?php endif; ?>

            <section class="panel hospital-panel">
              <form method="POST" action="edit_hospital.php" class="modal-form edit-hospital-form">
                <?= csrf_field() ?>
                <input type="hidden" name="hospital_id" value="<?= $hospitalId ?>">

                <div class="form-grid">
                    <div class="form-group full-width">
                        <label for="name">Hospital Name <span class="required">*</span></label>
                        <input type="text" id="name" name="name" value="<?= hospital_edit_escape($fields['name']) ?>" maxlength="200" required>
                    </div>
                    <div class="form-group">
                        <label for="email">Official Email <span class="required">*</span></label>
                        <input type="email" id="email" name="email" value="<?= hospital_edit_escape($fields['email']) ?>" maxlength="150" required>
                    </div>
                    <div class="form-group">
                        <label for="phone">Phone Number <span class="required">*</span></label>
                        <input type="tel" id="phone" name="phone" value="<?= hospital_edit_escape($fields['phone']) ?>" maxlength="50" required>
                    </div>
                    <div class="form-group">
                        <label for="emergency_phone">Emergency Helpline</label>
                        <input type="tel" id="emergency_phone" name="emergency_phone" value="<?= hospital_edit_escape($fields['emergency_phone']) ?>" maxlength="50">
                    </div>
                    <div class="form-group">
                        <label for="departments">Departments</label>
                        <input type="text" id="departments" name="departments" value="<?= hospital_edit_escape($fields['departments']) ?>">
                    </div>
                    <div class="form-group full-width">
                        <label for="address">Hospital Address <span class="required">*</span></label>
                        <input type="text" id="address" name="address" value="<?= hospital_edit_escape($fields['address']) ?>" maxlength="255" required>
                    </div>
                    <div class="form-group full-width">
                        <label for="description">Facility Description</label>
                        <textarea id="description" name="description" rows="4"><?= hospital_edit_escape($fields['description']) ?></textarea>
                    </div>
                </div>

                <div class="modal-footer">
                    <a class="btn-cancel" href="hospitals.php">Cancel</a>
                    <button type="submit" class="btn-submit">Save Changes</button>
                </div>
              </form>
            </section>
        </div>
    </main>
</body>
</html>
