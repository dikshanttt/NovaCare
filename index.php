<?php
require_once __DIR__ . '/auth/auth.php';
require_once __DIR__ . '/database/db.php';
require_once __DIR__ . '/include/function.php';

$isLoggedIn = is_logged_in();
$userRole = current_role();
$isPatient = $isLoggedIn && $userRole === 'patient';
$bookAppointmentUrl = 'patient/appointment.php';

// ── Live stats from DB ────────────────────────────────────────────────────────
try {
    $db = getDB();

    $totalDoctors   = (int) $db->query("SELECT COUNT(*) FROM doctors WHERE verification_status = 'verified'")->fetchColumn();
    $totalHospitals = (int) $db->query("SELECT COUNT(*) FROM hospitals WHERE is_active = TRUE")->fetchColumn();
    $totalAppointments = (int) $db->query("SELECT COUNT(*) FROM appointments WHERE status IN ('confirmed','completed')")->fetchColumn();

    // Booking completion / satisfaction rate
    $bookingData = $db->query("
        SELECT
            COUNT(*) AS total,
            COUNT(*) FILTER (WHERE status = 'completed') AS completed
        FROM appointments
    ")->fetch();

    $totalBookings = (int) ($bookingData['total'] ?? 0);
    $completedBookings = (int) ($bookingData['completed'] ?? 0);

    $bookingSatisfaction = $totalBookings > 0
        ? round(($completedBookings / $totalBookings) * 100)
        : 98;

    // Verified doctors for the "Meet your care team" section (limit 4)
    $stmt = $db->query("
        SELECT d.name, d.specialization, d.experience_years, d.image_path
        FROM   doctors d
        WHERE  d.verification_status = 'verified'
        ORDER  BY d.verified_at DESC
        LIMIT  4
    ");
    $featuredDoctors = $stmt->fetchAll();

    // Active hospitals (limit 4)
    $stmt = $db->query("
        SELECT id, name, address, departments
        FROM   hospitals
        WHERE  is_active = TRUE
        ORDER  BY created_at DESC
        LIMIT  4
    ");
    $featuredHospitals = $stmt->fetchAll();

    $dbOk = true;
} catch (Throwable $e) {
    // If DB is unreachable, fall back to placeholder values
    $dbOk                = false;
    $totalDoctors        = 1800;
    $totalHospitals      = 250;
    $totalAppointments   = 0;
    $bookingSatisfaction = 98;
    $featuredDoctors     = [];
    $featuredHospitals   = [];
}

// Helper: return placeholder avatar when doctor has no image
function doctorAvatar(?string $path): string {
    return $path ? htmlspecialchars($path, ENT_QUOTES) : 'assets/img/avatar-placeholder.png';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NovaCare - Healthcare Appointment</title>
    <link rel="stylesheet" href="assets/css/main/style.css">
</head>
<body>

<!-- ═══════════════════════════════════ NAVBAR ═══════════════════════════════ -->
<header class="navbar">
    <div class="logo">
        <span>+</span>NovaCare
    </div>

    <nav>
        <a href="#home">Home</a>
        <a href="#care">Hospitals</a>
        <a href="#doctors">Doctors</a>
        <a href="#works">How It Works</a>
        <a href="#faq">FAQ</a>
        <a href="#contact">Contact</a>
    </nav>

    <div class="nav-buttons">
        <?php if ($isLoggedIn): ?>
            <?php if ($isPatient): ?>
                <a href="patient/dashboard.php" class="login">Dashboard</a>
                <a href="patient/appointment.php" class="btn cherry-btn">Book Appointment</a>
                <a href="logout.php" class="login" style="font-size: 13px; color: var(--gray);">Sign out</a>
            <?php elseif ($userRole === 'doctor'): ?>
                <a href="doctor/dashboard.php" class="login">Doctor Dashboard</a>
                <a href="logout.php" class="btn oat-btn">Sign out</a>
            <?php elseif ($userRole === 'admin'): ?>
                <a href="admin/dashboard.php" class="login">Admin Panel</a>
                <a href="logout.php" class="btn oat-btn">Sign out</a>
            <?php endif; ?>
        <?php else: ?>
            <a href="login.php" class="login">Sign In</a>
            <a href="registration/account_selection.php" class="login">Sign Up</a>
            <a href="<?= $bookAppointmentUrl ?>" class="btn cherry-btn">Book Appointment</a>
        <?php endif; ?>
    </div>
</header>

<!-- ═══════════════════════════════════ HERO ═════════════════════════════════ -->
<section class="hero" id="home">
    <div class="hero-content">
        <p class="small-title">CARE, MADE EASIER</p>
        <h1>Better care starts with the right connection.</h1>
        <p class="hero-text">Find trusted hospitals and experienced doctors, compare availability, and book your appointment in a few simple steps.</p>
        <div class="hero-buttons">
            <a href="#doctors" class="btn cherry-btn">Find a Doctor</a>
            <a href="#care" class="btn oat-btn">Browse Hospitals</a>
        </div>  
    </div>

    <div class="hero-image">
        <img src="assets/img/doctor-hero.jpg" alt="Doctor talking with patient">
        <div class="image-card">
            <small>. AVAILABILITY TODAY</small>
            <h3>Care when it suits you.</h3>
            <p><?= $totalAppointments > 0 ? $totalAppointments . '+ confirmed appointments' : '128 nearby appointments' ?></p>
        </div>
    </div>
</section>

<!-- ═══════════════════════════════════ CARE / HOSPITALS ═════════════════════ -->
<section class="care-section" id="care">
    <div class="section-heading">
        <p class="small-title">. DISCOVER CARE</p>
        <h2>A trusted place for every kind of care.</h2>
        <p>Search by specialist, doctor, or hospital. Every provider is verified, so your next step feels informed.</p>
    </div>

    <div class="search-box">
        <input type="text" placeholder="🔎 Speciality, doctor, or condition">
        <input type="text" placeholder="📍 Your location">
        <button class="cherry-btn">Search Care</button>
    </div>

    <?php if ($dbOk && count($featuredHospitals) > 0): ?>
    <!-- Live hospital cards from DB -->
    <div class="categories">
        <?php foreach ($featuredHospitals as $h): ?>
        <div class="category-card">
            <div class="icon">🏥</div>
            <small><?= htmlspecialchars($h['departments'] ?? 'General', ENT_QUOTES) ?></small>
            <h3><?= htmlspecialchars($h['name'], ENT_QUOTES) ?></h3>
            <a href="#">Explore Specialists &rarr;</a>
        </div>
        <?php endforeach; ?>
    </div>
    <?php else: ?>
    <!-- Static fallback when DB has no hospitals yet -->
    <div class="categories">
        <div class="category-card">
            <div class="icon">+</div>
            <small>Cardiology</small>
            <h3>Heart Care</h3>
            <a href="#">Explore Specialists &rarr;</a>
        </div>
        <div class="category-card">
            <div class="icon">+</div>
            <small>Pediatrics</small>
            <h3>Growing Families</h3>
            <a href="#">Explore Specialists &rarr;</a>
        </div>
        <div class="category-card">
            <div class="icon">+</div>
            <small>Orthopedics</small>
            <h3>Move with Ease</h3>
            <a href="#">Explore Specialists &rarr;</a>
        </div>
        <div class="category-card">
            <div class="icon">+</div>
            <small>Primary Care</small>
            <h3>Everyday Wellness</h3>
            <a href="#">Explore Specialists &rarr;</a>
        </div>
    </div>
    <?php endif; ?>
</section>

<!-- ═══════════════════════════════════ HOW IT WORKS ═════════════════════════ -->
<section class="work" id="works">
    <div class="section-heading center">
        <p class="small-title">. HOW IT WORKS</p>
        <h2>From search to seen in three simple steps.</h2>
        <p>NovaCare keeps your healthcare journey clean from the first search to your visit.</p>
    </div>

    <div class="steps">
        <div class="step">
            <span>01</span>
            <h3>Tell us what you need</h3>
            <p>Choose a specialty, symptom, or preferred hospital.</p>
        </div>
        <div class="step butter">
            <span>02</span>
            <h3>Compare trusted care</h3>
            <p>Review verified profiles, experience, ratings and availability.</p>
        </div>
        <div class="step">
            <span>03</span>
            <h3>Book with confidence</h3>
            <p>Select a time, share key details, and receive confirmation.</p>
        </div>
    </div>
</section>

<!-- ═══════════════════════════════════ STATS ════════════════════════════════ -->
<section class="stats">
   <div class="stats-title">
    <p>. CARE YOU CAN COUNT ON</p>
    <h2>Human support, backed by a growing care network.</h2>
</div>

<div class="stat">
    <h2><?= $totalHospitals ?>+</h2>
    <p>Partner Hospitals</p>
</div>

<div class="stat">
    <h2><?= number_format($totalDoctors) ?>+</h2>
    <p>Verified Doctors</p>
</div>

<div class="stat">
    <h2><?= $bookingSatisfaction ?>%</h2>
    <p>Booking Completion</p>
</div>
</section>

<!-- ═══════════════════════════════════ DOCTORS ══════════════════════════════ -->
<section class="doctors" id="doctors">
    <div class="doctor-heading">
        <div>
            <p class="small-title">. MEET YOUR CARE TEAM</p>
            <h2>Find the right doctor for you.</h2>
            <p>Add and manage doctors based on their speciality, experience, and availability.</p>
        </div>
    </div>

    <?php if ($dbOk && count($featuredDoctors) > 0): ?>
    <!-- Live doctor cards from DB -->
    <div class="doctor-grid">
        <?php foreach ($featuredDoctors as $doc): ?>
        <div class="doctor-card">
            <img src="<?= doctorAvatar($doc['image_path']) ?>" alt="Dr. <?= htmlspecialchars($doc['name'], ENT_QUOTES) ?>">
            <div class="doctor-card-body">
                <small><?= htmlspecialchars($doc['specialization'], ENT_QUOTES) ?></small>
                <h3>Dr. <?= htmlspecialchars($doc['name'], ENT_QUOTES) ?></h3>
                <p><?= (int) $doc['experience_years'] ?> years experience</p>
                <a href="<?= $bookAppointmentUrl ?>" class="btn cherry-btn">Book Now</a>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php else: ?>
    <!-- Placeholder when no verified doctors yet -->
    <div class="doctor-add-box">
        <div class="add-icon">+</div>
        <h3>No doctors yet</h3>
        <p>Verified doctors will appear here once the admin approves them.</p>
        <a href="registration/dotor_registration.php" class="btn cherry-btn">Register as Doctor</a>
    </div>
    <?php endif; ?>
</section>

<!-- ═══════════════════════════════════ FAQ ══════════════════════════════════ -->
<section class="faq" id="faq">
    <div class="faq-title">
        <p class="small-title">. GOOD TO KNOW</p>
        <h2>Questions deserve clear answers.</h2>
        <p>Our care team is here if you need anything beyond these essentials.</p>
    </div>

    <div class="faq-list">
        <details>
            <summary>
                Is NovaCare free for patients?
                <span>+</span>
            </summary>
            <p>Yes, browsing doctors and hospitals on NovaCare is completely free. Provider information is reviewed before being displayed on the platform.</p>
        </details>

        <details>
            <summary>
                Can I reschedule or cancel online?
                <span>+</span>
            </summary>
            <p>Yes, appointments can be managed through your patient account at any time before the scheduled slot.</p>
        </details>

        <details>
            <summary>
                What information do I need to book?
                <span>+</span>
            </summary>
            <p>You generally need your basic contact information, date of birth, and the reason for your visit.</p>
        </details>
    </div>
</section>

<!-- ═══════════════════════════════════ CTA ══════════════════════════════════ -->
<section class="cta" id="appointment">
    <h2>Your next care connection is closer than you think.</h2>
    <a href="<?= $bookAppointmentUrl ?>" class="btn cherry-btn">Book an Appointment</a>
</section>

<!-- ═══════════════════════════════════ FOOTER ═══════════════════════════════ -->
<footer id="contact">
    <div class="footer-brand">
        <div class="footer-logo">
            <span>+</span>NovaCare
        </div>
        <p>Connecting patients with trusted doctors and hospitals across the region.</p>
    </div>

    <div>
        <h4>Explore</h4>
        <a href="#care">Hospitals</a>
        <a href="#doctors">Doctors</a>
        <a href="#works">How It Works</a>
        <a href="#faq">FAQ</a>
    </div>

    <div>
        <h4>Contact</h4>
        <a href="mailto:hello@novacare.com">hello@novacare.com</a>
        <a href="tel:+18006822273">+1 800 682 2273</a>
    </div>
</footer>

</body>
</html>
