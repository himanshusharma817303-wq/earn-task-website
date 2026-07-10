<?php
/**
 * Configuration File Template
 * Copy this file to config.php and configure your event parameters.
 */

// Event Configuration
$event_title = "Exclusive Reward Event";
$reward_amount = "$1 USDT";
$signup_link = "https://example.com/register"; // Replace with your referral or task link

// Admin Settings
// Access the admin panel at http://yourdomain.com/admin.php
define('ADMIN_PASSWORD', 'YOUR_SECURE_PASSWORD'); // CHANGE THIS PASSWORD FOR SECURITY

// Storage Configuration
define('DATA_DIR', __DIR__ . '/data');
define('UPLOAD_DIR', __DIR__ . '/uploads');
define('SUBMISSIONS_FILE', DATA_DIR . '/submissions.json');

// File Upload Settings
$allowed_extensions = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
$max_file_size = 8 * 1024 * 1024; // 8MB in bytes
