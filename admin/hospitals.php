<?php
require_once __DIR__ . '/../auth/auth.php';
require_once __DIR__ . '/../include/function.php';
require_once __DIR__ . '/../config/config.php';
require_login(['admin']);

$db = getDB();
$flash = get_flash();

/* ---------- POST ACTIONS ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $action     = $_POST['action'] ?? '';
    $hospitalId = (int)($_POST['hospital_id'] ?? 0);

    if ($action === 'toggle_status' && $hospitalId > 0) {
        $stmt = $db->prepare("
            UPDATE hospitals
            SET is_active = NOT is_active
            WHERE id = ?
        ");
        $stmt->execute([$hospitalId]);
        set_flash('success', 'Hospital status updated successfully.');

    } elseif ($action === 'add_hospital') {
        $name        = clean($_POST['name'] ?? '');
        $address     = clean($_POST['address'] ?? '');
        $phone       = clean($_POST['phone'] ?? '');
        $email       = clean($_POST['email'] ?? '');
        $emergency   = clean($_POST['emergency_phone'] ?? '');
        $departments = clean($_POST['departments'] ?? '');
        $description = clean($_POST['description'] ?? '');

        if ($name && $address && $phone && $email) {
            $stmt = $db->prepare("
                INSERT INTO hospitals (name, address, phone, email, emergency_phone, departments, description, is_active)
                VALUES (?, ?, ?, ?, ?, ?, ?, TRUE)
            ");
            $stmt->execute([$name, $address, $phone, $email, $emergency, $departments, $description]);
            set_flash('success', 'New hospital added successfully.');
        } else {
            set_flash('error', 'Please fill in all required fields (Name, Address, Phone, Email).');
        }
    }

    redirect('/admin/hospitals.php');
}

/* ---------- STATS ---------- */
$totalHospitals    = (int)$db->query("SELECT COUNT(*) FROM hospitals")->fetchColumn();
$activeHospitals   = (int)$db->query("SELECT COUNT(*) FROM hospitals WHERE is_active = TRUE")->fetchColumn();
$inactiveHospitals = $totalHospitals - $activeHospitals;
$affiliatedDoctors = (int)$db->query("SELECT COUNT(DISTINCT doctor_id) FROM doctor_hospital WHERE status = 'active'")->fetchColumn();

/* sidebar badge counts */
$pendingDoctors = (int)$db->query("SELECT COUNT(*) FROM doctors WHERE verification_status = 'pending'")->fetchColumn();
$pendingSched   = (int)$db->query("SELECT COUNT(*) FROM schedules WHERE status = 'pending_approval'")->fetchColumn();
$pendingApps    = (int)$db->query("SELECT COUNT(*) FROM appointments WHERE status IN ('pending','pending_hospital_approval')")->fetchColumn();

/* ---------- HOSPITAL LIST ---------- */
$hospitals = $db->query("
    SELECT h.*,
           (SELECT COUNT(*) FROM doctor_hospital dh WHERE dh.hospital_id = h.id AND dh.status = 'active') AS doctor_count
    FROM hospitals h
    ORDER BY h.is_active DESC, h.name ASC
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Hospitals | HAMS Console</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/admin/admin_style.css">
    <link rel="stylesheet" href="../assets/css/admin/hospitals.css">
</head>

<body class="admin-page">

    <div class="adm-sidebar-overlay" id="sidebarOverlay" onclick="closeSidebar()"></div>

    <div class="admin-shell">
        <aside class="adm-sidebar" id="adminSidebar">
            <div class="sidebar-top">
                <a class="adm-brand" href="dashboard.php">
                    <span class="adm-brand-icon">✚</span>
                    <div class="adm-brand-text">
                        <strong>HAMS</strong>
                        <small>Admin Console</small>
                    </div>
                </a>
                <button class="sidebar-close" onclick="closeSidebar()" aria-label="Close navigation">×</button>
            </div>

            <div class="sidebar-section-title">Workspace</div>

            <nav class="adm-nav">
                <a href="dashboard.php">
                    <span class="nav-icon">⊞</span>
                    <span>Overview</span>
                </a>
                <a class="active" href="hospitals.php">
                    <span class="nav-icon">🏥</span>
                    <span>Manage Hospitals</span>
                </a>
                <a href="verify_doctor.php">
                    <span class="nav-icon">🩺</span>
                    <span>Verify &amp; Affiliations</span>
                    <?php if ($pendingDoctors > 0): ?>
                        <span class="adm-badge"><?= $pendingDoctors ?></span>
                    <?php endif; ?>
                </a>
                <a href="schedule_approval.php">
                    <span class="nav-icon">📅</span>
                    <span>Schedule Approvals</span>
                    <?php if ($pendingSched > 0): ?>
                        <span class="adm-badge"><?= $pendingSched ?></span>
                    <?php endif; ?>
                </a>
                <a href="appointments.php">
                    <span class="nav-icon">📋</span>
                    <span>Appointments</span>
                    <?php if ($pendingApps > 0): ?>
                        <span class="adm-badge appointment-badge"><?= $pendingApps ?></span>
                    <?php endif; ?>
                </a>
            </nav>

            <div class="sidebar-bottom">
                <div class="network-status">
                    <span class="status-pulse"></span>
                    <div>
                        <strong>Network Online</strong>
                        <small>All systems operational</small>
                    </div>
                </div>
                <form method="POST" action="../logout.php" style="margin:0;">
                    <?= csrf_field() ?>
                    <button type="submit" class="signout-link" style="width:100%; border:0; background:transparent; cursor:pointer; text-align:left;">
                        <span class="nav-icon">↩</span>
                        Sign Out
                    </button>
                </form>
            </div>
        </aside>

        <main class="adm-content">
            <header class="adm-topbar">
                <button class="adm-mob-toggle" id="sidebarToggle" onclick="openSidebar()" aria-label="Open navigation">☰</button>

                <div class="breadcrumb">
                    <span>HAMS</span>
                    <b>/</b>
                    <strong>Manage Hospitals</strong>
                </div>

                <div class="topbar-right">
                    <div class="topbar-search">
                        <span class="search-icon">⌕</span>
                        <input type="text" id="hospitalSearchInput" placeholder="Search hospitals..." aria-label="Search hospitals">
                    </div>
                    <button class="topbar-icon-btn" aria-label="Notifications">
                        ♧
                        <span class="notif-dot"></span>
                    </button>
                    <div class="admin-profile-pill">
                        <div class="avatar-circle">SA</div>
                        <div class="profile-copy">
                            <strong>Super Admin</strong>
                            <small>Administrator</small>
                        </div>
                    </div>
                </div>
            </header>

            <div class="adm-body">
                <section class="welcome-row">
                    <div>
                        <p class="eyebrow">Hospital Network</p>
                        <h1>Manage Hospitals</h1>
                        <p class="welcome-text">View, add, and manage partner hospitals in the NovaCare network.</p>
                    </div>
                </section>

                <?php if ($flash): ?>
                    <div class="flash-message <?= htmlspecialchars($flash['type']) ?>">
                        <?= htmlspecialchars($flash['message']) ?>
                    </div>
                <?php endif; ?>

                <section class="metric-grid hospitals-metrics">
                    <article class="metric-card">
                        <div class="metric-icon blue">🏥</div>
                        <div class="metric-copy">
                            <span>Total</span>
                            <strong><?= $totalHospitals ?></strong>
                            <small>Registered hospitals</small>
                        </div>
                    </article>
                    <article class="metric-card">
                        <div class="metric-icon green">✓</div>
                        <div class="metric-copy">
                            <span>Active</span>
                            <strong><?= $activeHospitals ?></strong>
                            <small>Currently operational</small>
                        </div>
                    </article>
                    <article class="metric-card">
                        <div class="metric-icon gold">⏸</div>
                        <div class="metric-copy">
                            <span>Inactive</span>
                            <strong><?= $inactiveHospitals ?></strong>
                            <small>Temporarily paused</small>
                        </div>
                    </article>
                    <article class="metric-card">
                        <div class="metric-icon teal">🩺</div>
                        <div class="metric-copy">
                            <span>Doctors</span>
                            <strong><?= $affiliatedDoctors ?></strong>
                            <small>Affiliated physicians</small>
                        </div>
                    </article>
                </section>

                <!-- Hospital List Panel -->
                <section class="panel hospital-panel">
                    <div class="panel-head">
                        <div>
                            <span class="panel-kicker">NETWORK</span>
                            <h2>All Hospitals</h2>
                            <small>Partner hospitals in the NovaCare network</small>
                        </div>
                        <div class="panel-actions">
                            <button type="button" class="btn-primary" onclick="openHospitalModal()">
                                <span style="font-size: 1.1em; line-height: 1;">+</span> Add Hospital
                            </button>
                        </div>
                    </div>

                    <div class="table-wrap">
                        <table class="adm-table" id="hospitalsTable">
                            <thead>
                                <tr>
                                    <th>Hospital</th>
                                    <th>Contact</th>
                                    <th>Departments</th>
                                    <th>Doctors</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($hospitals)): ?>
                                    <tr class="empty-row">
                                        <td colspan="6" style="text-align:center;padding:40px;color:var(--text-muted);">
                                            No hospitals registered yet. Click <strong>+ Add Hospital</strong> to register one.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($hospitals as $h): ?>
                                        <?php
                                        $initials = strtoupper(substr($h['name'] ?? 'H', 0, 2));
                                        $deptRaw  = trim($h['departments'] ?? '');
                                        $deptList = $deptRaw !== '' ? array_filter(array_map('trim', explode(',', $deptRaw))) : [];
                                        $deptCount = count($deptList);
                                        ?>
                                        <tr class="hospital-row">
                                            <td>
                                                <div class="person-cell">
                                                    <span class="mini-avatar hospital-av"><?= htmlspecialchars($initials) ?></span>
                                                    <div>
                                                        <strong class="hospital-name"><?= htmlspecialchars($h['name']) ?></strong>
                                                        <small class="hospital-address"><?= htmlspecialchars($h['address']) ?></small>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <strong><?= htmlspecialchars($h['phone']) ?></strong>
                                                <small class="muted-block"><?= htmlspecialchars($h['email']) ?></small>
                                            </td>
                                            <td>
                                                <?php if ($deptCount > 0): ?>
                                                    <span class="count-pill"><?= $deptCount ?> dept<?= $deptCount !== 1 ? 's' : '' ?></span>
                                                    <div class="dept-tags">
                                                        <?php foreach (array_slice($deptList, 0, 3) as $dName): ?>
                                                            <span class="dept-tag"><?= htmlspecialchars($dName) ?></span>
                                                        <?php endforeach; ?>
                                                        <?php if ($deptCount > 3): ?>
                                                            <span class="dept-tag">+<?= $deptCount - 3 ?> more</span>
                                                        <?php endif; ?>
                                                    </div>
                                                <?php else: ?>
                                                    <span class="muted-block">—</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <strong><?= (int)$h['doctor_count'] ?></strong>
                                            </td>
                                            <td>
                                                <?php if ($h['is_active']): ?>
                                                    <span class="status-pill active">Active</span>
                                                <?php else: ?>
                                                    <span class="status-pill inactive">Inactive</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div class="row-actions">
                                                    <form method="POST" style="display:inline;">
                                                        <?= csrf_field() ?>
                                                        <input type="hidden" name="hospital_id" value="<?= (int)$h['id'] ?>">
                                                        <input type="hidden" name="action" value="toggle_status">
                                                        <button type="submit" class="btn-toggle-status <?= $h['is_active'] ? 'deactivate' : 'activate' ?>">
                                                            <?= $h['is_active'] ? 'Deactivate' : 'Activate' ?>
                                                        </button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>
        </main>
    </div>

    <!-- =======================================================
         ADD HOSPITAL MODAL DIALOG
         ======================================================= -->
    <div class="modal-backdrop" id="hospitalModalBackdrop" onclick="closeHospitalModal(event)">
        <div class="modal-card" onclick="event.stopPropagation()">
            <div class="modal-header">
                <div>
                    <span class="panel-kicker">REGISTER FACILITY</span>
                    <h2 class="modal-title">Add New Hospital</h2>
                    <p class="modal-desc">Enter details to partner with NovaCare network</p>
                </div>
                <button type="button" class="modal-close" onclick="closeHospitalModal()" aria-label="Close modal">✕</button>
            </div>

            <form method="POST" action="hospitals.php" class="modal-form">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="add_hospital">

                <div class="form-grid">
                    <div class="form-group full-width">
                        <label for="modal_name">Hospital Name <span class="required">*</span></label>
                        <input type="text" id="modal_name" name="name" required placeholder="e.g. NovaCare General Hospital">
                    </div>

                    <div class="form-group">
                        <label for="modal_email">Official Email <span class="required">*</span></label>
                        <input type="email" id="modal_email" name="email" required placeholder="e.g. info@novacare.org">
                    </div>

                    <div class="form-group">
                        <label for="modal_phone">Phone Number <span class="required">*</span></label>
                        <input type="tel" id="modal_phone" name="phone" required placeholder="e.g. +977 9800000000">
                    </div>

                    <div class="form-group">
                        <label for="modal_emergency">Emergency Helpline</label>
                        <input type="tel" id="modal_emergency" name="emergency_phone" placeholder="e.g. +977 9800000001">
                    </div>

                    <div class="form-group">
                        <label for="modal_departments">Departments <small style="color:var(--text-muted);font-weight:400;">(comma-separated)</small></label>
                        <input type="text" id="modal_departments" name="departments" placeholder="Cardiology, Pediatrics, ER, Neurology">
                    </div>

                    <div class="form-group full-width">
                        <label for="modal_address">Hospital Address <span class="required">*</span></label>
                        <input type="text" id="modal_address" name="address" required placeholder="e.g. Main Road, Biratnagar, Nepal">
                    </div>

                    <div class="form-group full-width">
                        <label for="modal_desc">Facility Description</label>
                        <textarea id="modal_desc" name="description" rows="3" placeholder="Brief description of the hospital facilities, bed capacity, and specialties..."></textarea>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn-cancel" onclick="closeHospitalModal()">Cancel</button>
                    <button type="submit" class="btn-submit">Add Hospital</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openSidebar() {
            document.querySelector(".adm-sidebar").classList.add("open");
            document.getElementById("sidebarOverlay").classList.add("open");
        }

        function closeSidebar() {
            document.querySelector(".adm-sidebar").classList.remove("open");
            document.getElementById("sidebarOverlay").classList.remove("open");
        }

        function openHospitalModal() {
            const backdrop = document.getElementById("hospitalModalBackdrop");
            backdrop.classList.add("open");
            document.body.style.overflow = "hidden";
            setTimeout(() => {
                const nameInput = document.getElementById("modal_name");
                if (nameInput) nameInput.focus();
            }, 100);
        }

        function closeHospitalModal(event) {
            if (event && event.target !== event.currentTarget) return;
            const backdrop = document.getElementById("hospitalModalBackdrop");
            backdrop.classList.remove("open");
            document.body.style.overflow = "";
        }

        // Close on ESC key
        document.addEventListener("keydown", function(e) {
            if (e.key === "Escape") {
                closeHospitalModal();
            }
        });

        // Instant live filter in hospitals table
        const searchInput = document.getElementById("hospitalSearchInput");
        if (searchInput) {
            searchInput.addEventListener("input", function() {
                const term = this.value.toLowerCase().trim();
                const rows = document.querySelectorAll("#hospitalsTable tbody tr.hospital-row");
                rows.forEach(row => {
                    const text = row.textContent.toLowerCase();
                    row.style.display = text.includes(term) ? "" : "none";
                });
            });
        }
    </script>
</body>

</html>
