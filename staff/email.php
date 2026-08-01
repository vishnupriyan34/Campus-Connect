<?php
// Staff Mass Notification Center (PHPMailer Integration)

$page_title = "Mass Communication - Campus Connect";
$active_nav = "email";
require_once '../includes/auth.php';
checkRole(['staff']);
require_once '../includes/header.php';
require_once '../config/mail.php';
require_once '../includes/audit.php';

$staff_id = $_SESSION['user_id'];
$error = '';
$success = '';

// Fetch active drives for dropdown filter
try {
    $drives = $pdo->query("
        SELECT d.id, d.title, c.name as company_name 
        FROM drives d 
        JOIN companies c ON d.company_id = c.id 
        WHERE d.status = 'open' 
        ORDER BY d.created_at DESC
    ")->fetchAll();
} catch (PDOException $e) {
    $drives = [];
}

// Handle email dispatch
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF
    $token = $_POST['csrf_token'] ?? '';
    if (!verifyCsrfToken($token)) {
        $_SESSION['error_message'] = "Invalid CSRF session token.";
        header("Location: email.php");
        exit;
    }
    
    $filter_type = $_POST['filter_type'] ?? 'all';
    $target_branch = $_POST['target_branch'] ?? '';
    $target_drive = intval($_POST['target_drive'] ?? 0);
    $subject = trim($_POST['subject'] ?? '');
    $body_content = trim($_POST['body'] ?? '');
    
    if (empty($subject) || empty($body_content)) {
        $error = "Subject and message body cannot be empty.";
    } else {
        try {
            // Build student search query
            if ($filter_type === 'branch') {
                $stmtQuery = $pdo->prepare("
                    SELECT u.id, u.name, u.email 
                    FROM users u 
                    JOIN students s ON u.id = s.user_id 
                    WHERE u.role = 'student' AND s.department = :branch
                ");
                $stmtQuery->execute([':branch' => $target_branch]);
                $targets_label = "Department: " . $target_branch;
            } elseif ($filter_type === 'drive' && $target_drive > 0) {
                $stmtQuery = $pdo->prepare("
                    SELECT u.id, u.name, u.email 
                    FROM users u 
                    JOIN applications a ON u.id = a.student_id 
                    WHERE u.role = 'student' AND a.drive_id = :drive_id
                ");
                $stmtQuery->execute([':drive_id' => $target_drive]);
                
                // Fetch drive name for label
                $stmtDriveName = $pdo->prepare("SELECT title FROM drives WHERE id = :id");
                $stmtDriveName->execute([':id' => $target_drive]);
                $driveName = $stmtDriveName->fetchColumn();
                $targets_label = "Drive: " . $driveName;
            } else {
                // All students
                $stmtQuery = $pdo->prepare("
                    SELECT id, name, email 
                    FROM users 
                    WHERE role = 'student'
                ");
                $stmtQuery->execute();
                $targets_label = "All Students";
            }
            
            $students_list = $stmtQuery->fetchAll();
            
            if (empty($students_list)) {
                $error = "No students match the specified filter criteria.";
            } else {
                $sent_count = 0;
                $delivery_method = 'LogFile';
                
                // Begin emailing in a loop (using mail.php sendMail function)
                foreach ($students_list as $student) {
                    $email_body = "
                        <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #e2e8f0; border-radius: 8px;'>
                            <h2 style='color: #4f46e5; border-bottom: 2px solid #eef2f6; padding-bottom: 10px;'>Campus Connect announcement</h2>
                            <p>Dear <strong>" . htmlspecialchars($student['name']) . "</strong>,</p>
                            <div style='line-height: 1.6; color: #334155; margin: 15px 0;'>
                                " . nl2br(htmlspecialchars($body_content)) . "
                            </div>
                            <hr style='border: 0; border-top: 1px solid #eef2f6; margin: 20px 0;'>
                            <p style='font-size: 0.8rem; color: #64748b; text-align: center;'>This is an automated notice sent by the Campus Placements Cell.</p>
                        </div>
                    ";
                    
                    $res = sendMail($student['email'], $subject, $email_body);
                    $delivery_method = $res['method'] ?? 'LogFile';
                    
                    // Create in-app notification too
                    $stmtNotif = $pdo->prepare("
                        INSERT INTO notifications (user_id, message) 
                        VALUES (:user_id, :message)
                    ");
                    $stmtNotif->execute([
                        ':user_id' => $student['id'],
                        ':message' => "Announcement: $subject"
                    ]);
                    
                    $sent_count++;
                }
                
                // Write Audit Log
                logAudit('Mass Email Sent', "Subject: '$subject' to $targets_label ($sent_count recipients)");
                
                $success = "Email announcement successfully sent to <strong>$sent_count</strong> students! Delivery Method: <strong>$delivery_method</strong>";
                if ($delivery_method === 'LogFile') {
                    $success .= " (logged to <code>scratch/mail.log</code>)";
                }
            }
        } catch (PDOException $e) {
            $error = "Database queries failed: " . $e->getMessage();
        }
    }
}

$csrf_token = getCsrfToken();
?>

<div class="page-title-area">
  <div>
    <h1 class="page-title">Mass Communication Center</h1>
    <p class="page-subtitle">Send unified email notifications and in-app alerts to students.</p>
  </div>
  <div>
    <a href="dashboard.php" class="btn btn-secondary btn-sm">
      <i class="fa-solid fa-arrow-left"></i> Back to Dashboard
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
  <!-- Email Composer Form -->
  <div class="card">
    <h3 style="font-size: 1.15rem; font-weight: 600; margin-bottom: 1.25rem;">Broadcast Composer</h3>
    
    <form action="email.php" method="POST">
      <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
      
      <div class="form-group">
        <label for="filter_type" class="form-label">Target Audience Filter</label>
        <select id="filter_type" name="filter_type" class="form-control" onchange="toggleFilters()">
          <option value="all">All Registered Students</option>
          <option value="branch">Filter by Academic Department</option>
          <option value="drive">Filter by Placement Drive Applicants</option>
        </select>
      </div>
      
      <!-- Hidden Branch filter -->
      <div class="form-group" id="branchFilterGroup" style="display: none;">
        <label for="target_branch" class="form-label">Select Branch</label>
        <select id="target_branch" name="target_branch" class="form-control">
          <option value="CSE">CSE</option>
          <option value="ECE">ECE</option>
          <option value="ME">ME</option>
          <option value="CE">CE</option>
          <option value="IT">IT</option>
        </select>
      </div>
      
      <!-- Hidden Drive filter -->
      <div class="form-group" id="driveFilterGroup" style="display: none;">
        <label for="target_drive" class="form-label">Select Placement Drive</label>
        <select id="target_drive" name="target_drive" class="form-control">
          <option value="" disabled selected>Select Active Drive</option>
          <?php foreach ($drives as $d): ?>
            <option value="<?php echo $d['id']; ?>">
              <?php echo sanitize($d['company_name']); ?> &mdash; <?php echo sanitize($d['title']); ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      
      <div class="form-group">
        <label for="subject" class="form-label">Email Subject *</label>
        <input type="text" id="subject" name="subject" class="form-control" placeholder="e.g. Schedule Update for Google Online Assessment" required>
      </div>
      
      <div class="form-group">
        <label for="body" class="form-label">Message Content *</label>
        <textarea id="body" name="body" class="form-control" rows="8" placeholder="Type your announcement details here. This message is formatted and sent to the student's email box." required></textarea>
      </div>
      
      <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 1rem;">
        <i class="fa-solid fa-paper-plane"></i> Dispatch Email Announcement
      </button>
    </form>
  </div>
  
  <!-- Info Card -->
  <div class="card" style="align-self: flex-start;">
    <h3 style="font-size: 1.15rem; font-weight: 600; margin-bottom: 1rem;">Mailing Information</h3>
    <ul style="padding-left: 1.25rem; font-size: 0.9rem; color: var(--text-secondary); display: flex; flex-direction: column; gap: 0.75rem; line-height: 1.5;">
      <li>
        <strong>Dual Dispatch:</strong> Dispatched messages are sent via email (through PHPMailer) AND also trigger an instant in-app alert, which students will see in their dashboard notifications feed.
      </li>
      <li>
        <strong>SMTP Sandbox:</strong> If SMTP settings in the `.env` configuration file are blank or set to defaults, the portal writes email packages to a local file at <code>scratch/mail.log</code>. You can open this log file to inspect mock deliverability.
      </li>
      <li>
        <strong>Safety Rule:</strong> Please verify the subject line and target list since mass notification actions cannot be cancelled or recalled once dispatched.
      </li>
    </ul>
  </div>
</div>

<script>
function toggleFilters() {
    const filterType = document.getElementById('filter_type').value;
    const branchGrp = document.getElementById('branchFilterGroup');
    const driveGrp = document.getElementById('driveFilterGroup');
    
    branchGrp.style.display = filterType === 'branch' ? 'block' : 'none';
    driveGrp.style.display = filterType === 'drive' ? 'block' : 'none';
}
</script>

<?php
require_once '../includes/footer.php';
?>
