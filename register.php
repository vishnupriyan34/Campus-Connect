<?php
// Student Registration Page

require_once 'config/db.php';
require_once 'includes/auth.php';

// If already logged in, redirect to dashboard
if (isset($_SESSION['user_id'])) {
    header("Location: student/dashboard.php");
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $department = $_POST['department'] ?? '';
    $cgpa = floatval($_POST['cgpa'] ?? 0);
    $backlog_count = intval($_POST['backlog_count'] ?? 0);
    $skills = trim($_POST['skills'] ?? '');

    // Basic Validation
    if (empty($name) || empty($email) || empty($password) || empty($department)) {
        $error = "Please fill in all required fields.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    } elseif ($password !== $confirm_password) {
        $error = "Passwords do not match.";
    } elseif (strlen($password) < 6) {
        $error = "Password must be at least 6 characters long.";
    } elseif ($cgpa < 0 || $cgpa > 10) {
        $error = "CGPA must be between 0.00 and 10.00.";
    } elseif ($backlog_count < 0) {
        $error = "Backlog count cannot be negative.";
    } else {
        try {
            // Check if email already exists
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = :email");
            $stmt->execute([':email' => $email]);
            if ($stmt->fetch()) {
                $error = "Email address is already registered.";
            } else {
                // Begin Transaction to write to both users and students
                $pdo->beginTransaction();
                
                $password_hash = password_hash($password, PASSWORD_BCRYPT);
                
                // 1. Insert into users
                $stmtUser = $pdo->prepare("
                    INSERT INTO users (name, email, password_hash, role) 
                    VALUES (:name, :email, :password_hash, 'student')
                ");
                $stmtUser->execute([
                    ':name' => $name,
                    ':email' => $email,
                    ':password_hash' => $password_hash
                ]);
                
                $user_id = $pdo->lastInsertId();
                
                // 2. Insert into students
                $stmtStudent = $pdo->prepare("
                    INSERT INTO students (user_id, department, cgpa, backlog_count, skills) 
                    VALUES (:user_id, :department, :cgpa, :backlog_count, :skills)
                ");
                $stmtStudent->execute([
                    ':user_id' => $user_id,
                    ':department' => $department,
                    ':cgpa' => $cgpa,
                    ':backlog_count' => $backlog_count,
                    ':skills' => $skills
                ]);
                
                // Create a welcome notification
                $stmtNotif = $pdo->prepare("
                    INSERT INTO notifications (user_id, message) 
                    VALUES (:user_id, :message)
                ");
                $stmtNotif->execute([
                    ':user_id' => $user_id,
                    ':message' => "Welcome to Campus Connect, $name! Upload your resume in the Profile section to start applying for drives."
                ]);
                
                $pdo->commit();
                
                $_SESSION['success_message'] = "Registration successful! You can now log in.";
                header("Location: login.php");
                exit;
            }
        } catch (PDOException $e) {
            $pdo->rollBack();
            $error = "Registration failed: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Student Registration - Campus Connect</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="css/variables.css">
  <link rel="stylesheet" href="css/style.css">
</head>
<body class="auth-wrapper" style="padding: 1rem 1.5rem;">

  <div class="auth-card" style="max-width: 550px; padding: 2rem;">
    <div class="auth-header" style="margin-bottom: 1.5rem;">
      <a href="#" class="logo" style="justify-content: center; margin-bottom: 0.25rem; font-size: 1.75rem;">
        <i class="fa-solid fa-graduation-cap"></i> Campus Connect
      </a>
      <p>Create your Student Profile</p>
    </div>
    
    <?php if (!empty($error)): ?>
      <div class="alert alert-error" style="padding: 0.75rem 1rem; font-size: 0.85rem; margin-bottom: 1.25rem;">
        <i class="fa-solid fa-circle-exclamation"></i> <?php echo sanitize($error); ?>
      </div>
    <?php endif; ?>
    
    <form action="register.php" method="POST">
      <div class="grid-2" style="gap: 1rem; margin-bottom: 1rem;">
        <div class="form-group" style="margin-bottom: 0.5rem;">
          <label for="name" class="form-label">Full Name *</label>
          <input type="text" id="name" name="name" class="form-control" placeholder="John Doe" required value="<?php echo isset($_POST['name']) ? sanitize($_POST['name']) : ''; ?>">
        </div>
        
        <div class="form-group" style="margin-bottom: 0.5rem;">
          <label for="email" class="form-label">Email Address *</label>
          <input type="email" id="email" name="email" class="form-control" placeholder="john@campusconnect.com" required value="<?php echo isset($_POST['email']) ? sanitize($_POST['email']) : ''; ?>">
        </div>
      </div>
      
      <div class="grid-2" style="gap: 1rem; margin-bottom: 1rem;">
        <div class="form-group" style="margin-bottom: 0.5rem;">
          <label for="password" class="form-label">Password *</label>
          <input type="password" id="password" name="password" class="form-control" placeholder="Min 6 characters" required>
        </div>
        
        <div class="form-group" style="margin-bottom: 0.5rem;">
          <label for="confirm_password" class="form-label">Confirm Password *</label>
          <input type="password" id="confirm_password" name="confirm_password" class="form-control" placeholder="Retype password" required>
        </div>
      </div>
      
      <div class="grid-3" style="gap: 1rem; margin-bottom: 1rem;">
        <div class="form-group" style="margin-bottom: 0.5rem;">
          <label for="department" class="form-label">Department *</label>
          <select id="department" name="department" class="form-control" required>
            <option value="" disabled selected>Select</option>
            <option value="CSE" <?php echo (isset($_POST['department']) && $_POST['department'] === 'CSE') ? 'selected' : ''; ?>>CSE</option>
            <option value="ECE" <?php echo (isset($_POST['department']) && $_POST['department'] === 'ECE') ? 'selected' : ''; ?>>ECE</option>
            <option value="ME" <?php echo (isset($_POST['department']) && $_POST['department'] === 'ME') ? 'selected' : ''; ?>>ME</option>
            <option value="CE" <?php echo (isset($_POST['department']) && $_POST['department'] === 'CE') ? 'selected' : ''; ?>>CE</option>
            <option value="IT" <?php echo (isset($_POST['department']) && $_POST['department'] === 'IT') ? 'selected' : ''; ?>>IT</option>
          </select>
        </div>
        
        <div class="form-group" style="margin-bottom: 0.5rem;">
          <label for="cgpa" class="form-label">CGPA *</label>
          <input type="number" id="cgpa" name="cgpa" class="form-control" placeholder="e.g. 8.50" step="0.01" min="0" max="10" required value="<?php echo isset($_POST['cgpa']) ? sanitize($_POST['cgpa']) : ''; ?>">
        </div>
        
        <div class="form-group" style="margin-bottom: 0.5rem;">
          <label for="backlog_count" class="form-label">Backlogs *</label>
          <input type="number" id="backlog_count" name="backlog_count" class="form-control" placeholder="0" min="0" required value="<?php echo isset($_POST['backlog_count']) ? sanitize($_POST['backlog_count']) : '0'; ?>">
        </div>
      </div>
      
      <div class="form-group" style="margin-bottom: 1.25rem;">
        <label for="skills" class="form-label">Skills (Comma-separated)</label>
        <input type="text" id="skills" name="skills" class="form-control" placeholder="Java, SQL, Git, HTML" value="<?php echo isset($_POST['skills']) ? sanitize($_POST['skills']) : ''; ?>">
      </div>
      
      <button type="submit" class="btn btn-primary" style="width: 100%;">
        <i class="fa-solid fa-user-plus"></i> Register
      </button>
    </form>
    
    <div class="auth-footer" style="margin-top: 1.25rem;">
      Already registered? <a href="login.php">Login here</a>
    </div>
  </div>

</body>
</html>
