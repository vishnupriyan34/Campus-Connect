<?php
// User Login Page

require_once 'config/db.php';
require_once 'includes/auth.php';

// If already logged in, redirect to respective dashboard
if (isset($_SESSION['user_id'])) {
    $role = $_SESSION['role'];
    if ($role === 'student') header("Location: student/dashboard.php");
    elseif ($role === 'staff') header("Location: staff/dashboard.php");
    elseif ($role === 'admin') header("Location: admin/dashboard.php");
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($email) || empty($password)) {
        $error = "Please fill in all fields.";
    } else {
        try {
            $stmt = $pdo->prepare("SELECT * FROM users WHERE email = :email");
            $stmt->execute([':email' => $email]);
            $user = $stmt->fetch();
            
            if ($user && password_verify($password, $user['password_hash'])) {
                // Set sessions
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['name'] = $user['name'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['role'] = $user['role'];
                
                // Log audit action if staff or admin
                if ($user['role'] !== 'student') {
                    // Load audit helper
                    require_once 'includes/audit.php';
                    logAudit('User Logged In', 'User IP: ' . $_SERVER['REMOTE_ADDR'], $user['id']);
                }
                
                // Redirect based on role
                if ($user['role'] === 'student') {
                    header("Location: student/dashboard.php");
                } elseif ($user['role'] === 'staff') {
                    header("Location: staff/dashboard.php");
                } elseif ($user['role'] === 'admin') {
                    header("Location: admin/dashboard.php");
                }
                exit;
            } else {
                $error = "Invalid email or password.";
            }
        } catch (PDOException $e) {
            $error = "Database error: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Login - Campus Connect</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="css/variables.css">
  <link rel="stylesheet" href="css/style.css">
</head>
<body class="auth-wrapper">

  <div class="auth-card">
    <div class="auth-header">
      <a href="#" class="logo" style="justify-content: center; margin-bottom: 0.5rem; font-size: 1.75rem;">
        <i class="fa-solid fa-graduation-cap"></i> Campus Connect
      </a>
      <p>Log in to access your placement dashboard</p>
    </div>
    
    <?php if (!empty($error)): ?>
      <div class="alert alert-error" style="padding: 0.75rem 1rem; font-size: 0.85rem; margin-bottom: 1.25rem;">
        <i class="fa-solid fa-circle-exclamation"></i> <?php echo sanitize($error); ?>
      </div>
    <?php endif; ?>
    
    <?php
    // Display register success message
    if (isset($_SESSION['success_message'])) {
        echo '<div class="alert alert-success" style="padding: 0.75rem 1rem; font-size: 0.85rem; margin-bottom: 1.25rem;"><i class="fa-solid fa-circle-check"></i> ' . sanitize($_SESSION['success_message']) . '</div>';
        unset($_SESSION['success_message']);
    }
    ?>
    
    <form action="login.php" method="POST">
      <div class="form-group">
        <label for="email" class="form-label">Email Address</label>
        <input type="email" id="email" name="email" class="form-control" placeholder="name@campusconnect.com" required value="<?php echo isset($_POST['email']) ? sanitize($_POST['email']) : ''; ?>">
      </div>
      
      <div class="form-group">
        <label for="password" class="form-label">Password</label>
        <input type="password" id="password" name="password" class="form-control" placeholder="&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;" required>
      </div>
      
      <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 0.75rem;">
        <i class="fa-solid fa-right-to-bracket"></i> Sign In
      </button>
    </form>
    
    <div class="auth-footer">
      Don't have a student account? <a href="register.php">Register here</a>
    </div>
  </div>

</body>
</html>
