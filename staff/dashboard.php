<?php
// Staff Dashboard Hub

$page_title = "Staff Dashboard - Campus Connect";
$active_nav = "dashboard";
require_once '../includes/auth.php';
checkRole(['staff']);
require_once '../includes/header.php';

// Fetch Aggregate Statistics
try {
    // Total Students
    $totalStudents = $pdo->query("SELECT COUNT(*) FROM students")->fetchColumn() ?: 0;
    
    // Placed Students (students who have at least one application with status = 'Selected')
    $placedStudents = $pdo->query("
        SELECT COUNT(DISTINCT student_id) 
        FROM applications 
        WHERE status = 'Selected'
    ")->fetchColumn() ?: 0;
    
    // Placement Rate
    $placementRate = $totalStudents > 0 ? ($placedStudents / $totalStudents) * 100 : 0;
    
    // Active Placement Drives
    $activeDrivesCount = $pdo->query("SELECT COUNT(*) FROM drives WHERE status = 'open'")->fetchColumn() ?: 0;
    
    // Pending Verification Requests
    $pendingVerificationsCount = $pdo->query("SELECT COUNT(*) FROM verification_requests WHERE status = 'pending'")->fetchColumn() ?: 0;
} catch (PDOException $e) {
    echo '<div class="alert alert-error">Database aggregates error: ' . sanitize($e->getMessage()) . '</div>';
    $totalStudents = $placedStudents = $placementRate = $activeDrivesCount = $pendingVerificationsCount = 0;
}

// Fetch Pending verification requests list (latest 5)
try {
    $stmtVerif = $pdo->prepare("
        SELECT vr.*, u.name as student_name, s.department
        FROM verification_requests vr
        JOIN users u ON vr.student_id = u.id
        JOIN students s ON u.id = s.user_id
        WHERE vr.status = 'pending'
        ORDER BY vr.created_at ASC
        LIMIT 5
    ");
    $stmtVerif->execute();
    $pendingRequests = $stmtVerif->fetchAll();
} catch (PDOException $e) {
    $pendingRequests = [];
}

// Fetch Active Drives list (latest 5)
try {
    $stmtDrives = $pdo->prepare("
        SELECT d.*, c.name as company_name, 
               (SELECT COUNT(*) FROM applications WHERE drive_id = d.id) as applicant_count
        FROM drives d
        JOIN companies c ON d.company_id = c.id
        WHERE d.status = 'open'
        ORDER BY d.created_at DESC
        LIMIT 5
    ");
    $stmtDrives->execute();
    $activeDrives = $stmtDrives->fetchAll();
} catch (PDOException $e) {
    $activeDrives = [];
}
?>

<div class="page-title-area">
  <div>
    <h1 class="page-title">Placement Staff Dashboard</h1>
    <p class="page-subtitle">Manage companies, eligible drives, verify student metrics, and view analytics.</p>
  </div>
  <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
    <a href="drives.php?action=new" class="btn btn-primary btn-sm">
      <i class="fa-solid fa-plus"></i> New Drive
    </a>
    <a href="upload_offer.php" class="btn btn-secondary btn-sm">
      <i class="fa-solid fa-file-arrow-up"></i> Upload Offer
    </a>
    <a href="email.php" class="btn btn-secondary btn-sm">
      <i class="fa-solid fa-paper-plane"></i> Mass Email
    </a>
  </div>
</div>

<!-- Aggregated Stats Tiles -->
<div class="stats-grid">
  <div class="stat-tile">
    <div class="stat-icon success"><i class="fa-solid fa-percent"></i></div>
    <div class="user-info">
      <div class="stat-value"><?php echo number_format($placementRate, 1); ?>%</div>
      <div class="stat-label">Placement Rate</div>
    </div>
  </div>

  <div class="stat-tile">
    <div class="stat-icon"><i class="fa-solid fa-user-graduate"></i></div>
    <div class="user-info">
      <div class="stat-value"><?php echo $placedStudents . ' / ' . $totalStudents; ?></div>
      <div class="stat-label">Placed Students</div>
    </div>
  </div>

  <div class="stat-tile">
    <div class="stat-icon accent"><i class="fa-solid fa-briefcase"></i></div>
    <div class="user-info">
      <div class="stat-value"><?php echo $activeDrivesCount; ?></div>
      <div class="stat-label">Active Drives</div>
    </div>
  </div>

  <div class="stat-tile">
    <div class="stat-icon warning"><i class="fa-solid fa-user-check"></i></div>
    <div class="user-info">
      <div class="stat-value"><?php echo $pendingVerificationsCount; ?></div>
      <div class="stat-label">Pending Verifications</div>
    </div>
  </div>
</div>

<div class="grid-2">
  <!-- Left Side: Pending Student Academic Edits Verification Queue -->
  <div style="display: flex; flex-direction: column; gap: 1.5rem;">
    <div class="card">
      <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
        <h2 style="font-size: 1.25rem; font-weight: 600;"><i class="fa-solid fa-user-check" style="color: var(--color-warning);"></i> Academic Approvals Queue</h2>
        <a href="verify.php" style="font-size: 0.85rem; color: var(--color-primary); text-decoration: none; font-weight: 600;">View All &rarr;</a>
      </div>
      
      <?php if (empty($pendingRequests)): ?>
        <div style="text-align: center; padding: 2rem; color: var(--text-muted); font-size: 0.9rem;">
          <i class="fa-solid fa-circle-check" style="font-size: 2rem; color: var(--color-success); margin-bottom: 0.5rem; display: block;"></i>
          No pending academic verifications.
        </div>
      <?php else: ?>
        <div class="table-container">
          <table>
            <thead>
              <tr>
                <th>Student</th>
                <th>Field</th>
                <th>Request</th>
                <th>Action</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($pendingRequests as $req): ?>
                <tr>
                  <td>
                    <strong><?php echo sanitize($req['student_name']); ?></strong>
                    <div style="font-size: 0.75rem; color: var(--text-muted);"><?php echo sanitize($req['department']); ?></div>
                  </td>
                  <td><span style="font-size: 0.8rem; font-weight: bold; text-transform: uppercase;"><?php echo sanitize($req['field_name']); ?></span></td>
                  <td><?php echo sanitize($req['old_value']); ?> &rarr; <strong><?php echo sanitize($req['new_value']); ?></strong></td>
                  <td>
                    <a href="verify.php?req_id=<?php echo $req['id']; ?>" class="btn btn-primary btn-sm" style="padding: 0.25rem 0.5rem; font-size: 0.75rem;">
                      Review
                    </a>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>
  </div>
  
  <!-- Right Side: Active Placement Drives -->
  <div style="display: flex; flex-direction: column; gap: 1.5rem;">
    <div class="card">
      <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
        <h2 style="font-size: 1.25rem; font-weight: 600;"><i class="fa-solid fa-briefcase" style="color: var(--color-accent);"></i> Active Recruitment Drives</h2>
        <a href="drives.php" style="font-size: 0.85rem; color: var(--color-accent); text-decoration: none; font-weight: 600;">Manage Drives &rarr;</a>
      </div>
      
      <?php if (empty($activeDrives)): ?>
        <div style="text-align: center; padding: 2rem; color: var(--text-muted); font-size: 0.9rem;">
          No active drives. Create one to start accepting applications.
        </div>
      <?php else: ?>
        <div class="table-container">
          <table>
            <thead>
              <tr>
                <th>Company & Title</th>
                <th>Branches</th>
                <th>Applicants</th>
                <th>Action</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($activeDrives as $dr): ?>
                <tr>
                  <td>
                    <strong><?php echo sanitize($dr['company_name']); ?></strong>
                    <div style="font-size: 0.8rem; color: var(--text-secondary);"><?php echo sanitize($dr['title']); ?></div>
                  </td>
                  <td><span style="font-size: 0.8rem; font-weight: 500; color: var(--text-muted);"><?php echo sanitize($dr['eligibility_branch']); ?></span></td>
                  <td>
                    <span class="badge badge-applied" style="font-size: 0.75rem;">
                      <?php echo intval($dr['applicant_count']); ?> Applied
                    </span>
                  </td>
                  <td>
                    <a href="verify.php?drive_id=<?php echo $dr['id']; ?>" class="btn btn-secondary btn-sm" style="padding: 0.25rem 0.5rem; font-size: 0.75rem;">
                      Applicants
                    </a>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php
require_once '../includes/footer.php';
?>
