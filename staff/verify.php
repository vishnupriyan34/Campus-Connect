<?php
// Staff Verifications, Approvals, and Applicant Shortlisting

$page_title = "Verification Queue - Campus Connect";
$active_nav = "verify";
require_once '../includes/auth.php';
checkRole(['staff']);
require_once '../includes/header.php';
require_once '../includes/audit.php';

$staff_id = $_SESSION['user_id'];
$req_id = isset($_GET['req_id']) ? intval($_GET['req_id']) : 0;
$drive_id = isset($_GET['drive_id']) ? intval($_GET['drive_id']) : 0;

// =========================================================================
// SECTION A: Process Academic Verification Request (Approve/Reject)
// =========================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'resolve_request') {
    // Verify CSRF
    $token = $_POST['csrf_token'] ?? '';
    if (!verifyCsrfToken($token)) {
        $_SESSION['error_message'] = "Invalid CSRF session token.";
        header("Location: verify.php");
        exit;
    }
    
    $req_id_post = intval($_POST['req_id']);
    $decision = $_POST['decision'] ?? ''; // 'approve' or 'reject'
    $reason = trim($_POST['reason'] ?? '');
    
    try {
        // Fetch request details
        $stmtReq = $pdo->prepare("SELECT * FROM verification_requests WHERE id = :id AND status = 'pending'");
        $stmtReq->execute([':id' => $req_id_post]);
        $req = $stmtReq->fetch();
        
        if (!$req) {
            $_SESSION['error_message'] = "Verification request not found or already resolved.";
        } else {
            $student_id_target = $req['student_id'];
            $field = $req['field_name'];
            $new_val = $req['new_value'];
            
            $pdo->beginTransaction();
            
            if ($decision === 'approve') {
                // 1. Update Student Table
                $stmtUpdateStudent = $pdo->prepare("
                    UPDATE students 
                    SET `$field` = :new_val 
                    WHERE user_id = :student_id
                ");
                $stmtUpdateStudent->execute([
                    ':new_val' => $new_val,
                    ':student_id' => $student_id_target
                ]);
                
                // 2. Update Request Status
                $stmtResolve = $pdo->prepare("
                    UPDATE verification_requests 
                    SET status = 'approved', resolved_by = :staff_id, resolved_at = CURRENT_TIMESTAMP 
                    WHERE id = :id
                ");
                $stmtResolve->execute([
                    ':staff_id' => $staff_id,
                    ':id' => $req_id_post
                ]);
                
                // 3. Send Notification to Student
                $stmtNotif = $pdo->prepare("
                    INSERT INTO notifications (user_id, message) 
                    VALUES (:user_id, :message)
                ");
                $stmtNotif->execute([
                    ':user_id' => $student_id_target,
                    ':message' => "Your request to update " . strtoupper($field) . " to " . htmlspecialchars($new_val) . " was APPROVED."
                ]);
                
                logAudit('Academic Edit Approved', "Approved request ID: $req_id_post (Student ID: $student_id_target, $field &rarr; $new_val)");
                $_SESSION['success_message'] = "Academic edit approved successfully.";
            } elseif ($decision === 'reject') {
                if (empty($reason)) {
                    $_SESSION['error_message'] = "Please provide a reason for rejecting the request.";
                    $pdo->rollBack();
                    header("Location: verify.php?req_id=" . $req_id_post);
                    exit;
                }
                
                // 1. Update Request Status to Rejected
                $stmtResolve = $pdo->prepare("
                    UPDATE verification_requests 
                    SET status = 'rejected', reason = :reason, resolved_by = :staff_id, resolved_at = CURRENT_TIMESTAMP 
                    WHERE id = :id
                ");
                $stmtResolve->execute([
                    ':reason' => $reason,
                    ':staff_id' => $staff_id,
                    ':id' => $req_id_post
                ]);
                
                // 2. Send Notification to Student
                $stmtNotif = $pdo->prepare("
                    INSERT INTO notifications (user_id, message) 
                    VALUES (:user_id, :message)
                ");
                $stmtNotif->execute([
                    ':user_id' => $student_id_target,
                    ':message' => "Your request to update " . strtoupper($field) . " was REJECTED. Reason: " . htmlspecialchars($reason)
                ]);
                
                logAudit('Academic Edit Rejected', "Rejected request ID: $req_id_post. Reason: $reason");
                $_SESSION['success_message'] = "Academic edit rejected.";
            }
            
            $pdo->commit();
        }
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $_SESSION['error_message'] = "Error resolving request: " . $e->getMessage();
    }
    header("Location: verify.php");
    exit;
}

// =========================================================================
// SECTION B: Process Applicant Status Update (Shortlist/Select/Reject)
// =========================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_applicant_status') {
    // Verify CSRF
    $token = $_POST['csrf_token'] ?? '';
    if (!verifyCsrfToken($token)) {
        $_SESSION['error_message'] = "Invalid CSRF session token.";
        header("Location: verify.php?drive_id=" . $drive_id);
        exit;
    }
    
    $app_id = intval($_POST['app_id']);
    $new_status = $_POST['status'] ?? ''; // 'Shortlisted', 'Selected', 'Rejected'
    
    if (!in_array($new_status, ['Shortlisted', 'Selected', 'Rejected'])) {
        $_SESSION['error_message'] = "Invalid applicant status value.";
    } else {
        try {
            // Fetch applicant details
            $stmtAppDetails = $pdo->prepare("
                SELECT a.student_id, a.drive_id, u.name as student_name, d.title as drive_title, c.name as company_name
                FROM applications a
                JOIN users u ON a.student_id = u.id
                JOIN drives d ON a.drive_id = d.id
                JOIN companies c ON d.company_id = c.id
                WHERE a.id = :app_id
            ");
            $stmtAppDetails->execute([':app_id' => $app_id]);
            $app_info = $stmtAppDetails->fetch();
            
            if ($app_info) {
                // Update applications status
                $stmtUpdateApp = $pdo->prepare("UPDATE applications SET status = :status WHERE id = :app_id");
                $stmtUpdateApp->execute([':status' => $new_status, ':app_id' => $app_id]);
                
                // Add student notification
                $notifMsg = "Your application status for " . $app_info['company_name'] . " - " . $app_info['drive_title'] . " has been updated to: " . strtoupper($new_status) . ".";
                if ($new_status === 'Selected') {
                    $notifMsg = "Congratulations! You have been SELECTED for " . $app_info['company_name'] . " - " . $app_info['drive_title'] . ". Check your inbox for updates.";
                }
                
                $stmtNotif = $pdo->prepare("INSERT INTO notifications (user_id, message) VALUES (:user_id, :message)");
                $stmtNotif->execute([
                    ':user_id' => $app_info['student_id'],
                    ':message' => $notifMsg
                ]);
                
                logAudit("Applicant " . $new_status, "Application ID: $app_id (Student: $app_info[student_name] in drive $app_info[drive_title])");
                $_SESSION['success_message'] = "Applicant marked as " . strtoupper($new_status);
            }
        } catch (PDOException $e) {
            $_SESSION['error_message'] = "Database error: " . $e->getMessage();
        }
    }
    header("Location: verify.php?drive_id=" . $drive_id);
    exit;
}

$csrf_token = getCsrfToken();
?>

<!-- VIEW 1: Review Individual Academic Request -->
<?php if ($req_id > 0): 
  $stmtShowReq = $pdo->prepare("
      SELECT vr.*, u.name as student_name, u.email as student_email, s.department, s.cgpa as current_cgpa, s.backlog_count as current_backlogs
      FROM verification_requests vr
      JOIN users u ON vr.student_id = u.id
      JOIN students s ON u.id = s.user_id
      WHERE vr.id = :id AND vr.status = 'pending'
  ");
  $stmtShowReq->execute([':id' => $req_id]);
  $request_item = $stmtShowReq->fetch();
  
  if (!$request_item):
      echo '<div class="alert alert-error">Request not found or resolved.</div>';
  else:
?>
  <div class="page-title-area">
    <div>
      <h1 class="page-title">Review Academic Verification</h1>
      <p class="page-subtitle">Verify academic change requests for <?php echo sanitize($request_item['student_name']); ?>.</p>
    </div>
    <div>
      <a href="verify.php" class="btn btn-secondary btn-sm">
        <i class="fa-solid fa-arrow-left"></i> Back to Queue
      </a>
    </div>
  </div>

  <div class="grid-2">
    <!-- Left: Request Comparison -->
    <div class="card">
      <h3 style="font-size: 1.15rem; font-weight: 600; margin-bottom: 1rem;">Comparison Details</h3>
      <table style="width: 100%;">
        <tr>
          <td style="font-weight: 600;">Student Name:</td>
          <td><?php echo sanitize($request_item['student_name']); ?> (<?php echo sanitize($request_item['department']); ?>)</td>
        </tr>
        <tr>
          <td style="font-weight: 600;">Student Email:</td>
          <td><?php echo sanitize($request_item['student_email']); ?></td>
        </tr>
        <tr>
          <td style="font-weight: 600;">Requested Field:</td>
          <td><span style="font-weight: 700; text-transform: uppercase;"><?php echo sanitize($request_item['field_name']); ?></span></td>
        </tr>
        <tr>
          <td style="font-weight: 600;">Old Value:</td>
          <td><span style="color: var(--text-muted);"><?php echo sanitize($request_item['old_value']); ?></span></td>
        </tr>
        <tr>
          <td style="font-weight: 600;">Proposed New Value:</td>
          <td><span style="font-weight: 700; color: var(--color-success); font-size: 1.1rem;"><?php echo sanitize($request_item['new_value']); ?></span></td>
        </tr>
        <tr>
          <td style="font-weight: 600;">Submission Date:</td>
          <td><?php echo date('M d, Y h:i A', strtotime($request_item['created_at'])); ?></td>
        </tr>
      </table>
    </div>
    
    <!-- Right: Decision form -->
    <div class="card">
      <h3 style="font-size: 1.15rem; font-weight: 600; margin-bottom: 1rem;">Action and Resolution</h3>
      
      <form action="verify.php" method="POST" id="resolutionForm">
        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
        <input type="hidden" name="action" value="resolve_request">
        <input type="hidden" name="req_id" value="<?php echo $req_id; ?>">
        <input type="hidden" name="decision" id="decisionVal" value="approve">
        
        <div class="form-group" id="rejectionReasonGroup" style="display: none;">
          <label for="reason" class="form-label">Reason for Rejection *</label>
          <input type="text" id="reason" name="reason" class="form-control" placeholder="e.g. Please submit grade sheets to verify CGPA.">
        </div>
        
        <div style="display: flex; gap: 0.75rem; margin-top: 1rem;">
          <button type="button" class="btn btn-danger" style="flex: 1;" onclick="setDecision('reject')">
            <i class="fa-solid fa-circle-xmark"></i> Reject Request
          </button>
          
          <button type="submit" class="btn btn-success" id="approveSubmitBtn" style="flex: 1;" onclick="setDecision('approve')">
            <i class="fa-solid fa-circle-check"></i> Approve Request
          </button>
        </div>
      </form>
    </div>
  </div>
  
  <script>
  function setDecision(decision) {
      document.getElementById('decisionVal').value = decision;
      if (decision === 'reject') {
          const reasonGroup = document.getElementById('rejectionReasonGroup');
          const reasonInput = document.getElementById('reason');
          if (reasonGroup.style.display === 'none') {
              reasonGroup.style.display = 'block';
              reasonInput.setAttribute('required', 'true');
              document.getElementById('approveSubmitBtn').style.display = 'none';
          } else {
              // Submit form rejection
              document.getElementById('resolutionForm').submit();
          }
      } else {
          document.getElementById('resolutionForm').submit();
      }
  }
  </script>
<?php endif; ?>

<!-- VIEW 2: Applicants Shortlisting Grid for a Specific Drive -->
<?php elseif ($drive_id > 0): 
  // Fetch Drive Details
  $stmtD = $pdo->prepare("
      SELECT d.*, c.name as company_name 
      FROM drives d 
      JOIN companies c ON d.company_id = c.id 
      WHERE d.id = :id
  ");
  $stmtD->execute([':id' => $drive_id]);
  $drive = $stmtD->fetch();
  
  if (!$drive):
      echo '<div class="alert alert-error">Drive not found.</div>';
  else:
      // Sorting handling (differentiator: AI match score ranking)
      $sort = $_GET['sort'] ?? 'applied_at';
      $order_by = 'a.applied_at ASC';
      if ($sort === 'match_score') {
          $order_by = 'a.match_score DESC, s.cgpa DESC';
      } elseif ($sort === 'cgpa') {
          $order_by = 's.cgpa DESC';
      } elseif ($sort === 'name') {
          $order_by = 'u.name ASC';
      }
      
      // Fetch applicants
      $stmtAppList = $pdo->prepare("
          SELECT a.*, u.name as student_name, u.email as student_email, s.department, s.cgpa, s.backlog_count, s.resume_path
          FROM applications a
          JOIN users u ON a.student_id = u.id
          JOIN students s ON u.id = s.user_id
          WHERE a.drive_id = :drive_id
          ORDER BY $order_by
      ");
      $stmtAppList->execute([':drive_id' => $drive_id]);
      $applicants = $stmtAppList->fetchAll();
?>
  <div class="page-title-area">
    <div>
      <h1 class="page-title">Applicants Shortlisting</h1>
      <p class="page-subtitle"><?php echo sanitize($drive['company_name']); ?> &mdash; <?php echo sanitize($drive['title']); ?></p>
    </div>
    <div>
      <a href="drives.php" class="btn btn-secondary btn-sm">
        <i class="fa-solid fa-arrow-left"></i> Back to Drives
      </a>
    </div>
  </div>

  <div class="card" style="margin-bottom: 2rem; display: flex; justify-content: space-between; align-items: center; padding: 1rem 1.5rem;">
    <div style="font-size: 0.95rem; color: var(--text-secondary);">
      Total Candidates Applied: <strong><?php echo count($applicants); ?></strong>
    </div>
    <!-- Sorting controls (Differentiator AI Ranking) -->
    <div style="display: flex; align-items: center; gap: 0.75rem;">
      <span style="font-size: 0.85rem; color: var(--text-muted); font-weight: 500;">Sort Candidates By:</span>
      <a href="verify.php?drive_id=<?php echo $drive_id; ?>&sort=match_score" class="btn btn-secondary btn-sm <?php echo $sort === 'match_score' ? 'btn-primary' : ''; ?>" style="padding: 0.3rem 0.7rem; font-size: 0.8rem;">
        <i class="fa-solid fa-wand-magic-sparkles"></i> AI Match Score
      </a>
      <a href="verify.php?drive_id=<?php echo $drive_id; ?>&sort=cgpa" class="btn btn-secondary btn-sm <?php echo $sort === 'cgpa' ? 'btn-primary' : ''; ?>" style="padding: 0.3rem 0.7rem; font-size: 0.8rem;">
        <i class="fa-solid fa-award"></i> Cutoff CGPA
      </a>
      <a href="verify.php?drive_id=<?php echo $drive_id; ?>&sort=applied_at" class="btn btn-secondary btn-sm <?php echo $sort === 'applied_at' ? 'btn-primary' : ''; ?>" style="padding: 0.3rem 0.7rem; font-size: 0.8rem;">
        <i class="fa-solid fa-calendar"></i> Date Applied
      </a>
    </div>
  </div>

  <?php if (empty($applicants)): ?>
    <div class="card" style="text-align: center; padding: 3rem;">
      <i class="fa-solid fa-users" style="font-size: 3rem; color: var(--text-muted); margin-bottom: 1rem;"></i>
      <h3>No applications received yet</h3>
      <p style="color: var(--text-muted); margin-top: 0.5rem;">Eligible students will apply once they view this drive.</p>
    </div>
  <?php else: ?>
    <div class="table-container">
      <table>
        <thead>
          <tr>
            <th>Student Info</th>
            <th>Academic Score</th>
            <th style="color: var(--color-accent); text-align: center;"><i class="fa-solid fa-wand-magic-sparkles"></i> AI Match Score</th>
            <th>Resume</th>
            <th>Application Status</th>
            <th>Resolution Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($applicants as $app): ?>
            <tr>
              <td>
                <strong><?php echo sanitize($app['student_name']); ?></strong>
                <div style="font-size: 0.75rem; color: var(--text-muted);"><?php echo sanitize($app['department']); ?> | <?php echo sanitize($app['student_email']); ?></div>
              </td>
              <td>
                <div style="font-size: 0.85rem;">CGPA: <strong><?php echo number_format($app['cgpa'], 2); ?></strong></div>
                <div style="font-size: 0.75rem; color: var(--text-muted);">Backlogs: <?php echo intval($app['backlog_count']); ?></div>
              </td>
              <td style="text-align: center;">
                <?php 
                $score = intval($app['match_score']);
                $scoreColor = 'var(--text-muted)';
                if ($score >= 80) $scoreColor = 'var(--color-success)';
                elseif ($score >= 50) $scoreColor = 'var(--color-warning)';
                elseif ($score > 0) $scoreColor = 'var(--color-danger)';
                ?>
                <div style="font-weight: 700; font-size: 1.15rem; color: <?php echo $scoreColor; ?>;">
                  <?php echo $score > 0 ? $score . '%' : 'Pending / --'; ?>
                </div>
                
                <?php if (!empty($app['match_missing_skills'])): ?>
                  <div style="font-size: 0.7rem; color: var(--text-muted); max-width: 150px; margin: 0.25rem auto 0 auto; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="Missing: <?php echo sanitize($app['match_missing_skills']); ?>">
                    Missing: <?php echo sanitize($app['match_missing_skills']); ?>
                  </div>
                <?php endif; ?>
              </td>
              <td>
                <?php if (!empty($app['resume_path'])): ?>
                  <a href="../<?php echo sanitize($app['resume_path']); ?>" target="_blank" style="color: var(--color-accent); text-decoration: none; font-size: 0.85rem; font-weight: 500;">
                    <i class="fa-solid fa-file-pdf"></i> View PDF
                  </a>
                <?php else: ?>
                  <span style="font-size: 0.85rem; color: var(--text-muted);">No Resume</span>
                <?php endif; ?>
              </td>
              <td>
                <span class="badge badge-<?php echo strtolower($app['status']); ?>" style="font-size: 0.75rem;">
                  <?php echo sanitize($app['status']); ?>
                </span>
              </td>
              <td>
                <form action="verify.php?drive_id=<?php echo $drive_id; ?>" method="POST" style="display: flex; gap: 0.3rem;">
                  <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                  <input type="hidden" name="action" value="update_applicant_status">
                  <input type="hidden" name="app_id" value="<?php echo $app['id']; ?>">
                  
                  <?php if ($app['status'] === 'Applied'): ?>
                    <button type="submit" name="status" value="Shortlisted" class="btn btn-secondary btn-sm" style="padding: 0.3rem 0.5rem; font-size: 0.75rem; border-color: rgba(245,158,11,0.3); color: var(--color-warning);">
                      Shortlist
                    </button>
                    <button type="submit" name="status" value="Rejected" class="btn btn-secondary btn-sm" style="padding: 0.3rem 0.5rem; font-size: 0.75rem; border-color: rgba(239,68,68,0.3); color: var(--color-danger);">
                      Reject
                    </button>
                  <?php elseif ($app['status'] === 'Shortlisted'): ?>
                    <button type="submit" name="status" value="Selected" class="btn btn-success btn-sm" style="padding: 0.3rem 0.5rem; font-size: 0.75rem;">
                      Select
                    </button>
                    <button type="submit" name="status" value="Rejected" class="btn btn-danger btn-sm" style="padding: 0.3rem 0.5rem; font-size: 0.75rem;">
                      Reject
                    </button>
                  <?php else: ?>
                    <span style="font-size: 0.75rem; color: var(--text-muted);">Resolved</span>
                  <?php endif; ?>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
<?php endif; ?>

<!-- VIEW 3: Approvals Timelines & Verification Requests List (Default) -->
<?php else: ?>
  <div class="page-title-area">
    <div>
      <h1 class="page-title">Verifications Dashboard</h1>
      <p class="page-subtitle">Approve academic edits or manage student drive applications.</p>
    </div>
  </div>

  <?php
  // Fetch all pending requests
  try {
      $stmtAllReq = $pdo->query("
          SELECT vr.*, u.name as student_name, s.department 
          FROM verification_requests vr
          JOIN users u ON vr.student_id = u.id
          JOIN students s ON u.id = s.user_id
          WHERE vr.status = 'pending'
          ORDER BY vr.created_at ASC
      ");
      $all_requests = $stmtAllReq->fetchAll();
  } catch (PDOException $e) {
      $all_requests = [];
  }
  ?>

  <div class="grid-2">
    <!-- Left Panel: Academic Approvals Queue -->
    <div class="card" style="min-height: 400px;">
      <h2 style="font-size: 1.25rem; font-weight: 600; margin-bottom: 1rem;"><i class="fa-solid fa-clock-rotate-left" style="color: var(--color-warning);"></i> Academic Approvals Queue</h2>
      
      <?php if (empty($all_requests)): ?>
        <div style="text-align: center; padding: 3rem; color: var(--text-muted); font-size: 0.95rem;">
          <i class="fa-solid fa-circle-check" style="font-size: 3.5rem; color: var(--color-success); margin-bottom: 1rem; display: block;"></i>
          No student updates require verification. Profiles are up-to-date!
        </div>
      <?php else: ?>
        <div class="table-container">
          <table>
            <thead>
              <tr>
                <th>Student</th>
                <th>Field</th>
                <th>Change Value</th>
                <th>Review</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($all_requests as $rq): ?>
                <tr>
                  <td>
                    <strong><?php echo sanitize($rq['student_name']); ?></strong>
                    <div style="font-size: 0.75rem; color: var(--text-muted);"><?php echo sanitize($rq['department']); ?></div>
                  </td>
                  <td><span style="font-weight: 700; font-size: 0.8rem; text-transform: uppercase;"><?php echo sanitize($rq['field_name']); ?></span></td>
                  <td><?php echo sanitize($rq['old_value']); ?> &rarr; <strong><?php echo sanitize($rq['new_value']); ?></strong></td>
                  <td>
                    <a href="verify.php?req_id=<?php echo $rq['id']; ?>" class="btn btn-primary btn-sm" style="padding: 0.25rem 0.5rem; font-size: 0.75rem;">
                      Review &rarr;
                    </a>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>
    
    <!-- Right Panel: Active Placement Drives Applicant Monitoring -->
    <div class="card" style="min-height: 400px;">
      <h2 style="font-size: 1.25rem; font-weight: 600; margin-bottom: 1rem;"><i class="fa-solid fa-briefcase" style="color: var(--color-accent);"></i> Drive Shortlisting Pipeline</h2>
      
      <?php
      // Fetch open drives
      try {
          $openDrives = $pdo->query("
              SELECT d.id, d.title, c.name as company_name, 
                     (SELECT COUNT(*) FROM applications WHERE drive_id = d.id) as total_applicants,
                     (SELECT COUNT(*) FROM applications WHERE drive_id = d.id AND status = 'Shortlisted') as shortlisted_count
              FROM drives d
              JOIN companies c ON d.company_id = c.id
              WHERE d.status = 'open'
              ORDER BY d.created_at DESC
          ")->fetchAll();
      } catch (PDOException $e) {
          $openDrives = [];
      }
      ?>
      
      <?php if (empty($openDrives)): ?>
        <p style="color: var(--text-muted); text-align: center; padding: 3rem;">No active drives to shortlist candidates.</p>
      <?php else: ?>
        <div style="display: flex; flex-direction: column; gap: 0.75rem; overflow-y: auto; max-height: 350px;">
          <?php foreach ($openDrives as $drv): ?>
            <div style="background-color: var(--bg-primary); border: 1px solid var(--border-glass); border-radius: var(--radius-md); padding: 1rem; display: flex; justify-content: space-between; align-items: center;">
              <div>
                <strong style="font-size: 0.95rem; color: var(--text-primary);"><?php echo sanitize($drv['company_name']); ?></strong>
                <div style="font-size: 0.8rem; color: var(--text-secondary);"><?php echo sanitize($drv['title']); ?></div>
                <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.25rem;">
                  Total Applicants: <strong><?php echo intval($drv['total_applicants']); ?></strong> &nbsp;|&nbsp; 
                  Shortlisted: <strong><?php echo intval($drv['shortlisted_count']); ?></strong>
                </div>
              </div>
              <a href="verify.php?drive_id=<?php echo $drv['id']; ?>" class="btn btn-secondary btn-sm" style="padding: 0.35rem 0.75rem; font-size: 0.8rem;">
                Manage &rarr;
              </a>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </div>
<?php endif; ?>

<?php
require_once '../includes/footer.php';
?>
