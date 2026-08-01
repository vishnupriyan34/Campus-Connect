<?php
// Root entry redirect handler

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

if (isset($_SESSION['user_id'])) {
    $role = $_SESSION['role'];
    if ($role === 'student') {
        header("Location: student/dashboard.php");
    } elseif ($role === 'staff') {
        header("Location: staff/dashboard.php");
    } elseif ($role === 'admin') {
        header("Location: admin/dashboard.php");
    } else {
        header("Location: login.php");
    }
} else {
    header("Location: login.php");
}
exit;
?>
