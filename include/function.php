<?php

    // Cleans and safely escapes user input
    function clean(string $value): string
    {
        return htmlspecialchars(trim($value), ENT_QUOTES, 'UTF-8');
    }

    // Finds the base path of the project
    function base_path(): string
    {
        $scriptPath = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '/');
        $scriptBase = dirname($scriptPath);
        $nestedDirectories = ['/admin', '/doctor', '/patient', '/register', '/registration'];

        foreach ($nestedDirectories as $directory) {
            if (str_ends_with($scriptBase, $directory)) {
                $scriptBase = dirname($scriptBase);
                break;
            }
        }

        if ($scriptBase === '/' || $scriptBase === '\\' || $scriptBase === '.') {
            return '';
        }

        return rtrim($scriptBase, '/');
    }

    // Redirects the user to another page or URL
    function redirect(string $path): void
    {
        if (preg_match('#^https?://#i', $path)) {
            header('Location: ' . $path);
            exit;
        }

        $base = base_path();
        $target = str_starts_with($path, '/') ? $path : '/' . $path;

        if ($base !== '') {
            $target = $base . $target;
        }

        header('Location: ' . $target);
        exit;
    }

    /**
     * Return an allowlisted local path for a post-login redirect.
     */
    function safe_internal_redirect(?string $destination, array $allowedPaths): ?string
    {
        if ($destination === null || $destination === '' || preg_match('/[\x00-\x20\x7f\\\\]/', $destination)) {
            return null;
        }

        if (str_starts_with($destination, '//')) {
            return null;
        }

        $parts = parse_url($destination);
        if ($parts === false) {
            return null;
        }
        foreach (['scheme', 'host', 'user', 'pass', 'query', 'fragment'] as $component) {
            if (array_key_exists($component, $parts)) {
                return null;
            }
        }

        if (!isset($parts['path'])) {
            return null;
        }

        $path = $parts['path'] ?? '';
        if (str_starts_with($path, '/')) {
            $path = substr($path, 1);
        }

        return in_array($path, $allowedPaths, true) ? $path : null;
    }

    // Stores a temporary flash message in the session
    function set_flash(string $type, string $message): void
    {
        $_SESSION['flash'] = ['type' => $type, 'message' => $message];
    }

    // Retrieves and removes the stored flash message
    function get_flash(): ?array
    {
        $flash = $_SESSION['flash'] ?? null;
        unset($_SESSION['flash']);
        return $flash;
    }

    // ---- CSRF protection --------------------------------------------------

    // Generates or returns the CSRF security token
        function csrf_token(): string
        {
            $tokenCreatedAt = (int) ($_SESSION['csrf_token_created_at'] ?? 0);
            if (
                empty($_SESSION['csrf_token']) ||
                $tokenCreatedAt === 0 ||
                time() - $tokenCreatedAt > 7200
            ) {
                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                $_SESSION['csrf_token_created_at'] = time();
            }
            return $_SESSION['csrf_token'];
        }

        // Creates a hidden CSRF token field for forms
        function csrf_field(): string
        {
            return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') . '">';
        }

        // Verifies the submitted CSRF token
        function csrf_verify(): void
        {
            $token = $_POST['csrf_token'] ?? '';
            $createdAt = (int) ($_SESSION['csrf_token_created_at'] ?? 0);
            if (
                !is_string($token) ||
                !is_string($_SESSION['csrf_token'] ?? null) ||
                $createdAt === 0 ||
                time() - $createdAt > 7200 ||
                !hash_equals($_SESSION['csrf_token'], $token)
            ) {
                http_response_code(403);
                exit('This form has expired or could not be verified. Please reload the page and try again.');
            }
        }

        /*
            * Generates a unique doctor login ID like DOC-1001.
        */
        function generate_doctor_login_id(PDO $db): string
        {
            do {
                $candidate = 'DOC-' . random_int(1000, 9999);
                $stmt = $db->prepare('SELECT 1 FROM doctors WHERE doctor_login_id = ?');
                $stmt->execute([$candidate]);
            } while ($stmt->fetch());

            return $candidate;
        }

        /**
         * Generates a random temporary password to email to a newly verified doctor.
         */
        function generate_temp_password(int $length = 10): string
        {
            $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789!@#';
            $password = '';
            for ($i = 0; $i < $length; $i++) {
                $password .= $chars[random_int(0, strlen($chars) - 1)];
            }
            return $password;
        }

        // ---- File upload validation --------------------------------------------

        // Validates and saves a doctor's uploaded profile image   
            function handle_doctor_image_upload(array $file): string
            {
                $allowedTypes = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
                $maxBytes = 2 * 1024 * 1024; // 2MB

                if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
                    throw new Exception('Image upload failed.');
                }
                if ($file['size'] > $maxBytes) {
                    throw new Exception('Image must be smaller than 2MB.');
                }

                // Check the real mime type server-side; never trust the client extension.
                    $finfo = new finfo(FILEINFO_MIME_TYPE);
                    $mime = $finfo->file($file['tmp_name']);

                    if (!isset($allowedTypes[$mime])) {
                        throw new Exception('Only JPG, PNG, or WEBP images are allowed.');
                    }

                $ext = $allowedTypes[$mime];
                $filename = bin2hex(random_bytes(16)) . '.' . $ext;
                $destDir = __DIR__ . '/../assets/uploads/doctors/';

                if (!is_dir($destDir)) {
                    if (!mkdir($destDir, 0755, true) && !is_dir($destDir)) {
                        throw new Exception('Upload directory could not be created.');
                    }
                }

                $destPath = $destDir . $filename;

                if (!move_uploaded_file($file['tmp_name'], $destPath)) {
                    throw new Exception('Could not save uploaded image.');
                }

                // Path stored in DB, relative to web root, used in <img src="...">
                    return 'assets/uploads/doctors/' . $filename;
            }

            /*
                * Returns a human-readable "time ago" string for a given datetime string.
             */
                function human_time_diff(string $datetime): string
                {
                    $diff = time() - strtotime($datetime);
                    if ($diff < 60)       return 'just now';
                    if ($diff < 3600)     return floor($diff / 60) . ' min ago';
                    if ($diff < 86400)    return floor($diff / 3600) . ' hr ago';
                    if ($diff < 604800)   return floor($diff / 86400) . ' day' . (floor($diff/86400)>1?'s':'') . ' ago';
                    return date('M j, Y', strtotime($datetime));
                }

            /**
                * Formats time string (HH:MM:SS or HH:MM) into clean readable format (e.g. 02:30 PM).
            */
                function format_time_slot(string $time): string
                {
                    $ts = strtotime($time);
                    return $ts !== false ? date('h:i A', $ts) : $time;
                }

            /**
                * Generates a unique appointment token (e.g. TK-20260831-4821).
            */
                function generate_appointment_token(PDO $db): string
                {
                    do {
                        $token = 'TK-' . date('Ymd') . '-' . random_int(1000, 9999);
                        $stmt = $db->prepare('SELECT 1 FROM appointments WHERE appointment_token = ?');
                        $stmt->execute([$token]);
                    } while ($stmt->fetch());

                    return $token;
                }    

