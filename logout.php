<?php
require_once __DIR__ . '/auth/auth.php';
require_once __DIR__ . '/include/function.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit('Please use the sign-out form to end your session.');
}

csrf_verify();
logout_user();
redirect('index.php');
