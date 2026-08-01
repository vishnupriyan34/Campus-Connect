<?php
// Staff Placement Drive Management

$page_title = "Manage Drives - Campus Connect";
$active_nav = "drives";
require_once '../includes/auth.php';
checkRole(['staff']);
require_once '../includes/header.php';
require_once '../includes/audit.php';

$action = isset($_GET['action']) ? $_GET['action'] : 'list';
$drive_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$error = '';

// Fetch all companies for select dropdowns
try {
    $companies = $pdo->query("SELECT id, name FROM companies ORDER BY name ASC")->fetchAll();
} catch (PDOException $e) {
    $companies = [];
}

// 1. Process Create/Update Drive submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($action === 'new' || $action === 'edit')) {
    // Verify CSRF
    $token = $_POST['csrf_token'] ?? '';
    if (!verifyCsrfToken($token)) {
        $_SESSION['error_message'] = "Invalid CSRF session token. Please try again.";
        header("Location: drives.php");
        exit;
    }
    
    $company_id = intval($_POST['company_id'] ?? 0);
    $title = trim($_POST['title'] ?? '');
    $job_description = trim($_POST['job_description'] ?? '');
    $eligibility_cgpa = floatval($_POST['eligibility_cgpa'] ?? 0);
    $eligibility_branch = trim($_POST['eligibility_branch'] ?? 'All');
    $eligibility_max_backlogs = intval($_POST['eligibility_max_backlogs'] ?? 0);
    $test_date = !empty($_POST['test_date']) ? $_POST['test_date'] : null;
    $interview_date = !empty($_POST['interview_date']) ? $_POST['interview_date'] : null;
    $status = $_POST['status'] ?? 'open';
    
    if (empty($company_id) || empty($title) || empty($job_description)) {
        $error = "Please fill in all required fields.";
    } else {
        try {
            if ($action === 'new') {
                $stmt = $pdo->prepare("
                    INSERT INTO drives (company_id, title, job_description, eligibility_cgpa, eligibility_branch, eligibility_max_backlogs, test_date, interview_date, status) 
                    VALUES (:company_id, :title, :job_description, :eligibility_cgpa, :eligibility_branch, :eligibility_max_backlogs, :test_date, :interview_date, :status)
                ");
                $stmt->execute([
                    ':company_id' => $company_id,
                    ':title' => $title,
                    ':job_description' => $job_description,
                    ':eligibility_cgpa' => $eligibility_cgpa,
                    ':eligibility_branch' => $eligibility_branch,
                    ':eligibility_max_backlogs' => $eligibility_max_backlogs,
                    ':test_date' => $test_date,
                    ':interview_date' => $interview_date,
                    ':status' => $status
                ]);
                $new_id = $pdo->lastInsertId();
                
                // Fetch company name for notification
                $compName = '';
                foreach ($companies as $c) {
                    if ($c['id'] == $company_id) $compName = $c['name'];
                }
                
                // Log audit trail
                logAudit('Drive Created', "New Drive ID: $new_id ($compName - $title)");
                
                // Broadcast notification to all students
                $stmtNotif = $pdo->prepare("
                    INSERT INTO notifications (user_id, message) 
                    VALUES (NULL, :message)
                ");
                $stmtNotif->execute([
                    ':message' => "New Recruitment Drive Opened: $compName is hiring for '$title'. Check your eligibility and apply!"
                ]);
                
                $_SESSION['success_message'] = "Placement drive created successfully!";
            } else {
                // Update
                $stmt = $pdo->prepare("
                    UPDATE drives 
                    SET company_id = :company_id, title = :title, job_description = :job_description, 
                        eligibility_cgpa = :eligibility_cgpa, eligibility_branch = :eligibility_branch, 
                        eligibility_max_backlogs = :eligibility_max_backlogs, test_date = :test_date, 
                        interview_date = :interview_date, status = :status
                    WHERE id = :id
                ");
                $stmt->execute([
                    ':company_id' => $company_id,
                    ':title' => $title,
                    ':job_description' => $job_description,
                    ':eligibility_cgpa' => $eligibility_cgpa,
                    ':eligibility_branch' => $eligibility_branch,
                    ':eligibility_max_backlogs' => $eligibility_max_backlogs,
                    ':test_date' => $test_date,
                    ':interview_date' => $interview_date,
                    ':status' => $status,
                    ':id' => $drive_id
                ]);
                
                logAudit('Drive Edited', "Modified Drive ID: $drive_id ($title)");
                $_SESSION['success_message'] = "Placement drive updated successfully!";
            }
            
            header("Location: drives.php");
            exit;
        } catch (PDOException $e) {
            $error = "Failed to save drive: " . $e->getMessage();
        }
    }
}

// 2. Process Status Toggle (Open/Close) Action
if ($action === 'toggle_status' && $drive_id > 0) {
    try {
        $stmtCurr = $pdo->prepare("SELECT status, title FROM drives WHERE id = :id");
        $stmtCurr->execute([':id' => $drive_id]);
        $curr = $stmtCurr->fetch();
        
        if ($curr) {
            $newStatus = $curr['status'] === 'open' ? 'closed' : 'open';
            $stmtToggle = $pdo->prepare("UPDATE drives SET status = :status WHERE id = :id");
            $stmtToggle->execute([':status' => $newStatus, ':id' => $drive_id]);
            
            logAudit('Drive Status Toggled', "Drive ID: $drive_id ($curr[title]) marked as " . strtoupper($newStatus));
            $_SESSION['success_message'] = "Drive status changed to " . strtoupper($newStatus);
        }
    } catch (PDOException $e) {
        $_SESSION['error_message'] = "Failed to toggle status: " . $e->getMessage();
    }
    header("Location: drives.php");
    exit;
}

// Edit Form Fetch
$drive_data = null;
if ($action === 'edit' && $drive_id > 0) {
    $stmtEdit = $pdo->prepare("SELECT * FROM drives WHERE id = :id");
    $stmtEdit->execute([':id' => $drive_id]);
    $drive_data = $stmtEdit->fetch();
    if (!$drive_data) {
        $_SESSION['error_message'] = "Drive not found.";
        header("Location: drives.php");
        exit;
    }
}

$csrf_token = getCsrfToken();
?>

<!-- Action 1: Create or Edit Drive Form -->
<?php if ($action === 'new' || $action === 'edit'): ?>
  <div class="page-title-area">
    <div>
      <h1 class="page-title"><?php echo $action === 'new' ? 'Create Recruitment Drive' : 'Edit Recruitment Drive'; ?></h1>
      <p class="page-subtitle">Configure test schedules and placement eligibility rules.</p>
    </div>
    <div>
      <a href="drives.php" class="btn btn-secondary btn-sm">
        <i class="fa-solid fa-arrow-left"></i> Cancel and Return
      </a>
    </div>
  </div>

  <?php if (!empty($error)): ?>
    <div class="alert alert-error">
      <i class="fa-solid fa-circle-exclamation"></i> <?php echo sanitize($error); ?>
    </div>
  <?php endif; ?>

  <div class="card" style="max-width: 800px; margin: 0 auto;">
    <form action="drives.php?action=<?php echo $action; ?>&id=<?php echo $drive_id; ?>" method="POST">
      <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
      
      <div class="grid-2" style="gap: 1.5rem; margin-bottom: 1.25rem;">
        <div class="form-group" style="margin-bottom: 0;">
          <label for="company_id" class="form-label">Recruiting Company *</label>
          <select id="company_id" name="company_id" class="form-control" required>
            <option value="" disabled selected>Select Company</option>
            <?php foreach ($companies as $comp): ?>
              <option value="<?php echo $comp['id']; ?>" <?php 
                echo (($drive_data && $drive_data['company_id'] == $comp['id']) || (isset($_POST['company_id']) && $_POST['company_id'] == $comp['id'])) ? 'selected' : ''; 
              ?>><?php echo sanitize($comp['name']); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        
        <div class="form-group" style="margin-bottom: 0;">
          <label for="title" class="form-label">Job Title / Role *</label>
          <input type="text" id="title" name="title" class="form-control" placeholder="e.g. Software Engineer Intern" required value="<?php 
            echo sanitize($drive_data['title'] ?? $_POST['title'] ?? ''); 
          ?>">
        </div>
      </div>
      
      <div class="form-group">
        <label for="job_description" class="form-label">Job Role & Skill Requirements *</label>
        <textarea id="job_description" name="job_description" class="form-control" rows="6" placeholder="Describe the job role, tasks, and preferred skill sets. This will also be scanned by Gemini AI for student matching." required><?php 
          echo sanitize($drive_data['job_description'] ?? $_POST['job_description'] ?? ''); 
        ?></textarea>
      </div>

      <h3 style="font-size: 1.05rem; font-weight: 600; margin-bottom: 1rem; border-bottom: 1px solid var(--border-glass); padding-bottom: 0.5rem; color: var(--color-warning);">
        <i class="fa-solid fa-graduation-cap"></i> Eligibility Criteria
      </h3>
      
      <div class="grid-3" style="gap: 1.5rem; margin-bottom: 1.5rem;">
        <div class="form-group" style="margin-bottom: 0;">
          <label for="eligibility_cgpa" class="form-label">Cutoff CGPA (Minimum)</label>
          <input type="number" id="eligibility_cgpa" name="eligibility_cgpa" class="form-control" step="0.01" min="0" max="10" placeholder="0.00" value="<?php 
            echo number_format($drive_data['eligibility_cgpa'] ?? $_POST['eligibility_cgpa'] ?? 0.00, 2); 
          ?>">
        </div>
        
        <div class="form-group" style="margin-bottom: 0;">
          <label for="eligibility_max_backlogs" class="form-label">Maximum Backlogs Allowed</label>
          <input type="number" id="eligibility_max_backlogs" name="eligibility_max_backlogs" class="form-control" min="0" placeholder="0" value="<?php 
            echo intval($drive_data['eligibility_max_backlogs'] ?? $_POST['eligibility_max_backlogs'] ?? 0); 
          ?>">
        </div>
        
        <div class="form-group" style="margin-bottom: 0;">
          <label for="eligibility_branch" class="form-label">Target Branches (Comma list)</label>
          <input type="text" id="eligibility_branch" name="eligibility_branch" class="form-control" placeholder="All or CSE, ECE, IT" value="<?php 
            echo sanitize($drive_data['eligibility_branch'] ?? $_POST['eligibility_branch'] ?? 'All'); 
          ?>">
        </div>
      </div>

      <h3 style="font-size: 1.05rem; font-weight: 600; margin-bottom: 1rem; border-bottom: 1px solid var(--border-glass); padding-bottom: 0.5rem; color: var(--color-accent);">
        <i class="fa-solid fa-calendar-days"></i> Placement Schedule
      </h3>
      
      <div class="grid-3" style="gap: 1.5rem; margin-bottom: 1.5rem;">
        <div class="form-group" style="margin-bottom: 0;">
          <label for="test_date" class="form-label">Aptitude Test Date</label>
          <input type="datetime-local" id="test_date" name="test_date" class="form-control" value="<?php 
            echo $drive_data && $drive_data['test_date'] ? date('Y-m-d\TH:i', strtotime($drive_data['test_date'])) : ''; 
          ?>">
        </div>
        
        <div class="form-group" style="margin-bottom: 0;">
          <label for="interview_date" class="form-label">Interview Date</label>
          <input type="datetime-local" id="interview_date" name="interview_date" class="form-control" value="<?php 
            echo $drive_data && $drive_data['interview_date'] ? date('Y-m-d\TH:i', strtotime($drive_data['interview_date'])) : ''; 
          ?>">
        </div>

        <div class="form-group" style="margin-bottom: 0;">
          <label for="status" class="form-label">Drive Status</label>
          <select id="status" name="status" class="form-control" required>
            <option value="open" <?php echo (($drive_data && $drive_data['status'] === 'open') || !isset($drive_data)) ? 'selected' : ''; ?>>Open</option>
            <option value="closed" <?php echo ($drive_data && $drive_data['status'] === 'closed') ? 'selected' : ''; ?>>Closed</option>
          </select>
        </div>
      </div>
      
      <button type="submit" class="btn btn-primary" style="width: 100%;">
        <i class="fa-solid fa-floppy-disk"></i> <?php echo $action === 'new' ? 'Publish Recruitment Drive' : 'Save Changes'; ?>
      </button>
    </form>
  </div>

<!-- Action 2: Drives Listing (Default) -->
<?php else: ?>
  <div class="page-title-area">
    <div>
      <h1 class="page-title">Manage Placement Drives</h1>
      <p class="page-subtitle">Publish new drives, toggle recruiting statuses, or manage applicants.</p>
    </div>
    <div>
      <a href="drives.php?action=new" class="btn btn-primary btn-sm">
        <i class="fa-solid fa-plus"></i> Publish New Drive
      </a>
    </div>
  </div>

  <?php
  // Query all drives
  try {
      $stmtList = $pdo->query("
          SELECT d.*, c.name as company_name,
                 (SELECT COUNT(*) FROM applications WHERE drive_id = d.id) as total_applicants
          FROM drives d
          JOIN companies c ON d.company_id = c.id
          ORDER BY d.created_at DESC
      ");
      $drives_list = $stmtList->fetchAll();
  } catch (PDOException $e) {
      echo '<div class="alert alert-error">Failed to query drives: ' . sanitize($e->getMessage()) . '</div>';
      $drives_list = [];
  }
  ?>

  <?php if (empty($drives_list)): ?>
    <div class="card" style="text-align: center; padding: 3rem;">
      <i class="fa-solid fa-briefcase" style="font-size: 3rem; color: var(--text-muted); margin-bottom: 1rem;"></i>
      <h3>No drives published yet</h3>
      <p style="color: var(--text-muted); margin-top: 0.5rem;">Click the button above to publish your first college recruitment drive.</p>
    </div>
  <?php else: ?>
    <div class="table-container">
      <table>
        <thead>
          <tr>
            <th>Company & Role</th>
            <th>Eligibility Cutoffs</th>
            <th>Schedule Dates</th>
            <th>Applicants</th>
            <th>Status</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($drives_list as $drive): ?>
            <tr>
              <td>
                <strong><?php echo sanitize($drive['company_name']); ?></strong>
                <div style="font-size: 0.85rem; color: var(--text-secondary);"><?php echo sanitize($drive['title']); ?></div>
              </td>
              <td>
                <div style="font-size: 0.85rem;">CGPA cutoff: <strong><?php echo number_format($drive['eligibility_cgpa'], 2); ?></strong></div>
                <div style="font-size: 0.75rem; color: var(--text-muted);">Max Backlogs: <?php echo intval($drive['eligibility_max_backlogs']); ?> | Branches: <?php echo sanitize($drive['eligibility_branch']); ?></div>
              </td>
              <td>
                <div style="font-size: 0.8rem; color: var(--text-secondary);"><i class="fa-solid fa-desktop" style="width: 15px;"></i> Test: <?php echo $drive['test_date'] ? date('M d, Y h:i A', strtotime($drive['test_date'])) : 'TBD'; ?></div>
                <div style="font-size: 0.8rem; color: var(--text-secondary);"><i class="fa-solid fa-users" style="width: 15px;"></i> Interview: <?php echo $drive['interview_date'] ? date('M d, Y h:i A', strtotime($drive['interview_date'])) : 'TBD'; ?></div>
              </td>
              <td>
                <span class="badge badge-applied" style="font-size: 0.75rem;">
                  <?php echo intval($drive['total_applicants']); ?> Candidates
                </span>
              </td>
              <td>
                <span class="badge badge-<?php echo $drive['status'] === 'open' ? 'selected' : 'rejected'; ?>" style="font-size: 0.75rem; text-transform: uppercase;">
                  <?php echo sanitize($drive['status']); ?>
                </span>
              </td>
              <td>
                <div style="display: flex; gap: 0.4rem;">
                  <a href="verify.php?drive_id=<?php echo $drive['id']; ?>" class="btn btn-secondary btn-sm" title="Review Applications" style="padding: 0.3rem 0.5rem;">
                    <i class="fa-solid fa-user-check"></i>
                  </a>
                  <a href="drives.php?action=edit&id=<?php echo $drive['id']; ?>" class="btn btn-secondary btn-sm" title="Edit Drive Rules" style="padding: 0.3rem 0.5rem;">
                    <i class="fa-solid fa-pen"></i>
                  </a>
                  <a href="drives.php?action=toggle_status&id=<?php echo $drive['id']; ?>" class="btn btn-secondary btn-sm" title="<?php echo $drive['status'] === 'open' ? 'Close Drive' : 'Reopen Drive'; ?>" style="padding: 0.3rem 0.5rem;">
                    <i class="fa-solid <?php echo $drive['status'] === 'open' ? 'fa-circle-stop' : 'fa-circle-play'; ?>" style="color: <?php echo $drive['status'] === 'open' ? 'var(--color-danger)' : 'var(--color-success)'; ?>"></i>
                  </a>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
<?php endif; ?>

<?php
require_once '../includes/footer.php';
?>
