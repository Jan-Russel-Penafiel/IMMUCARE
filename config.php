<?php
// Session Configuration - Must be set before any session starts
if (session_status() == PHP_SESSION_NONE) {
    ini_set('session.gc_maxlifetime', 3600); // 1 hour = 3600 seconds
    ini_set('session.cookie_lifetime', 3600); // 1 hour session cookie
    session_set_cookie_params(3600); // Set session cookie to expire in 1 hour
    session_start();
}

// Database Configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'immucare_db');

// SMTP Configuration
define('SMTP_HOST', 'smtp.gmail.com');  // Change to your SMTP server
define('SMTP_USER', 'vmctaccollege@gmail.com');  // Change to your email
define('SMTP_PASS', 'tqqs fkkh lbuz jbeg');  // Change to your app password
define('SMTP_SECURE', 'tls');  // tls or ssl
define('SMTP_PORT', 587);  // 587 for TLS, 465 for SSL

// SMS Configuration
define('SMS_PROVIDER', 'iprog');  // IPROG SMS provider
define('IPROG_SMS_API_KEY', '1ef3b27ea753780a90cbdf07d027fb7b52791004');  // Your IPROG SMS API key
define('SMS_SENDER_ID', 'IMMUCARE');  // Your registered sender ID

// Application Settings
define('APP_URL', 'http://localhost/mic_new');  // Change to your application URL
define('APP_NAME', 'ImmuCare');

// Session Settings
define('SESSION_TIMEOUT', 3600); // 1 hour in seconds
define('SESSION_REGENERATE_TIME', 300); // Regenerate session ID every 5 minutes

// Session management functions
function check_session_timeout() {
    if (isset($_SESSION['last_activity'])) {
        if (time() - $_SESSION['last_activity'] > SESSION_TIMEOUT) {
            // Session has expired
            session_unset();
            session_destroy();
            header('Location: login.php?timeout=1');
            exit();
        }
    }
    $_SESSION['last_activity'] = time(); // Update last activity time
}

function regenerate_session() {
    if (!isset($_SESSION['session_regenerated'])) {
        $_SESSION['session_regenerated'] = time();
    }
    
    if (time() - $_SESSION['session_regenerated'] > SESSION_REGENERATE_TIME) {
        session_regenerate_id(true);
        $_SESSION['session_regenerated'] = time();
    }
}

// Other settings
define('SITE_URL', 'http://localhost/mic_new');
define('UPLOAD_DIR', __DIR__ . '/uploads');

// Contact Information
define('SUPPORT_EMAIL', 'support@immucare.com');
define('SUPPORT_PHONE', '+1-800-IMMUCARE');
define('SCHEDULING_PHONE', '+1-800-SCHEDULE');
define('IMMUNIZATION_EMAIL', 'immunization@immucare.com');
define('IMMUNIZATION_PHONE', '+1-800-VACCINE');

?> 