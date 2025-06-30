<?php
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
define('SMS_PROVIDER', 'philsms');  // Default SMS provider
define('PHILSMS_API_KEY', '2100|J9BVGEx9FFOJAbHV0xfn6SMOkKBt80HTLjHb6zZX');  // Your PhilSMS API key
define('PHILSMS_SENDER_ID', 'PhilSMS');  // Your registered sender ID

// Application Settings
define('APP_URL', 'http://localhost/mic_new');  // Change to your application URL
define('APP_NAME', 'ImmuCare');

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