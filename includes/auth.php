<?php
// Session check and security helper functions

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

/**
 * Ensures user is logged in and has one of the allowed roles.
 * Redirects to login or dashboard if unauthorized.
 * 
 * @param array $allowedRoles Array of allowed roles: ['student', 'staff', 'admin']
 */
function checkRole($allowedRoles) {
    if (!isset($_SESSION['user_id'])) {
        $_SESSION['error_message'] = "Please log in to access this page.";
        header("Location: ../login.php");
        exit;
    }
    
    $userRole = $_SESSION['role'];
    if (!in_array($userRole, $allowedRoles)) {
        $_SESSION['error_message'] = "Unauthorized access.";
        
        // Redirect based on their actual role
        if ($userRole === 'student') {
            header("Location: ../student/dashboard.php");
        } elseif ($userRole === 'staff') {
            header("Location: ../staff/dashboard.php");
        } elseif ($userRole === 'admin') {
            header("Location: ../admin/dashboard.php");
        } else {
            header("Location: ../login.php");
        }
        exit;
    }
}

/**
 * Generates a CSRF token and saves it in the session if not already set.
 * 
 * @return string CSRF token
 */
function getCsrfToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verifies if the POSTed CSRF token matches the session CSRF token.
 * 
 * @param string $token Token from form submission
 * @return bool True if valid, false otherwise
 */
function verifyCsrfToken($token) {
    if (empty($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Escapes HTML characters for output.
 * 
 * @param string $string Raw string
 * @return string HTML-escaped string
 */
function sanitize($string) {
    return htmlspecialchars($string ?? '', ENT_QUOTES, 'UTF-8');
}
?>
