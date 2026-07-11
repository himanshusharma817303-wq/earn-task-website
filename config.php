<?php
/**
 * Configuration File
 * Configure your event parameters here.
 */

// Event Configuration
$event_title = "Exclusive Reward Event";
$reward_amount = "$1 USDT";
$signup_link = "https://eipl7.com/?dl=8jvsp4"; // Replace with your referral or task link

// Admin Settings
// Access the admin panel at http://yourdomain.com/admin.php
define('ADMIN_PASSWORD', 'Raj1234'); // CHANGE THIS PASSWORD FOR SECURITY

// Storage Configuration
define('DATA_DIR', __DIR__ . '/data');
define('UPLOAD_DIR', __DIR__ . '/uploads');
define('SUBMISSIONS_FILE', DATA_DIR . '/submissions.json');

// File Upload Settings
$allowed_extensions = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
$max_file_size = 8 * 1024 * 1024; // 8MB in bytes
