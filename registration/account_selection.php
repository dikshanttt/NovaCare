<?php
require_once __DIR__ . '/../include/bootstrap.php';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Account Selection - NovaCare</title>
    <link rel="stylesheet" href="../assets/css/login/auth.css">
</head>

<body>

    <!-- Subnav / Back link -->
    <div class="auth-subnav">
        <a href="../index.php" class="auth-back-link">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M19 12H5M12 19l-7-7 7-7" />
            </svg>
            Back to NovaCare
        </a>
    </div>

    <!-- Main Selection Hero -->
    <main class="selection-hero">
        <span class="selection-pill-tag">Welcome to NovaCare</span>
        <h1 class="selection-title">How would you like to use NovaCare?</h1>
        <p class="selection-subtitle">Choose your role to create the right account. You can review details before completing registration.</p>

        <!-- 2 Role Choice Cards -->
        <div class="selection-grid">

            <!-- Card 1: For Patients -->
            <div class="role-card patient-theme">
                <div>
                    <div class="role-card-header">
                        <div class="role-icon-circle">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2" />
                                <circle cx="12" cy="7" r="4" />
                            </svg>
                        </div>
                        <span class="role-category-tag">For Patients</span>
                    </div>

                    <h2 class="role-card-heading">Find the care you need.</h2>
                    <p class="role-card-desc">Create your patient account to find doctors, book appointments, and keep track of your visits in one place.</p>

                    <ul class="role-features-list">
                        <li class="role-feature-item">
                            <svg class="role-check-svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                <polyline points="20 6 9 17 4 12" />
                            </svg>
                            <span>Find doctors by specialty</span>
                        </li>
                        <li class="role-feature-item">
                            <svg class="role-check-svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                <polyline points="20 6 9 17 4 12" />
                            </svg>
                            <span>Book appointments online</span>
                        </li>
                        <li class="role-feature-item">
                            <svg class="role-check-svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                <polyline points="20 6 9 17 4 12" />
                            </svg>
                            <span>Manage your booked appointments</span>
                        </li>
                        <li class="role-feature-item">
                            <svg class="role-check-svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                <polyline points="20 6 9 17 4 12" />
                            </svg>
                            <span>Receive appointment status updates</span>
                        </li>
                    </ul>
                </div>

                <a href="patient_registration.php" class="role-btn">
                    <span>Register as a patient</span>
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M5 12h14M12 5l7 7-7 7" />
                    </svg>
                </a>
            </div>

            <!-- Card 2: For Doctors -->
            <div class="role-card doctor-theme">
                <div>
                    <div class="role-card-header">
                        <div class="role-icon-circle">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M4.8 2.3A.3.3 0 1 0 5 2H4a2 2 0 0 0-2 2v5a6 6 0 0 0 6 6v0a6 6 0 0 0 6-6V4a2 2 0 0 0-2-2h-1a.2.2 0 1 0 .3.3" />
                                <path d="M8 15v1a6 6 0 0 0 6 6v0a6 6 0 0 0 6-6v-4" />
                                <circle cx="20" cy="10" r="2" />
                            </svg>
                        </div>
                        <span class="role-category-tag">For Doctors</span>
                    </div>

                    <h2 class="role-card-heading">Manage your appointments easily.</h2>
                    <p class="role-card-desc">Create your doctor profile, manage appointment requests, and view patient details through one dashboard.</p>

                    <ul class="role-features-list">
                        <li class="role-feature-item">
                            <svg class="role-check-svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                <polyline points="20 6 9 17 4 12" />
                            </svg>
                            <span>Register as a doctor</span>
                        </li>

                        <li class="role-feature-item">
                            <svg class="role-check-svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                <polyline points="20 6 9 17 4 12" />
                            </svg>
                            <span>Await admin account verification</span>
                        </li>

                        <li class="role-feature-item">
                            <svg class="role-check-svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                <polyline points="20 6 9 17 4 12" />
                            </svg>
                            <span>Create your available schedule</span>
                        </li>

                        <li class="role-feature-item">
                            <svg class="role-check-svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                <polyline points="20 6 9 17 4 12" />
                            </svg>
                            <span>Update your schedule anytime</span>
                        </li>
                    </ul>
                </div>

                <a href="doctor_registration.php" class="role-btn">
                    <span>Register as a doctor</span>
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M5 12h14M12 5l7 7-7 7" />
                    </svg>
                </a>
            </div>

        </div>

        <p class="selection-bottom-link">
            Already have an account? <a href="../login.php">Sign in to NovaCare</a>
        </p>
    </main>

    <!-- 3-Step Journey Section -->
    <section class="selection-steps-section">
        <div class="steps-heading-wrap">
            <span class="selection-pill-tag">Simple From Here</span>
            <h2 class="steps-title">A clear path to your first appointment.</h2>
        </div>

        <div class="steps-cards-grid">
            <div class="step-card">
                <span class="step-number">1</span>
                <h3 class="step-title">Create your secure profile</h3>
                <p class="step-text">A few essential details help us tailor your experience.</p>
            </div>

            <div class="step-card">
                <span class="step-number">2</span>
                <h3 class="step-title">Find the right match</h3>
                <p class="step-text">Compare specialties, experience, locations, and times.</p>
            </div>

            <div class="step-card">
                <span class="step-number">3</span>
                <h3 class="step-title">Confirm and prepare</h3>
                <p class="step-text">Get instant confirmation plus helpful visit reminders.</p>
            </div>
        </div>
    </section>

    <!-- Trust Banner -->
    <section class="trust-banner-wrap">
        <div class="trust-banner-inner">
            <div class="trust-shield-icon">
                <svg width="38" height="38" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z" />
                    <path d="m9 12 2 2 4-4" />
                </svg>
            </div>

            <div class="trust-banner-info">
                <h3>Your trust is part of the treatment.</h3>
                <p>NovaCare uses secure, privacy-minded technology and verifies every provider in our network. Registration takes about 3 minutes, and our support team is here whenever you need a human hand.</p>
            </div>

            <ul class="trust-checklist">
                <li class="trust-checklist-item">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="20 6 9 17 4 12" />
                    </svg>
                    <span>Secure account access</span>
                </li>

                <li class="trust-checklist-item">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="20 6 9 17 4 12" />
                    </svg>
                    <span>Verified doctors</span>
                </li>

                <li class="trust-checklist-item">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="20 6 9 17 4 12" />
                    </svg>
                    <span>Easy appointment booking</span>
                </li>
            </ul>
        </div>
    </section>

    <!-- Dark Footer Strip -->
    <footer class="auth-dark-footer">
        <div class="auth-dark-footer-inner">
            <div class="footer-brand-logo">
                <span>+</span>NovaCare
            </div>

            <div class="footer-support-text">
                Need help? +977 982-72977 &bull; Mon&ndash;Sat, 9am&ndash;8pm
            </div>

            <div class="footer-badges">
                <span>Secure care coordination</span>
                <span>&bull;</span>
                <span>Privacy focused</span>
            </div>
        </div>
    </footer>

</body>

</html>