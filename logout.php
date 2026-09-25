<?php
require_once __DIR__ . '/auth/auth.php';
require_once __DIR__ . '/include/function.php';

logout_user();
redirect('index.php');
