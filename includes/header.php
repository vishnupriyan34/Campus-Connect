<?php
// Shared Header with Navigation and Notification Polling Setup

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../config/db.php';

// Generate current user initials
$initials = 'CC';
if (isset($_SESSION['name'])) {
    $words = explode(' ', $_SESSION['name']);
    $initials = strtoupper(substr($words[0], 0, 1) . (isset($words[1]) ? substr($words[1], 0, 1) : ''));
}

$role = $_SESSION['role'] ?? '';
$root_prefix = "../"; // default prefix since dashboards are in folders
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?php echo $page_title ?? 'Campus Connect'; ?></title>
  
  <!-- FontAwesome for Icons -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  
  <!-- Core Design System CSS -->
  <link rel="stylesheet" href="<?php echo $root_prefix; ?>css/variables.css">
  <link rel="stylesheet" href="<?php echo $root_prefix; ?>css/style.css">
  <link rel="stylesheet" href="<?php echo $root_prefix; ?>css/dashboards.css">
  
  <!-- Theme Flash Blocker -->
  <script>
    if (localStorage.getItem('theme') === 'light') {
      document.documentElement.classList.add('light-theme');
    }
  </script>
</head>
<body>

<header>
  <div class="container navbar">
    <a href="<?php echo $root_prefix; ?>" class="logo">
      <i class="fa-solid fa-graduation-cap"></i>
      Campus Connect
    </a>
    
    <nav>
      <ul class="nav-links">
        <?php if ($role === 'student'): ?>
          <li><a href="<?php echo $root_prefix; ?>student/dashboard.php" class="<?php echo $active_nav === 'dashboard' ? 'active' : ''; ?>"><i class="fa-solid fa-chart-line"></i> Dashboard</a></li>
          <li><a href="<?php echo $root_prefix; ?>student/profile.php" class="<?php echo $active_nav === 'profile' ? 'active' : ''; ?>"><i class="fa-solid fa-user-graduate"></i> My Profile</a></li>
          <li><a href="<?php echo $root_prefix; ?>student/offers.php" class="<?php echo $active_nav === 'offers' ? 'active' : ''; ?>"><i class="fa-solid fa-file-invoice"></i> Offer Letters</a></li>
        
        <?php elseif ($role === 'staff'): ?>
          <li><a href="<?php echo $root_prefix; ?>staff/dashboard.php" class="<?php echo $active_nav === 'dashboard' ? 'active' : ''; ?>"><i class="fa-solid fa-gauge"></i> Dashboard</a></li>
          <li><a href="<?php echo $root_prefix; ?>staff/drives.php" class="<?php echo $active_nav === 'drives' ? 'active' : ''; ?>"><i class="fa-solid fa-briefcase"></i> Drives</a></li>
          <li><a href="<?php echo $root_prefix; ?>staff/verify.php" class="<?php echo $active_nav === 'verify' ? 'active' : ''; ?>"><i class="fa-solid fa-user-check"></i> Verifications</a></li>
          <li><a href="<?php echo $root_prefix; ?>staff/upload_offer.php" class="<?php echo $active_nav === 'upload_offer' ? 'active' : ''; ?>"><i class="fa-solid fa-file-arrow-up"></i> Upload Offer</a></li>
          <li><a href="<?php echo $root_prefix; ?>staff/email.php" class="<?php echo $active_nav === 'email' ? 'active' : ''; ?>"><i class="fa-solid fa-paper-plane"></i> Communications</a></li>
          <li><a href="<?php echo $root_prefix; ?>staff/analytics.php" class="<?php echo $active_nav === 'analytics' ? 'active' : ''; ?>"><i class="fa-solid fa-chart-pie"></i> Analytics</a></li>
        
        <?php elseif ($role === 'admin'): ?>
          <li><a href="<?php echo $root_prefix; ?>admin/dashboard.php" class="<?php echo $active_nav === 'dashboard' ? 'active' : ''; ?>"><i class="fa-solid fa-sliders"></i> Admin Panel</a></li>
          <li><a href="<?php echo $root_prefix; ?>admin/companies.php" class="<?php echo $active_nav === 'companies' ? 'active' : ''; ?>"><i class="fa-solid fa-building"></i> Companies</a></li>
          <li><a href="<?php echo $root_prefix; ?>admin/system_control.php" class="<?php echo $active_nav === 'system_control' ? 'active' : ''; ?>"><i class="fa-solid fa-users-gear"></i> System Control</a></li>
          <li><a href="<?php echo $root_prefix; ?>admin/audit.log.php" class="<?php echo $active_nav === 'audit' ? 'active' : ''; ?>"><i class="fa-solid fa-list-check"></i> Audit Logs</a></li>
        <?php endif; ?>
      </ul>
    </nav>
    
    <div class="nav-controls">
      <!-- Theme Switch Toggle Button -->
      <button class="notif-bell" id="themeToggleBtn" title="Toggle Light/Dark Mode" style="margin-right: 0.5rem; outline: none;">
        <i class="fa-solid fa-moon" id="themeToggleIcon"></i>
      </button>

      <!-- Notification Bell -->
      <div class="notif-bell-container" id="notifBellContainer">
        <button class="notif-bell" id="notifBellBtn" aria-label="Notifications">
          <i class="fa-solid fa-bell"></i>
          <span class="notif-badge hidden" id="notifBadge">0</span>
        </button>
        
        <!-- Dropdown menu -->
        <div class="notif-dropdown" id="notifDropdown">
          <div class="notif-header">
            <h4>Notifications</h4>
            <button class="mark-read-btn" id="markReadBtn">Mark all read</button>
          </div>
          <ul class="notif-list" id="notifList">
            <li class="notif-empty">No notifications</li>
          </ul>
        </div>
      </div>
      
      <!-- User profile details -->
      <div class="user-menu">
        <div class="user-avatar"><?php echo $initials; ?></div>
        <div class="user-info">
          <span class="user-name"><?php echo sanitize($_SESSION['name'] ?? 'Guest'); ?></span>
          <span class="user-role"><?php echo sanitize($role); ?></span>
        </div>
        <a href="<?php echo $root_prefix; ?>logout.php" class="btn btn-secondary btn-sm" title="Log Out" style="margin-left: 0.5rem; padding: 0.3rem 0.6rem;">
          <i class="fa-solid fa-right-from-bracket"></i>
        </a>
      </div>
    </div>
  </div>
</header>

<main class="container" style="min-height: 80vh; padding-top: 1.5rem; padding-bottom: 2rem;">
<?php
// Display flash messages
if (isset($_SESSION['success_message'])) {
    echo '<div class="alert alert-success"><i class="fa-solid fa-circle-check"></i> ' . sanitize($_SESSION['success_message']) . '</div>';
    unset($_SESSION['success_message']);
}
if (isset($_SESSION['error_message'])) {
    echo '<div class="alert alert-error"><i class="fa-solid fa-circle-exclamation"></i> ' . sanitize($_SESSION['error_message']) . '</div>';
    unset($_SESSION['error_message']);
}
?>
