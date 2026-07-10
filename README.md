# Quick Earn Task Event Website

A high-performance, fast-loading, single-page event landing page designed for task completion and reward management (USDT BEP-20). Built with a responsive dark-gold Web3 aesthetic, custom animated tickers, and a secure admin dashboard.

---

## Features
- **Modern Web3 Design**: Fully responsive, mobile-first design styled with CSS-based glassmorphism, glowing gold gradients, custom animations, and embedded SVGs (no external resources for blazing-fast load times).
- **Interactive Flow**: Seamless drag-and-drop screenshot uploads with real-time preview, form verification, and AJAX-based page transitions (no page reloads).
- **Social Proof**: A live rolling ticker showing recent successful submissions and payouts to boost conversions.
- **Robust Storage**: Self-healing data directories with automatic SQLite/JSON fallback storage. Includes `.htaccess` and `index.html` blocks to prevent direct data or screenshot directory downloads.
- **Admin Dashboard**: Full back-office suite (`admin.php`) to search, filter, review screenshots in an interactive modal, approve/reject submissions, and export winners to CSV for bulk payouts.

---

## File Structure
- `index.php` - The primary client-facing event landing page and backend AJAX receiver.
- `config.php` - Central configuration file for simple customizations.
- `admin.php` - Dashboard panel for managing entries and downloading reports.
- `data/` - Private directory holding user submission data (protected via `.htaccess` & `index.html`).
- `uploads/` - Directory storing uploaded user screenshots (protected via `index.html`).

---

## Getting Started

### 1. Upload to Server
Upload all files (`index.php`, `config.php`, `admin.php`, `data/`, `uploads/`) to your PHP-enabled web hosting directory (e.g., `public_html`).

### 2. Configure Event Details
Open `config.php` and update the parameters:
```php
// Event Configuration
$event_title = "Exclusive Reward Event";
$reward_amount = "$1 USDT";
$signup_link = "https://your-sponsor-link.com/register"; // <-- CHANGE THIS to your signup link

// Admin Settings
define('ADMIN_PASSWORD', 'your-secure-password'); // <-- CHANGE THIS to secure your admin panel
```

### 3. Folder Permissions
Ensure the web server has write permissions for the `data/` and `uploads/` directories:
- `data/` should be writable (typically permission code `0755` or `0777` if required by hosting).
- `uploads/` should be writable (typically permission code `0755` or `0777`).

---

## How to Manage Submissions

1. Navigate to your admin page: `http://yourdomain.com/admin.php`.
2. Log in using the password set in `config.php` (default is `admin123`).
3. You can:
   - View recent submissions (dates, User IDs, USDT wallet addresses, and screenshots).
   - **Click any screenshot** thumbnail to view it in full size.
   - **Click the Copy button** next to any USDT wallet address to copy it instantly.
   - Approve, Reject, or Delete submissions. (Deleting an entry also automatically deletes the screenshot file from the server).
   - **Export Approved (CSV)**: Click this button to download a spreadsheet of all approved winners, ready to import into a bulk USDT payment sender tool.
