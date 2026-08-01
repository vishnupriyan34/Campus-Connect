<?php
// Admin System Control: Staff & Admin User Account Management

$page_title = "System Control - Campus Connect";
$active_nav = "system_control";
require_once '../includes/auth.php';
checkRole(['admin']);
require_once '../includes/header.php';
require_once '../includes/audit.php';

$admin_id = $_SESSION['user_id'];
$error = '';
$success = '';

// Process Account Creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_user') {
    // Verify CSRF
    $token = $_POST['csrf_token'] ?? '';
    if (!verifyCsrfToken($token)) {
        $_SESSION['error_message'] = "Invalid CSRF session token.";
        header("Location: system_control.php");
        exit;
    }
    
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $role = $_POST['role'] ?? 'staff';
    
    if (empty($name) || empty($email) || empty($password)) {
        $error = "Please fill in all fields.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please provide a valid email address.";
    } elseif (strlen($password) < 6) {
        $error = "Password must be at least 6 characters long.";
    } elseif ($role !== 'staff' && $role !== 'admin') {
        $error = "Invalid role specified.";
    } else {
        try {
            // Check if user already exists
            $stmtCheck = $pdo->prepare("SELECT id FROM users WHERE email = :email");
            $stmtCheck->execute([':email' => $email]);
            
            if ($stmtCheck->fetch()) {
                $error = "A user account with this email address already exists.";
            } else {
                $password_hash = password_hash($password, PASSWORD_BCRYPT);
                
                $stmtInsert = $pdo->prepare("
                    INSERT INTO users (name, email, password_hash, role) 
                    VALUES (:name, :email, :password_hash, :role)
                ");
                $stmtInsert->execute([
                    ':name' => $name,
                    ':email' => $email,
                    ':password_hash' => $password_hash,
                    ':role' => $role
                ]);
                $new_user_id = $pdo->lastInsertId();
                
                // Add in-app notification to new user
                $stmtNotif = $pdo->prepare("INSERT INTO notifications (user_id, message) VALUES (:user_id, :message)");
                $stmtNotif->execute([
                    ':user_id' => $new_user_id,
                    ':message' => "Welcome to the Campus Connect administration board, $name! Your account role is set to: " . strtoupper($role)
                ]);
                
                logAudit('User Account Created', "Created $role account ID: $new_user_id (Name: $name, Email: $email)");
                
                $success = "Account successfully created for TPO / Administrator: <strong>" . sanitize($name) . "</strong>";
            }
        } catch (PDOException $e) {
            $error = "Database operation failed: " . $e->getMessage();
        }
    }
}

// Fetch all staff and admin accounts
try {
    $stmtUsers = $pdo->query("
        SELECT id, name, email, role, created_at 
        FROM users 
        WHERE role IN ('staff', 'admin') 
        ORDER BY role ASC, name ASC
    ");
    $accounts = $stmtUsers->fetchAll();
} catch (PDOException $e) {
    $accounts = [];
}

$csrf_token = getCsrfToken();
?>

<div class="page-title-area">
  <div>
    <h1 class="page-title">System Control Panel</h1>
    <p class="page-subtitle">Manage system access, roles, and configure placements administration credentials.</p>
  </div>
  <div>
    <a href="dashboard.php" class="btn btn-secondary btn-sm">
      <i class="fa-solid fa-arrow-left"></i> Back to Panel
    </a>
  </div>
</div>

<?php if (!empty($error)): ?>
  <div class="alert alert-error">
    <i class="fa-solid fa-circle-exclamation"></i> <?php echo sanitize($error); ?>
  </div>
<?php endif; ?>

<?php if (!empty($success)): ?>
  <div class="alert alert-success">
    <i class="fa-solid fa-circle-check"></i> <div><?php echo $success; ?></div>
  </div>
<?php endif; ?>

<div class="grid-2">
  <!-- Accounts Listing -->
  <div class="card">
    <h2 style="font-size: 1.25rem; font-weight: 600; margin-bottom: 1rem;"><i class="fa-solid fa-users" style="color: var(--color-primary);"></i> System Accounts</h2>
    <div class="table-container">
      <table>
        <thead>
          <tr>
            <th>Name & Email</th>
            <th>Role</th>
            <th>Created Date</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($accounts as $acc): ?>
            <tr>
              <td>
                <strong><?php echo sanitize($acc['name']); ?></strong>
                <div style="font-size: 0.75rem; color: var(--text-muted);"><?php echo sanitize($acc['email']); ?></div>
              </td>
              <td>
                <span class="badge badge-<?php echo $acc['role'] === 'admin' ? 'selected' : 'applied'; ?>" style="font-size: 0.75rem; text-transform: uppercase;">
                  <?php echo sanitize($acc['role']); ?>
                </span>
              </td>
              <td><?php echo date('M d, Y', strtotime($acc['created_at'])); ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
  
  <!-- Add Account Form -->
  <div class="card">
    <h2 style="font-size: 1.25rem; font-weight: 600; margin-bottom: 1rem;"><i class="fa-solid fa-user-plus" style="color: var(--color-accent);"></i> Register Admin/TPO Account</h2>
    
    <form action="system_control.php" method="POST">
      <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
      <input type="hidden" name="action" value="create_user">
      
      <div class="form-group">
        <label for="name" class="form-label">Full Name *</label>
        <input type="text" id="name" name="name" class="form-control" placeholder="e.g. Officer Rajesh Kumar" required value="<?php echo isset($_POST['name']) ? sanitize($_POST['name']) : ''; ?>">
      </div>
      
      <div class="form-group">
        <label for="email" class="form-label">Email Address *</label>
        <input type="email" id="email" name="email" class="form-control" placeholder="officer@campusconnect.com" required value="<?php echo isset($_POST['email']) ? sanitize($_POST['email']) : ''; ?>">
      </div>
      
      <div class="grid-2" style="gap: 1.5rem; margin-bottom: 1.25rem;">
        <div class="form-group" style="margin-bottom: 0;">
          <label for="password" class="form-label">Temporary Password *</label>
          <input type="password" id="password" name="password" class="form-control" placeholder="Min 6 characters" required>
        </div>
        
        <div class="form-group" style="margin-bottom: 0;">
          <label for="role" class="form-label">Access Permissions (Role) *</label>
          <select id="role" name="role" class="form-control" required>
            <option value="staff" selected>Placement Staff (TPO)</option>
            <option value="admin">Administrator</option>
          </select>
        </div>
      </div>
      
      <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 1rem;">
        <i class="fa-solid fa-user-plus"></i> Register Administrative Account
      </button>
    </form>
  </div>
</div>

<?php
require_once '../includes/footer.php';
?>
