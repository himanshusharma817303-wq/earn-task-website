<?php
/**
 * Admin Panel for Quick Earn Task
 * View submissions, download screenshots, export CSV, change status.
 */

session_start();
require_once 'config.php';

// Authentication Logic
if (isset($_POST['password'])) {
    if ($_POST['password'] === ADMIN_PASSWORD) {
        $_SESSION['admin_logged_in'] = true;
        header('Location: admin.php');
        exit;
    } else {
        $login_error = "Invalid password. Please try again.";
    }
}

if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    unset($_SESSION['admin_logged_in']);
    session_destroy();
    header('Location: admin.php');
    exit;
}

// Check login status
$is_logged_in = isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;

// Helper to load submissions
function getSubmissions() {
    if (!file_exists(SUBMISSIONS_FILE)) {
        return [];
    }
    $jsonData = file_get_contents(SUBMISSIONS_FILE);
    $data = json_decode($jsonData, true);
    return is_array($data) ? $data : [];
}

// Helper to save submissions
function saveSubmissions($data) {
    if (!file_exists(DATA_DIR)) {
        @mkdir(DATA_DIR, 0755, true);
    }
    return file_put_contents(SUBMISSIONS_FILE, json_encode($data, JSON_PRETTY_PRINT), LOCK_EX);
}

// Action Handlers
if ($is_logged_in) {
    // Export CSV Action
    if (isset($_GET['action']) && $_GET['action'] === 'export') {
        $submissions = getSubmissions();
        
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=approved_usdt_winners_' . date('Ymd_His') . '.csv');
        
        $output = fopen('php://output', 'w');
        // Column headers
        fputcsv($output, ['User ID', 'USDT Address', 'Submitted At', 'Status']);
        
        foreach ($submissions as $sub) {
            // Only export approved by default, or export according to filter
            if ($sub['status'] === 'approved') {
                fputcsv($output, [
                    $sub['user_id'],
                    $sub['usdt_address'],
                    $sub['submitted_at'],
                    $sub['status']
                ]);
            }
        }
        fclose($output);
        exit;
    }

    // Status Change Action (Approve / Reject)
    if (isset($_GET['action']) && $_GET['action'] === 'set_status' && isset($_GET['id']) && isset($_GET['status'])) {
        $id = $_GET['id'];
        $status = $_GET['status'];
        
        if (in_array($status, ['pending', 'approved', 'rejected'])) {
            $submissions = getSubmissions();
            $updated = false;
            foreach ($submissions as &$sub) {
                if ($sub['id'] === $id) {
                    $sub['status'] = $status;
                    $updated = true;
                    break;
                }
            }
            if ($updated) {
                saveSubmissions($submissions);
            }
        }
        
        // Redirect back cleanly without query params triggering again
        header('Location: admin.php' . (isset($_GET['filter']) ? '?filter=' . urlencode($_GET['filter']) : ''));
        exit;
    }

    // Delete Action
    if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
        $id = $_GET['id'];
        $submissions = getSubmissions();
        $filtered = [];
        $deleted = false;
        
        foreach ($submissions as $sub) {
            if ($sub['id'] === $id) {
                // Delete screenshot file from disk
                $filePath = UPLOAD_DIR . '/' . $sub['screenshot'];
                if (file_exists($filePath)) {
                    @unlink($filePath);
                }
                $deleted = true;
            } else {
                $filtered[] = $sub;
            }
        }
        
        if ($deleted) {
            saveSubmissions($filtered);
        }
        
        header('Location: admin.php' . (isset($_GET['filter']) ? '?filter=' . urlencode($_GET['filter']) : ''));
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel - Quick Task Submissions</title>
    
    <!-- Google Font 'Inter' for premium typography -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --bg-dark: #f8fafc;
            --bg-card: #ffffff;
            --border-color: #e2e8f0;
            --accent-gold: #2563eb;
            --accent-gradient: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
            --text-main: #0f172a;
            --text-muted: #64748b;
            --success-color: #16a34a;
            --error-color: #dc2626;
            --warning-color: #d97706;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
        }

        body {
            background-color: var(--bg-dark);
            color: var(--text-main);
            min-height: 100vh;
            padding: 20px;
        }

        /* Container */
        .container {
            max-width: 1200px;
            margin: 0 auto;
        }

        /* Top Header */
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-bottom: 20px;
            border-bottom: 1px solid var(--border-color);
            margin-bottom: 30px;
        }

        .header h1 {
            font-size: 1.8rem;
            font-weight: 800;
            background: var(--accent-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .btn {
            padding: 10px 18px;
            border-radius: 8px;
            font-size: 0.85rem;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all 0.2s;
            border: none;
        }

        .btn-gold {
            background: var(--accent-gradient);
            color: #ffffff;
        }

        .btn-gold:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.2);
        }

        .btn-outline {
            background: transparent;
            color: var(--text-main);
            border: 1px solid var(--border-color);
        }

        .btn-outline:hover {
            background: #f1f5f9;
            border-color: #cbd5e1;
        }

        .btn-danger {
            background: #fef2f2;
            color: var(--error-color);
            border: 1px solid #fca5a5;
        }

        .btn-danger:hover {
            background: var(--error-color);
            color: #fff;
        }

        /* Login Card */
        .login-card {
            max-width: 400px;
            margin: 100px auto 0;
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.03);
        }

        .login-card h2 {
            font-size: 1.4rem;
            margin-bottom: 20px;
            font-weight: 700;
            text-align: center;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-label {
            display: block;
            font-size: 0.85rem;
            margin-bottom: 8px;
            color: var(--text-muted);
        }

        .form-input {
            width: 100%;
            background: #ffffff;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            padding: 12px;
            color: #0f172a;
            font-size: 0.95rem;
        }

        .form-input:focus {
            outline: none;
            border-color: var(--accent-gold);
            box-shadow: 0 0 0 2px rgba(37, 99, 235, 0.15);
        }

        .error-message {
            color: var(--error-color);
            background: #fef2f2;
            border: 1px solid #fca5a5;
            padding: 10px;
            border-radius: 6px;
            font-size: 0.8rem;
            margin-bottom: 20px;
            text-align: center;
        }

        /* Dashboard elements */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 20px;
            text-align: center;
        }

        .stat-number {
            font-size: 2rem;
            font-weight: 800;
            margin-bottom: 5px;
        }

        .stat-number.gold { color: var(--accent-gold); }
        .stat-number.green { color: var(--success-color); }
        .stat-number.red { color: var(--error-color); }

        .stat-label {
            font-size: 0.8rem;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* Filter bar */
        .filter-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 15px 20px;
            margin-bottom: 20px;
        }

        .filters {
            display: flex;
            gap: 10px;
        }

        .filter-link {
            text-decoration: none;
            color: var(--text-muted);
            padding: 6px 12px;
            border-radius: 6px;
            font-size: 0.8rem;
            font-weight: 600;
            border: 1px solid transparent;
            transition: all 0.2s;
        }

        .filter-link:hover {
            color: var(--text-main);
            background: #f1f5f9;
        }

        .filter-link.active {
            background: #eff6ff;
            border-color: #bfdbfe;
            color: #1d4ed8;
        }

        /* Table design */
        .table-responsive {
            width: 100%;
            overflow-x: auto;
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        }

        table {
            width: 100%;
            border-collapse: collapse;
            text-align: left;
        }

        th {
            background: #f8fafc;
            padding: 16px 20px;
            font-size: 0.8rem;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 1px solid var(--border-color);
        }

        td {
            padding: 16px 20px;
            border-bottom: 1px solid var(--border-color);
            font-size: 0.9rem;
            vertical-align: middle;
        }

        tr:last-child td {
            border-bottom: none;
        }

        tr:hover td {
            background: #f8fafc;
        }

        .badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 50px;
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
        }

        .badge-pending {
            background: #fffbeb;
            color: var(--warning-color);
            border: 1px solid #fef3c7;
        }

        .badge-approved {
            background: #f0fdf4;
            color: var(--success-color);
            border: 1px solid #bbf7d0;
        }

        .badge-rejected {
            background: #fef2f2;
            color: var(--error-color);
            border: 1px solid #fca5a5;
        }

        /* Screenshot preview */
        .screenshot-thumbnail {
            width: 60px;
            height: 60px;
            object-fit: cover;
            border-radius: 6px;
            border: 1px solid var(--border-color);
            cursor: pointer;
            transition: transform 0.2s;
        }

        .screenshot-thumbnail:hover {
            transform: scale(1.1);
        }

        /* Action icons */
        .actions-cell {
            display: flex;
            gap: 6px;
            align-items: center;
        }

        .act-btn {
            background: none;
            border: none;
            cursor: pointer;
            width: 32px;
            height: 32px;
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s;
        }

        .act-btn-approve {
            background: #f0fdf4;
            color: var(--success-color);
            border: 1px solid #bbf7d0;
        }

        .act-btn-approve:hover {
            background: var(--success-color);
            color: #ffffff;
            border-color: var(--success-color);
        }

        .act-btn-reject {
            background: #fffbeb;
            color: var(--warning-color);
            border: 1px solid #fef3c7;
        }

        .act-btn-reject:hover {
            background: var(--warning-color);
            color: #ffffff;
            border-color: var(--warning-color);
        }

        /* Clipboard Copy Utility */
        .copy-box {
            display: flex;
            align-items: center;
            gap: 6px;
            font-family: monospace;
            background: #f1f5f9;
            padding: 4px 8px;
            border-radius: 6px;
            border: 1px solid #cbd5e1;
            width: max-content;
        }

        .copy-btn {
            background: none;
            border: none;
            color: var(--accent-gold);
            cursor: pointer;
            padding: 2px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .copy-btn:hover {
            color: #1d4ed8;
        }

        /* Modal styling */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.8);
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .modal-content {
            max-width: 90%;
            max-height: 90vh;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            display: block;
        }

        .modal-close {
            position: absolute;
            top: 20px;
            right: 30px;
            color: #fff;
            font-size: 40px;
            font-weight: 700;
            cursor: pointer;
            user-select: none;
        }

        /* Empty state */
        .empty-state {
            padding: 50px 20px;
            text-align: center;
            color: var(--text-muted);
            font-size: 0.95rem;
        }

        .toast {
            position: fixed;
            bottom: 20px;
            right: 20px;
            background: var(--success-color);
            color: #ffffff;
            padding: 10px 20px;
            border-radius: 6px;
            font-weight: 700;
            font-size: 0.85rem;
            box-shadow: 0 4px 12px rgba(22, 163, 74, 0.3);
            display: none;
            animation: slideUp 0.3s ease forwards;
            z-index: 2000;
        }

        @keyframes slideUp {
            from { transform: translateY(50px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
    </style>
</head>
<body>

    <?php if (!$is_logged_in): ?>
        <!-- Login Screen -->
        <div class="login-card">
            <h2>Admin Login</h2>
            <?php if (isset($login_error)): ?>
                <div class="error-message"><?php echo $login_error; ?></div>
            <?php endif; ?>
            <form action="admin.php" method="POST">
                <div class="form-group">
                    <label class="form-label" for="password">Enter Admin Password</label>
                    <input type="password" id="password" name="password" class="form-input" placeholder="Password" required autofocus autocomplete="off">
                </div>
                <button type="submit" class="btn btn-gold" style="width: 100%; justify-content: center;">Login</button>
            </form>
        </div>
    <?php else: 
        // Load & calculate stats
        $submissions = getSubmissions();
        $total = count($submissions);
        $pending = 0;
        $approved = 0;
        $rejected = 0;
        
        foreach ($submissions as $sub) {
            if ($sub['status'] === 'pending') $pending++;
            elseif ($sub['status'] === 'approved') $approved++;
            elseif ($sub['status'] === 'rejected') $rejected++;
        }
        
        // Apply filter
        $filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';
        $display_submissions = [];
        
        foreach ($submissions as $sub) {
            if ($filter === 'all') {
                $display_submissions[] = $sub;
            } elseif ($filter === $sub['status']) {
                $display_submissions[] = $sub;
            }
        }
        
        // Reverse array to see newest submissions first
        $display_submissions = array_reverse($display_submissions);
    ?>
        <!-- Admin Dashboard -->
        <div class="container">
            <div class="header">
                <div>
                    <h1>Quick Task Dashboard</h1>
                    <p style="font-size: 0.8rem; color: var(--text-muted); margin-top: 4px;">Manage user task submissions & payouts</p>
                </div>
                <div style="display: flex; gap: 10px;">
                    <a href="admin.php?action=export" class="btn btn-gold">
                        <svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor"><path d="M19.35 10.04C18.67 6.59 15.64 4 12 4 9.11 4 6.6 5.64 5.35 8.04 2.34 8.36 0 10.91 0 14c0 3.31 2.69 6 6 6h13c2.76 0 5-2.24 5-5 0-2.64-2.05-4.78-4.65-4.96zM17 13l-5 5-5-5h3V9h4v4h3z"/></svg>
                        Export Approved (CSV)
                    </a>
                    <a href="admin.php?action=logout" class="btn btn-outline">Logout</a>
                </div>
            </div>

            <!-- Stats grid -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-number"><?php echo $total; ?></div>
                    <div class="stat-label">Total Submissions</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number gold"><?php echo $pending; ?></div>
                    <div class="stat-label">Pending Review</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number green"><?php echo $approved; ?></div>
                    <div class="stat-label">Approved Winners</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number red"><?php echo $rejected; ?></div>
                    <div class="stat-label">Rejected entries</div>
                </div>
            </div>

            <!-- Filter bar -->
            <div class="filter-bar">
                <div class="filters">
                    <a href="admin.php?filter=all" class="filter-link <?php echo $filter === 'all' ? 'active' : ''; ?>">All (<?php echo $total; ?>)</a>
                    <a href="admin.php?filter=pending" class="filter-link <?php echo $filter === 'pending' ? 'active' : ''; ?>">Pending (<?php echo $pending; ?>)</a>
                    <a href="admin.php?filter=approved" class="filter-link <?php echo $filter === 'approved' ? 'active' : ''; ?>">Approved (<?php echo $approved; ?>)</a>
                    <a href="admin.php?filter=rejected" class="filter-link <?php echo $filter === 'rejected' ? 'active' : ''; ?>">Rejected (<?php echo $rejected; ?>)</a>
                </div>
                <div style="font-size: 0.8rem; color: var(--text-muted);">
                    Showing <?php echo count($display_submissions); ?> records
                </div>
            </div>

            <!-- Submissions Table -->
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Date / IP</th>
                            <th>User ID</th>
                            <th>USDT BEP-20 Address</th>
                            <th>Screenshot</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($display_submissions)): ?>
                            <tr>
                                <td colspan="6" class="empty-state">No submissions found in this category.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($display_submissions as $sub): ?>
                                <tr>
                                    <td>
                                        <div style="font-weight: 600;"><?php echo date('M d, H:i', strtotime($sub['submitted_at'])); ?></div>
                                        <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 2px;"><?php echo htmlspecialchars($sub['ip']); ?></div>
                                    </td>
                                    <td style="font-weight: 600;"><?php echo htmlspecialchars($sub['user_id']); ?></td>
                                    <td>
                                        <div class="copy-box">
                                            <span><?php echo htmlspecialchars($sub['usdt_address']); ?></span>
                                            <button class="copy-btn" onclick="copyAddress('<?php echo htmlspecialchars($sub['usdt_address']); ?>')" title="Copy Address">
                                                <svg viewBox="0 0 24 24" width="14" height="14" fill="currentColor"><path d="M16 1H4c-1.1 0-2 .9-2 2v14h2V3h12V1zm3 4H8c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h11c1.1 0 2-.9 2-2V7c0-1.1-.9-2-2-2zm0 16H8V7h11v14z"/></svg>
                                            </button>
                                        </div>
                                    </td>
                                    <td>
                                        <?php if (file_exists(UPLOAD_DIR . '/' . $sub['screenshot'])): ?>
                                            <img src="uploads/<?php echo htmlspecialchars($sub['screenshot']); ?>" class="screenshot-thumbnail" onclick="openModal(this.src)" alt="Proof Screenshot">
                                        <?php else: ?>
                                            <span style="color: var(--error-color); font-size: 0.8rem;">Image Missing</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge badge-<?php echo $sub['status']; ?>">
                                            <?php echo htmlspecialchars($sub['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="actions-cell">
                                            <!-- Approve Button -->
                                            <a href="admin.php?action=set_status&id=<?php echo $sub['id']; ?>&status=approved&filter=<?php echo $filter; ?>" class="act-btn act-btn-approve" title="Approve Entry">
                                                <svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg>
                                            </a>
                                            <!-- Reject Button -->
                                            <a href="admin.php?action=set_status&id=<?php echo $sub['id']; ?>&status=rejected&filter=<?php echo $filter; ?>" class="act-btn act-btn-reject" title="Reject Entry">
                                                <svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor"><path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/></svg>
                                            </a>
                                            <!-- Delete Button -->
                                            <a href="admin.php?action=delete&id=<?php echo $sub['id']; ?>&filter=<?php echo $filter; ?>" class="act-btn btn-danger" style="width:32px; height:32px;" onclick="return confirm('Are you sure you want to delete this submission and its screenshot?')" title="Delete Entry">
                                                <svg viewBox="0 0 24 24" width="14" height="14" fill="currentColor"><path d="M6 19c0 1.1.9 2 2 2h8c1.1 0 2-.9 2-2V7H6v12zM19 4h-3.5l-1-1h-5l-1 1H5v2h14V4z"/></svg>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Screenshot Modal View -->
        <div id="imageModal" class="modal" onclick="closeModal()">
            <span class="modal-close" onclick="closeModal()">&times;</span>
            <img class="modal-content" id="modalImg" onclick="event.stopPropagation()">
        </div>

        <!-- Toast Alerts -->
        <div id="toast" class="toast">Address copied to clipboard!</div>

        <script>
            // Modal controls
            function openModal(imgSrc) {
                const modal = document.getElementById('imageModal');
                const modalImg = document.getElementById('modalImg');
                modal.style.display = 'flex';
                modalImg.src = imgSrc;
            }

            function closeModal() {
                document.getElementById('imageModal').style.display = 'none';
            }

            // ESC key to close modal
            document.addEventListener('keydown', function(event) {
                if (event.key === "Escape") {
                    closeModal();
                }
            });

            // Clipboard Copy Address
            function copyAddress(address) {
                navigator.clipboard.writeText(address).then(() => {
                    const toast = document.getElementById('toast');
                    toast.style.display = 'block';
                    setTimeout(() => {
                        toast.style.opacity = '0';
                        setTimeout(() => {
                            toast.style.display = 'none';
                            toast.style.opacity = '1';
                        }, 300);
                    }, 2000);
                }).catch(err => {
                    console.error('Could not copy text: ', err);
                });
            }
        </script>
    <?php endif; ?>

</body>
</html>
