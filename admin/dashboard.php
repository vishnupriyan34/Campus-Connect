<?php
// Admin Dashboard Panel

$page_title = "Admin Panel - Campus Connect";
$active_nav = "dashboard";
require_once '../includes/auth.php';
checkRole(['admin']);
require_once '../includes/header.php';
require_once '../config/mail.php';
require_once '../includes/audit.php';

$admin_id = $_SESSION['user_id'];
$error = '';
$success = '';

// 1. Process Global Announcement (BCC email and in-app alerts to ALL users)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'global_announcement') {
    // Verify CSRF
    $token = $_POST['csrf_token'] ?? '';
    if (!verifyCsrfToken($token)) {
        $_SESSION['error_message'] = "Invalid CSRF session token.";
        header("Location: dashboard.php");
        exit;
    }
    
    $subject = trim($_POST['subject'] ?? '');
    $message_text = trim($_POST['message'] ?? '');
    
    if (empty($subject) || empty($message_text)) {
        $error = "Subject and announcement content cannot be empty.";
    } else {
        try {
            // Fetch all users
            $allUsers = $pdo->query("SELECT id, name, email FROM users")->fetchAll();
            $sent_count = 0;
            $delivery_method = 'LogFile';
            
            foreach ($allUsers as $user) {
                
                $email_body = "
                    <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #cbd5e1; border-radius: 8px;'>
                        <h2 style='color: #a855f7; border-bottom: 2px solid #eef2f6; padding-bottom: 10px;'>Campus Connect announcement</h2>
                        <p>Hi <strong>" . htmlspecialchars($user['name']) . "</strong>,</p>
                        <div style='line-height: 1.6; color: #1e293b; margin: 15px 0;'>
                            " . nl2br(htmlspecialchars($message_text)) . "
                        </div>
                        <hr style='border: 0; border-top: 1px solid #eef2f6; margin: 20px 0;'>
                        <p style='font-size: 0.8rem; color: #64748b; text-align: center;'>This is an administrative portal broadcast notice.</p>
                    </div>
                ";
                
                $res = sendMail($user['email'], $subject, $email_body);
                $delivery_method = $res['method'] ?? 'LogFile';
                
                // Write in-app notification
                $stmtNotif = $pdo->prepare("INSERT INTO notifications (user_id, message) VALUES (:user_id, :message)");
                $stmtNotif->execute([
                    ':user_id' => $user['id'],
                    ':message' => "Global Broadcast: $subject"
                ]);
                $sent_count++;
            }
            
            logAudit('Global Broadcast Sent', "Subject: '$subject' to $sent_count users");
            
            $success = "Announcement broadcasted successfully to <strong>$sent_count</strong> registered users! Delivery Method: <strong>$delivery_method</strong>";
            if ($delivery_method === 'LogFile') {
                $success .= " (logged to <code>scratch/mail.log</code>)";
            }
        } catch (PDOException $e) {
            $error = "Failed to broadcast notifications: " . $e->getMessage();
        }
    }
}

// 2. Fetch Aggregated Statistics
try {
    $totalStudents = $pdo->query("SELECT COUNT(*) FROM students")->fetchColumn() ?: 0;
    $placedStudents = $pdo->query("SELECT COUNT(DISTINCT student_id) FROM applications WHERE status = 'Selected'")->fetchColumn() ?: 0;
    $placementRate = $totalStudents > 0 ? ($placedStudents / $totalStudents) * 100 : 0;
    
    $totalDrives = $pdo->query("SELECT COUNT(*) FROM drives")->fetchColumn() ?: 0;
    $totalCompanies = $pdo->query("SELECT COUNT(*) FROM companies")->fetchColumn() ?: 0;
    $totalOffers = $pdo->query("SELECT COUNT(*) FROM offer_letters")->fetchColumn() ?: 0;
    
    // Fetch department-wise placement breakdown
    $deptStats = $pdo->query("
        SELECT 
            s.department,
            COUNT(DISTINCT s.user_id) as total_count,
            COUNT(DISTINCT CASE WHEN a.status = 'Selected' THEN a.student_id END) as placed_count
        FROM students s
        LEFT JOIN applications a ON s.user_id = a.student_id
        GROUP BY s.department
        ORDER BY s.department ASC
    ")->fetchAll();
    
} catch (PDOException $e) {
    echo '<div class="alert alert-error">Database aggregates error: ' . sanitize($e->getMessage()) . '</div>';
    $totalStudents = $placedStudents = $placementRate = $totalDrives = $totalCompanies = $totalOffers = 0;
    $deptStats = [];
}

// 3. Fetch latest 5 audit logs
try {
    $auditLogs = $pdo->query("
        SELECT al.*, u.name as actor_name, u.role as actor_role
        FROM audit_logs al
        LEFT JOIN users u ON al.actor_id = u.id
        ORDER BY al.timestamp DESC
        LIMIT 5
    ")->fetchAll();
} catch (PDOException $e) {
    $auditLogs = [];
}

$csrf_token = getCsrfToken();
?>

<div class="page-title-area">
  <div>
    <h1 class="page-title">Placement System Administration</h1>
    <p class="page-subtitle">Cross-drive monitoring, department audit trails, and system communication management.</p>
  </div>
  <div style="display: flex; gap: 0.5rem;">
    <a href="export.php" class="btn btn-primary btn-sm">
      <i class="fa-solid fa-file-csv"></i> Export Student Placement Data
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

<!-- Aggregated Metrics -->
<div class="stats-grid">
  <div class="stat-tile">
    <div class="stat-icon success"><i class="fa-solid fa-graduation-cap"></i></div>
    <div class="user-info">
      <div class="stat-value"><?php echo number_format($placementRate, 1); ?>%</div>
      <div class="stat-label">System Placement Rate</div>
    </div>
  </div>

  <div class="stat-tile">
    <div class="stat-icon"><i class="fa-solid fa-users"></i></div>
    <div class="user-info">
      <div class="stat-value"><?php echo $placedStudents . ' / ' . $totalStudents; ?></div>
      <div class="stat-label">Placed Students Count</div>
    </div>
  </div>

  <div class="stat-tile">
    <div class="stat-icon accent"><i class="fa-solid fa-building"></i></div>
    <div class="user-info">
      <div class="stat-value"><?php echo $totalCompanies; ?></div>
      <div class="stat-label">Registered Companies</div>
    </div>
  </div>

  <div class="stat-tile">
    <div class="stat-icon warning"><i class="fa-solid fa-file-signature"></i></div>
    <div class="user-info">
      <div class="stat-value"><?php echo $totalOffers; ?></div>
      <div class="stat-label">Offer Letters Distributed</div>
    </div>
  </div>
</div>

<div class="grid-2">
  <!-- Left Side: Placements Monitoring Table by Department -->
  <div style="display: flex; flex-direction: column; gap: 1.5rem;">
    <div class="card">
      <h2 style="font-size: 1.25rem; font-weight: 600; margin-bottom: 1rem;"><i class="fa-solid fa-chart-simple" style="color: var(--color-primary);"></i> Department Wise Placements</h2>
      
      <div class="table-container">
        <table>
          <thead>
            <tr>
              <th>Department</th>
              <th>Total Students</th>
              <th>Placed count</th>
              <th>Placement Rate</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($deptStats as $ds): 
              $rate = $ds['total_count'] > 0 ? ($ds['placed_count'] / $ds['total_count']) * 100 : 0;
            ?>
              <tr>
                <td><strong><?php echo sanitize($ds['department']); ?></strong></td>
                <td><?php echo intval($ds['total_count']); ?></td>
                <td><span style="color: var(--color-success); font-weight: 600;"><?php echo intval($ds['placed_count']); ?></span></td>
                <td><strong><?php echo number_format($rate, 1); ?>%</strong></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
    
    <!-- Mass Broadcast form -->
    <div class="card">
      <h2 style="font-size: 1.25rem; font-weight: 600; margin-bottom: 1rem;"><i class="fa-solid fa-bullhorn" style="color: var(--color-secondary);"></i> Send Global System Broadcast</h2>
      <form action="dashboard.php" method="POST">
        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
        <input type="hidden" name="action" value="global_announcement">
        
        <div class="form-group">
          <label for="subject" class="form-label">Broadcast Subject *</label>
          <input type="text" id="subject" name="subject" class="form-control" placeholder="e.g. Server Maintenance Notice or General Placement Update" required>
        </div>
        
        <div class="form-group">
          <label for="message" class="form-label">Message Content *</label>
          <textarea id="message" name="message" class="form-control" rows="4" placeholder="This message will be emailed to all users and saved to their notification feeds." required></textarea>
        </div>
        
        <button type="submit" class="btn btn-primary btn-sm" style="width: 100%;">
          <i class="fa-solid fa-paper-plane"></i> Broadcast Message
        </button>
      </form>
    </div>
  </div>
  
  <!-- Right Side: Audit Trail logs timeline -->
  <div style="display: flex; flex-direction: column; gap: 1.5rem;">
    <div class="card">
      <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
        <h2 style="font-size: 1.25rem; font-weight: 600;"><i class="fa-solid fa-list-check" style="color: var(--color-accent);"></i> Recent Audit Log Preview</h2>
        <a href="audit.log.php" style="font-size: 0.85rem; color: var(--color-accent); text-decoration: none; font-weight: 600;">Full Logs &rarr;</a>
      </div>
      
      <?php if (empty($auditLogs)): ?>
        <p style="color: var(--text-muted); text-align: center; padding: 2rem;">No system actions recorded.</p>
      <?php else: ?>
        <div class="audit-list">
          <?php foreach ($auditLogs as $log): 
            $actorRole = $log['actor_role'] ?? 'system';
          ?>
            <div class="audit-item <?php echo sanitize($actorRole); ?>">
              <div class="audit-content">
                <span class="audit-action"><?php echo sanitize($log['action']); ?></span>
                <span class="audit-meta">
                  By: <strong><?php echo sanitize($log['actor_name'] ?: 'System'); ?></strong> (<?php echo sanitize($actorRole); ?>) &bull; 
                  Target: <em><?php echo sanitize($log['target']); ?></em>
                </span>
              </div>
              <div class="audit-timestamp">
                <?php echo date('M d, h:i A', strtotime($log['timestamp'])); ?>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php
require_once '../includes/footer.php';
?>
