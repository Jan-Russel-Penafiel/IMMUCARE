<?php
// Session Configuration - Must be set before any session starts
if (session_status() == PHP_SESSION_NONE) {
    // Set session configuration before starting
    ini_set('session.gc_maxlifetime', 3600); // 1 hour = 3600 seconds  
    ini_set('session.cookie_lifetime', 3600); // 1 hour session cookie
    ini_set('session.use_strict_mode', 1); // Use strict mode for security
    ini_set('session.use_only_cookies', 1); // Only use cookies for session IDs
    ini_set('session.cookie_httponly', 1); // Prevent XSS attacks
    ini_set('session.cookie_secure', 0); // Set to 1 if using HTTPS
    
    // Set cookie parameters
    session_set_cookie_params([
        'lifetime' => 3600,
        'path' => '/',
        'domain' => '',
        'secure' => false, // Set to true if using HTTPS
        'httponly' => true,
        'samesite' => 'Strict'
    ]);
    
    session_start();
    
    // Initialize session regeneration time if not set
    if (!isset($_SESSION['session_regenerated'])) {
        $_SESSION['session_regenerated'] = time();
    }
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
    // Only check if session is active
    if (session_status() !== PHP_SESSION_ACTIVE) {
        return false;
    }
    
    if (isset($_SESSION['last_activity'])) {
        if (time() - $_SESSION['last_activity'] > SESSION_TIMEOUT) {
            // Session has expired
            session_unset();
            session_destroy();
            // Start a new session to show the timeout message
            session_start();
            header('Location: login.php?timeout=1');
            exit();
        }
    }
    $_SESSION['last_activity'] = time(); // Update last activity time
    
    // Auto-regenerate session ID for security
    regenerate_session();
    return true;
}

function regenerate_session() {
    // Only regenerate if session is active and headers not sent
    if (session_status() !== PHP_SESSION_ACTIVE || headers_sent()) {
        return false;
    }
    
    if (!isset($_SESSION['session_regenerated'])) {
        $_SESSION['session_regenerated'] = time();
    }
    
    if (time() - $_SESSION['session_regenerated'] > SESSION_REGENERATE_TIME) {
        session_regenerate_id(true);
        $_SESSION['session_regenerated'] = time();
    }
    return true;
}

// Function to properly start session with all configurations
function secure_session_start() {
    if (session_status() == PHP_SESSION_NONE) {
        // Set session configuration
        ini_set('session.gc_maxlifetime', 3600);
        ini_set('session.cookie_lifetime', 3600);
        ini_set('session.use_strict_mode', 1);
        ini_set('session.use_only_cookies', 1);
        ini_set('session.cookie_httponly', 1);
        ini_set('session.cookie_secure', 0); // Set to 1 if using HTTPS
        
        session_set_cookie_params([
            'lifetime' => 3600,
            'path' => '/',
            'domain' => '',
            'secure' => false,
            'httponly' => true,
            'samesite' => 'Strict'
        ]);
        
        return session_start();
    }
    return true;
}

// Function to safely destroy session
function secure_session_destroy() {
    if (session_status() == PHP_SESSION_ACTIVE) {
        $_SESSION = array();
        
        // Delete session cookie
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        
        session_destroy();
    }
}

// Function to check if user is logged in and session is valid
function is_user_logged_in() {
    if (session_status() !== PHP_SESSION_ACTIVE) {
        return false;
    }
    
    if (!isset($_SESSION['user_id'])) {
        return false;
    }
    
    // Check session timeout
    return check_session_timeout();
}

// Function to require login (redirect if not logged in)
function require_login($redirect_url = 'login.php') {
    if (!is_user_logged_in()) {
        header('Location: ' . $redirect_url);
        exit();
    }
}

// Function to set user session after successful login
function set_user_session($user_id, $user_data = array()) {
    if (session_status() !== PHP_SESSION_ACTIVE) {
        secure_session_start();
    }
    
    // Regenerate session ID for security after login (only if headers not sent)
    if (!headers_sent()) {
        session_regenerate_id(true);
    }
    
    $_SESSION['user_id'] = $user_id;
    $_SESSION['login_time'] = time();
    $_SESSION['last_activity'] = time();
    $_SESSION['session_regenerated'] = time();
    
    // Store additional user data if provided
    foreach ($user_data as $key => $value) {
        $_SESSION[$key] = $value;
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