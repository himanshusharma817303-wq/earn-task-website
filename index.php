<?php
/**
 * Quick Earn Task Event Page
 * Fast loading, responsive, secure, and beautiful.
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);

require_once 'config.php';

// Ensure uploads and data directories exist with correct permissions
if (!file_exists(DATA_DIR)) {
    @mkdir(DATA_DIR, 0755, true);
}
if (!file_exists(UPLOAD_DIR)) {
    @mkdir(UPLOAD_DIR, 0755, true);
}

// Handle Form Submission via AJAX POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    $response = ['success' => false, 'message' => ''];
    
    // Validate inputs
    $userId = isset($_POST['user_id']) ? trim($_POST['user_id']) : '';
    $usdtAddress = isset($_POST['usdt_address']) ? trim($_POST['usdt_address']) : '';
    
    if (empty($userId)) {
        $response['message'] = 'Please enter your User ID.';
        echo json_encode($response);
        exit;
    }
    
    if (empty($usdtAddress)) {
        $response['message'] = 'Please enter your USDT BEP-20 address.';
        echo json_encode($response);
        exit;
    }
    
    // BEP-20 (BSC) address format check: 0x followed by 40 hex characters
    if (!preg_match('/^0x[a-fA-F0-9]{40}$/', $usdtAddress)) {
        $response['message'] = 'Invalid USDT BEP-20 address format. It must start with 0x and be 42 characters long.';
        echo json_encode($response);
        exit;
    }
    
    // File validation
    if (!isset($_FILES['screenshot']) || $_FILES['screenshot']['error'] !== UPLOAD_ERR_OK) {
        $error_code = isset($_FILES['screenshot']['error']) ? $_FILES['screenshot']['error'] : -1;
        $file_errors = [
            UPLOAD_ERR_INI_SIZE => 'File exceeds upload limit set by server.',
            UPLOAD_ERR_FORM_SIZE => 'File is too large.',
            UPLOAD_ERR_PARTIAL => 'File was only partially uploaded.',
            UPLOAD_ERR_NO_FILE => 'Please upload a screenshot of your User ID.',
            UPLOAD_ERR_NO_TMP_DIR => 'Server missing temporary folder.',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk.',
            UPLOAD_ERR_EXTENSION => 'A PHP extension stopped the file upload.'
        ];
        $response['message'] = isset($file_errors[$error_code]) ? $file_errors[$error_code] : 'Screenshot upload failed. Please try again.';
        echo json_encode($response);
        exit;
    }
    
    $fileTmpPath = $_FILES['screenshot']['tmp_name'];
    $fileName = $_FILES['screenshot']['name'];
    $fileSize = $_FILES['screenshot']['size'];
    $fileNameCmps = explode(".", $fileName);
    $fileExtension = strtolower(end($fileNameCmps));
    
    if (!in_array($fileExtension, $allowed_extensions)) {
        $response['message'] = 'Invalid image type. Allowed types: ' . implode(', ', $allowed_extensions);
        echo json_encode($response);
        exit;
    }
    
    if ($fileSize > $max_file_size) {
        $response['message'] = 'Screenshot is too large. Max size allowed is ' . ($max_file_size / (1024 * 1024)) . 'MB.';
        echo json_encode($response);
        exit;
    }
    
    // Verify file is actually an image
    $check = getimagesize($fileTmpPath);
    if ($check === false) {
        $response['message'] = 'Uploaded file is not a valid image.';
        echo json_encode($response);
        exit;
    }
    
    // Clean and generate filename
    $newFileName = md5(time() . $userId . uniqid()) . '.' . $fileExtension;
    $dest_path = UPLOAD_DIR . '/' . $newFileName;
    
    if (move_uploaded_file($fileTmpPath, $dest_path)) {
        // Read and save submission
        $submissionData = [
            'id' => uniqid(),
            'user_id' => htmlspecialchars($userId),
            'usdt_address' => htmlspecialchars($usdtAddress),
            'screenshot' => $newFileName,
            'submitted_at' => date('Y-m-d H:i:s'),
            'ip' => $_SERVER['REMOTE_ADDR'],
            'status' => 'pending'
        ];
        
        $submissions = [];
        if (file_exists(SUBMISSIONS_FILE)) {
            $jsonData = file_get_contents(SUBMISSIONS_FILE);
            $submissions = json_decode($jsonData, true);
            if (!is_array($submissions)) {
                $submissions = [];
            }
        }
        
        $submissions[] = $submissionData;
        
        // Write file with lock
        if (file_put_contents(SUBMISSIONS_FILE, json_encode($submissions, JSON_PRETTY_PRINT), LOCK_EX)) {
            $response['success'] = true;
            $response['message'] = 'Success';
        } else {
            // Delete uploaded file if JSON write fails
            @unlink($dest_path);
            $response['message'] = 'Database error. Please try again.';
        }
    } else {
        $response['message'] = 'Failed to save screenshot. Check folder permissions.';
    }
    
    echo json_encode($response);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?php echo htmlspecialchars($event_title); ?> - Get Rewarded</title>
    <meta name="description" content="Complete simple tasks and win USDT rewards. Fast, secure, and direct distribution.">
    
    <!-- Inline CSS for instant loading -->
    <style>
        :root {
            --bg-dark: #0a0d14;
            --card-bg: rgba(20, 24, 35, 0.7);
            --card-border: rgba(255, 255, 255, 0.08);
            --accent-gold: #f3ba2f;
            --accent-orange: #f05a28;
            --accent-gradient: linear-gradient(135deg, #f5b026 0%, #f05a28 100%);
            --text-main: #f3f4f6;
            --text-muted: #9ca3af;
            --success-color: #10b981;
            --error-color: #ef4444;
            --shadow-glow: 0 0 25px rgba(243, 186, 47, 0.15);
            --transition-speed: 0.3s;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            -webkit-tap-highlight-color: transparent;
        }

        body {
            background-color: var(--bg-dark);
            color: var(--text-main);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: flex-start;
            padding: 0;
            overflow-x: hidden;
            position: relative;
        }

        /* Abstract glowing particles in background */
        body::before {
            content: '';
            position: absolute;
            width: 300px;
            height: 300px;
            background: radial-gradient(circle, rgba(243, 186, 47, 0.1) 0%, transparent 70%);
            top: 10%;
            left: -100px;
            z-index: 0;
            pointer-events: none;
        }
        body::after {
            content: '';
            position: absolute;
            width: 300px;
            height: 300px;
            background: radial-gradient(circle, rgba(240, 90, 40, 0.1) 0%, transparent 70%);
            bottom: 20%;
            right: -100px;
            z-index: 0;
            pointer-events: none;
        }

        /* Mobile Frame Container */
        .app-container {
            width: 100%;
            max-width: 480px;
            min-height: 100vh;
            background: linear-gradient(180deg, #101422 0%, #0a0d14 100%);
            position: relative;
            z-index: 1;
            box-shadow: 0 10px 50px rgba(0, 0, 0, 0.6);
            display: flex;
            flex-direction: column;
            border-left: 1px solid rgba(255, 255, 255, 0.03);
            border-right: 1px solid rgba(255, 255, 255, 0.03);
        }

        /* Header / Banner */
        .hero-banner {
            position: relative;
            background: linear-gradient(135deg, #240c04 0%, #101422 100%);
            padding: 40px 20px 30px;
            text-align: center;
            border-bottom: 1px solid var(--card-border);
            overflow: hidden;
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        .hero-banner::after {
            content: '';
            position: absolute;
            bottom: -50%;
            width: 200%;
            height: 100%;
            background: radial-gradient(ellipse at center, rgba(240, 90, 40, 0.15) 0%, transparent 70%);
            z-index: 1;
        }

        .floating-badge {
            background: rgba(243, 186, 47, 0.1);
            border: 1px solid rgba(243, 186, 47, 0.3);
            color: var(--accent-gold);
            padding: 6px 14px;
            border-radius: 50px;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 15px;
            z-index: 2;
            animation: pulse 2s infinite;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        @keyframes pulse {
            0% { transform: scale(1); opacity: 0.9; }
            50% { transform: scale(1.05); opacity: 1; box-shadow: 0 0 15px rgba(243, 186, 47, 0.2); }
            100% { transform: scale(1); opacity: 0.9; }
        }

        .hero-title {
            font-size: 2.2rem;
            font-weight: 800;
            line-height: 1.2;
            background: linear-gradient(135deg, #ffffff 30%, #f3ba2f 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 8px;
            z-index: 2;
            letter-spacing: -0.5px;
        }

        .hero-subtitle {
            font-size: 0.95rem;
            color: var(--text-muted);
            z-index: 2;
        }

        /* Reward Banner Card */
        .reward-card {
            background: rgba(243, 186, 47, 0.04);
            border: 1px solid rgba(243, 186, 47, 0.15);
            border-radius: 16px;
            margin: -20px 20px 25px;
            padding: 20px;
            z-index: 2;
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            box-shadow: var(--shadow-glow);
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .reward-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: -50%;
            width: 200%;
            height: 100%;
            background: linear-gradient(to right, transparent, rgba(255, 255, 255, 0.05), transparent);
            transform: skewX(-30deg);
            animation: shine 4s infinite linear;
        }

        @keyframes shine {
            0% { left: -100%; }
            50% { left: 100%; }
            100% { left: 100%; }
        }

        .reward-label {
            font-size: 0.8rem;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 5px;
        }

        .reward-amount {
            font-size: 2.4rem;
            font-weight: 900;
            background: var(--accent-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 10px;
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 8px;
        }

        .reward-amount svg {
            width: 32px;
            height: 32px;
            fill: #f3ba2f;
        }

        .reward-note {
            font-size: 0.75rem;
            color: var(--accent-gold);
            background: rgba(243, 186, 47, 0.1);
            padding: 4px 10px;
            border-radius: 6px;
            display: inline-block;
        }

        /* Rolling recent participants ticker (Very Chinese Event Page Style) */
        .live-ticker {
            background: rgba(10, 13, 20, 0.6);
            border-top: 1px solid rgba(255, 255, 255, 0.03);
            border-bottom: 1px solid rgba(255, 255, 255, 0.03);
            padding: 8px 15px;
            margin-bottom: 20px;
            overflow: hidden;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 0.75rem;
        }

        .ticker-badge {
            background: #ef4444;
            color: #fff;
            padding: 2px 6px;
            border-radius: 4px;
            font-weight: 700;
            font-size: 0.65rem;
            text-transform: uppercase;
            flex-shrink: 0;
            animation: flash 1.5s infinite;
        }

        @keyframes flash {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.6; }
        }

        .ticker-wrapper {
            flex-grow: 1;
            height: 18px;
            position: relative;
            overflow: hidden;
        }

        .ticker-list {
            position: absolute;
            width: 100%;
            animation: scrollTicker 15s infinite linear;
        }

        .ticker-item {
            height: 18px;
            line-height: 18px;
            white-space: nowrap;
            color: var(--text-muted);
            display: flex;
            justify-content: space-between;
        }

        .ticker-item span.highlight {
            color: var(--accent-gold);
            font-weight: 600;
        }

        @keyframes scrollTicker {
            0% { transform: translateY(0); }
            10% { transform: translateY(0); }
            15% { transform: translateY(-18px); }
            25% { transform: translateY(-18px); }
            30% { transform: translateY(-36px); }
            40% { transform: translateY(-36px); }
            45% { transform: translateY(-54px); }
            55% { transform: translateY(-54px); }
            60% { transform: translateY(-72px); }
            70% { transform: translateY(-72px); }
            75% { transform: translateY(-90px); }
            85% { transform: translateY(-90px); }
            90% { transform: translateY(-108px); }
            98% { transform: translateY(-108px); }
            100% { transform: translateY(0); }
        }

        /* Content section */
        .content-section {
            padding: 0 20px 40px;
            flex-grow: 1;
            z-index: 2;
        }

        .section-title {
            font-size: 1.1rem;
            font-weight: 700;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 8px;
            letter-spacing: 0.5px;
        }

        .section-title span {
            background: var(--accent-gradient);
            width: 4px;
            height: 16px;
            border-radius: 2px;
            display: inline-block;
        }

        /* Task Steps Cards */
        .steps-container {
            display: flex;
            flex-direction: column;
            gap: 12px;
            margin-bottom: 25px;
        }

        .step-card {
            background: var(--card-bg);
            border: 1px solid var(--card-border);
            border-radius: 12px;
            padding: 16px;
            display: flex;
            align-items: flex-start;
            gap: 14px;
            transition: border-color var(--transition-speed);
        }

        .step-card:hover {
            border-color: rgba(243, 186, 47, 0.2);
        }

        .step-number {
            background: var(--accent-gradient);
            color: #000;
            width: 24px;
            height: 24px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.8rem;
            font-weight: 700;
            flex-shrink: 0;
            margin-top: 2px;
        }

        .step-info {
            flex-grow: 1;
        }

        .step-text {
            font-size: 0.9rem;
            font-weight: 600;
            margin-bottom: 4px;
            color: var(--text-main);
        }

        .step-desc {
            font-size: 0.8rem;
            color: var(--text-muted);
            line-height: 1.4;
        }

        .step-action-btn {
            background: rgba(243, 186, 47, 0.1);
            color: var(--accent-gold);
            border: 1px solid rgba(243, 186, 47, 0.2);
            padding: 8px 14px;
            border-radius: 8px;
            font-size: 0.8rem;
            font-weight: 700;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            margin-top: 10px;
            transition: all var(--transition-speed);
        }

        .step-action-btn:hover {
            background: var(--accent-gradient);
            color: #000;
            border-color: transparent;
            transform: translateY(-2px);
        }

        /* Form styling */
        .submission-form {
            background: var(--card-bg);
            border: 1px solid var(--card-border);
            border-radius: 16px;
            padding: 20px;
            margin-bottom: 20px;
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
        }

        .form-group {
            margin-bottom: 18px;
        }

        .form-label {
            display: block;
            font-size: 0.85rem;
            font-weight: 600;
            margin-bottom: 8px;
            color: var(--text-main);
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .form-label svg {
            width: 16px;
            height: 16px;
            fill: var(--accent-gold);
        }

        .form-input {
            width: 100%;
            background: rgba(10, 13, 20, 0.8);
            border: 1px solid var(--card-border);
            border-radius: 10px;
            padding: 12px 14px;
            color: #fff;
            font-size: 0.9rem;
            transition: border-color var(--transition-speed), box-shadow var(--transition-speed);
        }

        .form-input:focus {
            outline: none;
            border-color: var(--accent-gold);
            box-shadow: 0 0 10px rgba(243, 186, 47, 0.15);
        }

        .form-input::placeholder {
            color: #4b5563;
        }

        /* File upload zone */
        .upload-zone {
            border: 2px dashed var(--card-border);
            border-radius: 10px;
            padding: 20px;
            text-align: center;
            cursor: pointer;
            transition: border-color var(--transition-speed), background var(--transition-speed);
            background: rgba(10, 13, 20, 0.4);
            position: relative;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            min-height: 120px;
        }

        .upload-zone.dragover {
            border-color: var(--accent-gold);
            background: rgba(243, 186, 47, 0.05);
        }

        .upload-icon {
            width: 36px;
            height: 36px;
            fill: var(--text-muted);
            margin-bottom: 8px;
            transition: fill var(--transition-speed);
        }

        .upload-zone:hover .upload-icon {
            fill: var(--accent-gold);
        }

        .upload-text {
            font-size: 0.8rem;
            color: var(--text-muted);
        }

        .upload-text span {
            color: var(--accent-gold);
            font-weight: 600;
        }

        .file-input {
            display: none;
        }

        /* File preview */
        .preview-container {
            display: none;
            width: 100%;
            position: relative;
            border-radius: 8px;
            overflow: hidden;
            border: 1px solid var(--card-border);
        }

        .preview-image {
            width: 100%;
            height: auto;
            max-height: 180px;
            object-fit: contain;
            background: #000;
            display: block;
        }

        .remove-preview {
            position: absolute;
            top: 8px;
            right: 8px;
            background: rgba(239, 68, 68, 0.9);
            color: #fff;
            border: none;
            border-radius: 50%;
            width: 24px;
            height: 24px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.8rem;
            font-weight: 700;
            transition: background var(--transition-speed);
            box-shadow: 0 2px 6px rgba(0,0,0,0.3);
        }

        .remove-preview:hover {
            background: #dc2626;
        }

        /* Error Banner */
        .error-banner {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid rgba(239, 68, 68, 0.2);
            color: var(--error-color);
            padding: 10px 12px;
            border-radius: 8px;
            font-size: 0.8rem;
            margin-bottom: 15px;
            display: none;
            align-items: center;
            gap: 8px;
        }

        .error-banner svg {
            width: 16px;
            height: 16px;
            fill: var(--error-color);
            flex-shrink: 0;
        }

        /* Submit Button */
        .submit-btn {
            width: 100%;
            background: var(--accent-gradient);
            border: none;
            color: #000;
            padding: 14px 20px;
            border-radius: 10px;
            font-size: 1rem;
            font-weight: 800;
            cursor: pointer;
            transition: all var(--transition-speed);
            box-shadow: 0 4px 15px rgba(240, 90, 40, 0.3);
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 8px;
        }

        .submit-btn:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(240, 90, 40, 0.5);
        }

        .submit-btn:active:not(:disabled) {
            transform: translateY(0);
        }

        .submit-btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }

        /* Spinner */
        .spinner {
            width: 18px;
            height: 18px;
            border: 2px solid rgba(0, 0, 0, 0.1);
            border-top: 2px solid #000;
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
            display: none;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        /* Success Overlay Screen (Single page seamless change) */
        .success-overlay {
            display: none;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
            padding: 40px 20px;
            flex-grow: 1;
            z-index: 10;
            animation: fadeIn 0.4s ease forwards;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .success-icon-wrapper {
            background: rgba(16, 185, 129, 0.1);
            border: 1px solid rgba(16, 185, 129, 0.3);
            width: 72px;
            height: 72px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 24px;
            box-shadow: 0 0 20px rgba(16, 185, 129, 0.2);
        }

        .success-icon-wrapper svg {
            width: 36px;
            height: 36px;
            fill: var(--success-color);
        }

        .success-title {
            font-size: 1.8rem;
            font-weight: 800;
            color: #fff;
            margin-bottom: 12px;
        }

        .success-text {
            font-size: 0.95rem;
            color: var(--text-muted);
            line-height: 1.6;
            margin-bottom: 25px;
            max-width: 320px;
        }

        .success-text span {
            color: var(--accent-gold);
            font-weight: 700;
        }

        /* Footer */
        .app-footer {
            text-align: center;
            padding: 20px;
            font-size: 0.75rem;
            color: #4b5563;
            border-top: 1px solid rgba(255, 255, 255, 0.02);
            margin-top: auto;
        }

        /* Info box */
        .info-box {
            background: rgba(243, 186, 47, 0.03);
            border: 1px solid rgba(243, 186, 47, 0.1);
            border-radius: 8px;
            padding: 12px;
            margin-top: 15px;
            font-size: 0.75rem;
            color: var(--text-muted);
            line-height: 1.4;
            display: flex;
            gap: 8px;
        }

        .info-box svg {
            width: 14px;
            height: 14px;
            fill: var(--accent-gold);
            flex-shrink: 0;
            margin-top: 2px;
        }
    </style>
</head>
<body>

    <div class="app-container">
        
        <!-- Main Form & Content Wrapper -->
        <div id="main-content-flow">
            <!-- Hero Banner -->
            <div class="hero-banner">
                <div class="floating-badge">
                    <svg viewBox="0 0 24 24" width="12" height="12" fill="currentColor"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-6h2v6zm0-8h-2V7h2v2z"/></svg>
                    Airdrop Event
                </div>
                <h1 class="hero-title">Quick Earn Task</h1>
                <p class="hero-subtitle">Complete 1 simple step & submit details</p>
            </div>

            <!-- Reward Card -->
            <div class="reward-card">
                <div class="reward-label">Instant Reward Pool</div>
                <div class="reward-amount">
                    <!-- USDT custom SVG icon -->
                    <svg viewBox="0 0 128 128">
                        <circle cx="64" cy="64" r="64" fill="#26A17B"/>
                        <path fill="#FFF" d="M64 16v18.7c2.6 0 5.1.1 7.6.4v-19c-2.5-.2-5-.3-7.6-.3s-5.1.1-7.6.3v19c2.5-.3 5-.4 7.6-.4zm-22.9 6.2l9.4 16.3c1.7-2 3.6-3.8 5.7-5.3L46.8 17c-1.9 1.5-3.6 3.2-5.1 5.2zm45.8 0c-1.5-2-3.2-3.7-5.1-5.2l-9.4 16.2c2.1 1.5 4 3.3 5.7 5.3l8.8-16.3zm-57 26.6l16.3 9.4c.5-2.6 1.4-5.1 2.5-7.5l-16.2-9.4c-1.2 2.4-2 5-2.6 7.5zm68.2-7.5c1.1 2.4 2 4.9 2.5 7.5l16.3-9.4c-.6-2.5-1.4-5.1-2.6-7.5l-16.2 9.4zM16 64c0 2.6.1 5.1.3 7.6h19c-.3-2.5-.4-5-.4-7.6s.1-5.1.4-7.6h-19c-.2 2.5-.3 5.1-.3 7.6zm19.3 22.9l-16.3 9.4c1.5 2 3.2 3.6 5.2 5.1l9.4-16.3c-2-1.7-3.8-3.6-5.3-5.7c2-1.7 3.8-3.6 5.3-5.7zm57.7 5.7c1.7 2 3.6 3.8 5.7 5.3l9.4-16.3c-2-1.5-3.7-3.2-5.2-5.1l-9.9 16.1zm-45 3l-9.4 16.3c2 1.5 3.7 3.2 5.2 5.1l9.4-16.3c-2.1-1.7-4-3.6-5.7-5.3l.5.2z"/>
                        <path fill="#FFF" d="M78 48.7c0-2.3-6.2-4.2-14-4.2s-14 1.9-14 4.2 6.2 4.2 14 4.2 14-1.9 14-4.2zm-14 8.2c-10 0-18.7-3-20.7-7V62c2 4 10.7 7 20.7 7s18.7-3 20.7-7v-12.1c-2 4.1-10.7 7.1-20.7 7.1zm0 13c-10 0-18.7-3-20.7-7v12.1c2 4 10.7 7 20.7 7s18.7-3 20.7-7V69.9c-2 4.1-10.7 7.1-20.7 7.1zm0 13c-10 0-18.7-3-20.7-7v12.1c2 4 10.7 7 20.7 7s18.7-3 20.7-7V82.9c-2 4.1-10.7 7.1-20.7 7.1zm0 13c-10 0-18.7-3-20.7-7V98c2 4 10.7 7 20.7 7s18.7-3 20.7-7v-12.1c-2 4.1-10.7 7.1-20.7 7.1z"/>
                    </svg>
                    Win Up To <?php echo htmlspecialchars($reward_amount); ?>
                </div>
                <div class="reward-note">⚡ Fast Review & Instant Distribution</div>
            </div>

            <!-- Live scrolling marquee for social proof -->
            <div class="live-ticker">
                <div class="ticker-badge">Live</div>
                <div class="ticker-wrapper">
                    <div class="ticker-list">
                        <div class="ticker-item"><span>User ID 942*** submitted</span> <span class="highlight">Success</span></div>
                        <div class="ticker-item"><span>User ID 108*** submitted</span> <span class="highlight">Success</span></div>
                        <div class="ticker-item"><span>User ID 684*** won</span> <span class="highlight">+$1.00 USDT</span></div>
                        <div class="ticker-item"><span>User ID 249*** submitted</span> <span class="highlight">Success</span></div>
                        <div class="ticker-item"><span>User ID 502*** won</span> <span class="highlight">+$1.00 USDT</span></div>
                        <div class="ticker-item"><span>User ID 711*** submitted</span> <span class="highlight">Success</span></div>
                        <div class="ticker-item"><span>User ID 942*** submitted</span> <span class="highlight">Success</span></div> <!-- duplicated first for smooth loop -->
                    </div>
                </div>
            </div>

            <div class="content-section">
                <!-- Steps Section -->
                <div class="section-title">
                    <span></span> Task Steps
                </div>
                <div class="steps-container">
                    <div class="step-card">
                        <div class="step-number">1</div>
                        <div class="step-info">
                            <div class="step-text">Register / Sign Up</div>
                            <div class="step-desc">Click the button below, register a new account on our sponsor's website.</div>
                            <a href="<?php echo htmlspecialchars($signup_link); ?>" target="_blank" class="step-action-btn">
                                Register Now 
                                <svg viewBox="0 0 24 24" width="14" height="14" fill="currentColor"><path d="M19 19H5V5h7V3H5c-1.11 0-2 .9-2 2v14c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2v-7h-2v7zM14 3v2h3.59l-9.83 9.83 1.41 1.41L19 6.41V10h2V3h-7z"/></svg>
                            </a>
                        </div>
                    </div>
                    
                    <div class="step-card">
                        <div class="step-number">2</div>
                        <div class="step-info">
                            <div class="step-text">Copy User ID & Screenshot</div>
                            <div class="step-desc">Log in, go to your profile, copy your new User ID and take a screenshot showing it clearly.</div>
                        </div>
                    </div>
                    
                    <div class="step-card">
                        <div class="step-number">3</div>
                        <div class="step-info">
                            <div class="step-text">Submit Details</div>
                            <div class="step-desc">Fill out the form below with your User ID, upload the screenshot, and provide your USDT BEP-20 address.</div>
                        </div>
                    </div>
                </div>

                <!-- Form Section -->
                <div class="section-title">
                    <span></span> Submit Proof
                </div>
                
                <form id="task-form" class="submission-form" enctype="multipart/form-data">
                    
                    <!-- Error Banner -->
                    <div id="form-error" class="error-banner">
                        <svg viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/></svg>
                        <span id="error-message">Error message here</span>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="user_id">
                            <svg viewBox="0 0 24 24"><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg>
                            Your User ID
                        </label>
                        <input type="text" id="user_id" name="user_id" class="form-input" placeholder="e.g. 8493021" required autocomplete="off">
                    </div>

                    <div class="form-group">
                        <label class="form-label">
                            <svg viewBox="0 0 24 24"><path d="M21 19V5c0-1.1-.9-2-2-2H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2zM8.5 13.5l2.5 3.01L14.5 12l4.5 6H5l3.5-4.5z"/></svg>
                            Screenshot of User ID
                        </label>
                        
                        <!-- Drag & Drop upload zone -->
                        <div id="drop-zone" class="upload-zone">
                            <svg class="upload-icon" viewBox="0 0 24 24"><path d="M19.35 10.04C18.67 6.59 15.64 4 12 4 9.11 4 6.6 5.64 5.35 8.04 2.34 8.36 0 10.91 0 14c0 3.31 2.69 6 6 6h13c2.76 0 5-2.24 5-5 0-2.64-2.05-4.78-4.65-4.96zM14 13v4h-4v-4H7l5-5 5 5h-3z"/></svg>
                            <div class="upload-text" id="upload-instruction">
                                <span>Click to upload</span> or drag and drop screenshot
                            </div>
                            <input type="file" id="screenshot" name="screenshot" class="file-input" accept="image/*" required>
                            
                            <!-- Image preview container inside drop zone -->
                            <div id="preview-box" class="preview-container">
                                <img id="preview-img" src="#" class="preview-image" alt="Screenshot preview">
                                <button type="button" id="remove-btn" class="remove-preview">&times;</button>
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="usdt_address">
                            <svg viewBox="0 0 24 24"><path d="M21 18v1c0 1.1-.9 2-2 2H5c-1.11 0-2-.9-2-2V5c0-1.1.89-2 2-2h14c1.1 0 2 .9 2 2v1h-9c-1.11 0-2 .9-2 2v8c0 1.1.89 2 2 2h9zm-9-2h10V8H12v8zm4-2.5c-.83 0-1.5-.67-1.5-1.5s.67-1.5 1.5-1.5 1.5.67 1.5 1.5-.67 1.5-1.5 1.5z"/></svg>
                            USDT BEP-20 (BSC) Address
                        </label>
                        <input type="text" id="usdt_address" name="usdt_address" class="form-input" placeholder="0x..." required autocomplete="off">
                    </div>

                    <button type="submit" id="submit-btn" class="submit-btn">
                        <span>Submit Details</span>
                        <div class="spinner" id="btn-spinner"></div>
                    </button>

                    <div class="info-box">
                        <svg viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-6h2v6zm0-8h-2V7h2v2z"/></svg>
                        <div>Multiple submissions or using fake screenshots will result in immediate disqualification. We inspect each entry carefully.</div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Success Screen -->
        <div id="success-flow" class="success-overlay">
            <div class="success-icon-wrapper">
                <svg viewBox="0 0 24 24"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg>
            </div>
            <h2 class="success-title">Thank you!</h2>
            <p class="success-text">Your submission has been received. <span>If you win, you will get <?php echo htmlspecialchars($reward_amount); ?>.</span> Rewards are sent directly to your BEP-20 wallet after manual verification.</p>
            <button type="button" onclick="resetForm()" class="step-action-btn" style="margin-top: 0; background: rgba(255,255,255,0.05); color: #fff; border-color: rgba(255,255,255,0.1);">
                Submit Another Entry
            </button>
        </div>

        <!-- Footer -->
        <div class="app-footer">
            &copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars($event_title); ?>. All Rights Reserved.
        </div>

    </div>

    <!-- Frontend Scripting for Interactive experience and instant validation -->
    <script>
        const dropZone = document.getElementById('drop-zone');
        const fileInput = document.getElementById('screenshot');
        const previewBox = document.getElementById('preview-box');
        const previewImg = document.getElementById('preview-img');
        const removeBtn = document.getElementById('remove-btn');
        const uploadInstruction = document.getElementById('upload-instruction');
        
        const taskForm = document.getElementById('task-form');
        const submitBtn = document.getElementById('submit-btn');
        const btnSpinner = document.getElementById('btn-spinner');
        const formError = document.getElementById('form-error');
        const errorMessage = document.getElementById('error-message');
        
        const mainContentFlow = document.getElementById('main-content-flow');
        const successFlow = document.getElementById('success-flow');

        // Drag & Drop logic
        dropZone.addEventListener('click', () => {
            if (fileInput.files.length === 0) {
                fileInput.click();
            }
        });

        dropZone.addEventListener('dragover', (e) => {
            e.preventDefault();
            dropZone.classList.add('dragover');
        });

        dropZone.addEventListener('dragleave', () => {
            dropZone.classList.remove('dragover');
        });

        dropZone.addEventListener('drop', (e) => {
            e.preventDefault();
            dropZone.classList.remove('dragover');
            
            if (e.dataTransfer.files.length > 0) {
                handleFile(e.dataTransfer.files[0]);
            }
        });

        fileInput.addEventListener('change', (e) => {
            if (e.target.files.length > 0) {
                handleFile(e.target.files[0]);
            }
        });

        function handleFile(file) {
            // Check file type
            if (!file.type.startsWith('image/')) {
                showError('Please upload a valid image file.');
                return;
            }

            // Check file size (8MB max)
            const maxSize = 8 * 1024 * 1024;
            if (file.size > maxSize) {
                showError('Image size should be less than 8MB.');
                return;
            }

            // Read and show preview
            const reader = new FileReader();
            reader.onload = (e) => {
                previewImg.src = e.target.result;
                previewBox.style.display = 'block';
                uploadInstruction.style.display = 'none';
                formError.style.display = 'none';
            };
            reader.readAsDataURL(file);
            
            // Assign to input files
            const dataTransfer = new DataTransfer();
            dataTransfer.items.add(file);
            fileInput.files = dataTransfer.files;
        }

        removeBtn.addEventListener('click', (e) => {
            e.stopPropagation(); // Prevent opening file chooser
            fileInput.value = '';
            previewImg.src = '#';
            previewBox.style.display = 'none';
            uploadInstruction.style.display = 'block';
        });

        // Form Submit via Fetch API
        taskForm.addEventListener('submit', (e) => {
            e.preventDefault();
            formError.style.display = 'none';
            
            const userId = document.getElementById('user_id').value.trim();
            const usdtAddress = document.getElementById('usdt_address').value.trim();
            
            if (!userId) {
                showError('Please enter your User ID.');
                return;
            }

            if (!fileInput.files || fileInput.files.length === 0) {
                showError('Please upload a screenshot of your User ID.');
                return;
            }

            if (!usdtAddress) {
                showError('Please enter your USDT BEP-20 address.');
                return;
            }

            // BEP-20 Address regex check (0x followed by 40 hex characters)
            const bep20Regex = /^0x[a-fA-F0-9]{40}$/;
            if (!bep20Regex.test(usdtAddress)) {
                showError('Invalid USDT BEP-20 address format. It must start with "0x" followed by 40 alphanumeric characters.');
                return;
            }

            // Loading state
            submitBtn.disabled = true;
            btnSpinner.style.display = 'inline-block';

            const formData = new FormData(taskForm);

            fetch('index.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .then(data => {
                submitBtn.disabled = false;
                btnSpinner.style.display = 'none';
                
                if (data.success) {
                    // Transition to success screen
                    mainContentFlow.style.display = 'none';
                    successFlow.style.display = 'flex';
                    window.scrollTo({ top: 0, behavior: 'smooth' });
                } else {
                    showError(data.message || 'Submission failed. Please try again.');
                }
            })
            .catch(error => {
                submitBtn.disabled = false;
                btnSpinner.style.display = 'none';
                showError('Something went wrong. Please check your connection and try again.');
                console.error('Error:', error);
            });
        });

        function showError(msg) {
            errorMessage.textContent = msg;
            formError.style.display = 'flex';
            const errorElement = document.getElementById('form-error');
            errorElement.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }

        function resetForm() {
            taskForm.reset();
            fileInput.value = '';
            previewImg.src = '#';
            previewBox.style.display = 'none';
            uploadInstruction.style.display = 'block';
            
            successFlow.style.display = 'none';
            mainContentFlow.style.display = 'block';
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }
    </script>
</body>
</html>
