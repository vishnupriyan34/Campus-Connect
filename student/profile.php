<?php
// Student Profile Management & Verification Requests

$page_title = "Student Profile - Campus Connect";
$active_nav = "profile";
require_once '../includes/auth.php';
checkRole(['student']);
require_once '../includes/header.php';
require_once '../includes/audit.php';

$student_id = $_SESSION['user_id'];

// Ensure uploads directories exist
$resume_dir = __DIR__ . '/../uploads/resumes/';
if (!is_dir($resume_dir)) {
    mkdir($resume_dir, 0777, true);
}

// 1. Process Resume Upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['resume'])) {
    // Verify CSRF
    $token = $_POST['csrf_token'] ?? '';
    if (!verifyCsrfToken($token)) {
        $_SESSION['error_message'] = "Invalid CSRF session token. Please try again.";
        header("Location: profile.php");
        exit;
    }
    
    $file = $_FILES['resume'];
    
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $_SESSION['error_message'] = "Upload failed with error code: " . $file['error'];
    } else {
        $allowed_mime = 'application/pdf';
        $file_mime = mime_content_type($file['tmp_name']);
        $file_size = $file['size'];
        
        if ($file_mime !== $allowed_mime) {
            $_SESSION['error_message'] = "Only PDF files are allowed.";
        } elseif ($file_size > 2 * 1024 * 1024) { // 2MB Limit
            $_SESSION['error_message'] = "Resume file size must be less than 2MB.";
        } else {
            // Secure upload filename
            $filename = "student_" . $student_id . "_" . time() . ".pdf";
            $dest_path = $resume_dir . $filename;
            
            // Delete old resume file if exists
            $stmtOld = $pdo->prepare("SELECT resume_path FROM students WHERE user_id = :user_id");
            $stmtOld->execute([':user_id' => $student_id]);
            $oldPath = $stmtOld->fetchColumn();
            if ($oldPath && file_exists(__DIR__ . '/../' . $oldPath)) {
                unlink(__DIR__ . '/../' . $oldPath);
            }
            
            if (move_uploaded_file($file['tmp_name'], $dest_path)) {
                // Update DB
                $relative_path = "uploads/resumes/" . $filename;
                $stmtUpdate = $pdo->prepare("UPDATE students SET resume_path = :path WHERE user_id = :user_id");
                $stmtUpdate->execute([':path' => $relative_path, ':user_id' => $student_id]);
                
                logAudit('Resume Uploaded', 'Updated resume PDF file: ' . $filename, $student_id);
                $_SESSION['success_message'] = "Resume uploaded successfully!";
            } else {
                $_SESSION['error_message'] = "Error saving upload file on server.";
            }
        }
    }
    header("Location: profile.php");
    exit;
}

// 2. Process Academic Verification Requests (CGPA / Backlog update)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'request_verification') {
    // Verify CSRF
    $token = $_POST['csrf_token'] ?? '';
    if (!verifyCsrfToken($token)) {
        $_SESSION['error_message'] = "Invalid CSRF session token. Please try again.";
        header("Location: profile.php");
        exit;
    }
    
    $field_name = $_POST['field_name'] ?? '';
    $new_value = trim($_POST['new_value'] ?? '');
    
    if ($field_name !== 'cgpa' && $field_name !== 'backlog_count') {
        $_SESSION['error_message'] = "Invalid update request field.";
    } elseif (empty($new_value)) {
        $_SESSION['error_message'] = "Please provide a valid value.";
    } else {
        try {
            // Fetch current student values
            $stmtCurr = $pdo->prepare("SELECT cgpa, backlog_count FROM students WHERE user_id = :user_id");
            $stmtCurr->execute([':user_id' => $student_id]);
            $currData = $stmtCurr->fetch();
            
            $old_value = ($field_name === 'cgpa') ? $currData['cgpa'] : $currData['backlog_count'];
            
            // Check if there is already a pending verification request for this field
            $stmtCheck = $pdo->prepare("
                SELECT id FROM verification_requests 
                WHERE student_id = :student_id AND field_name = :field_name AND status = 'pending'
            ");
            $stmtCheck->execute([
                ':student_id' => $student_id,
                ':field_name' => $field_name
            ]);
            
            if ($stmtCheck->fetch()) {
                $_SESSION['error_message'] = "You already have a pending verification request for " . strtoupper($field_name) . ". Wait for staff review.";
            } else {
                // Insert request
                $stmtReq = $pdo->prepare("
                    INSERT INTO verification_requests (student_id, field_name, old_value, new_value, status) 
                    VALUES (:student_id, :field_name, :old_value, :new_value, 'pending')
                ");
                $stmtReq->execute([
                    ':student_id' => $student_id,
                    ':field_name' => $field_name,
                    ':old_value'   => $old_value,
                    ':new_value'   => $new_value
                ]);
                
                logAudit('Academic Edit Requested', 'Requested ' . strtoupper($field_name) . ' update: ' . $new_value, $student_id);
                $_SESSION['success_message'] = "Verification request submitted! Placements staff will review it shortly.";
            }
        } catch (PDOException $e) {
            $_SESSION['error_message'] = "Failed to submit request: " . $e->getMessage();
        }
    }
    header("Location: profile.php");
    exit;
}

// 3. Process Skills Update (direct change allowed)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_skills') {
    // Verify CSRF
    $token = $_POST['csrf_token'] ?? '';
    if (!verifyCsrfToken($token)) {
        $_SESSION['error_message'] = "Invalid CSRF session token. Please try again.";
        header("Location: profile.php");
        exit;
    }
    
    $skills = trim($_POST['skills'] ?? '');
    
    try {
        $stmtSkills = $pdo->prepare("UPDATE students SET skills = :skills WHERE user_id = :user_id");
        $stmtSkills->execute([':skills' => $skills, ':user_id' => $student_id]);
        
        $_SESSION['success_message'] = "Skills profile updated successfully.";
    } catch (PDOException $e) {
        $_SESSION['error_message'] = "Failed to save skills: " . $e->getMessage();
    }
    header("Location: profile.php");
    exit;
}

// Fetch current details
$stmtDetail = $pdo->prepare("
    SELECT u.name, u.email, s.* 
    FROM users u 
    JOIN students s ON u.id = s.user_id 
    WHERE u.id = :user_id
");
$stmtDetail->execute([':user_id' => $student_id]);
$profile = $stmtDetail->fetch();

// Fetch verification requests history
$stmtReqHistory = $pdo->prepare("
    SELECT vr.*, u.name as resolved_by_name 
    FROM verification_requests vr 
    LEFT JOIN users u ON vr.resolved_by = u.id 
    WHERE vr.student_id = :student_id 
    ORDER BY vr.created_at DESC
");
$stmtReqHistory->execute([':student_id' => $student_id]);
$requests = $stmtReqHistory->fetchAll();

$csrf_token = getCsrfToken();
?>

<div class="page-title-area">
  <div>
    <h1 class="page-title">My Profile</h1>
    <p class="page-subtitle">Manage your verified academic data, skills, and resume.</p>
  </div>
  <div>
    <a href="dashboard.php" class="btn btn-secondary btn-sm">
      <i class="fa-solid fa-arrow-left"></i> Back to Dashboard
    </a>
  </div>
</div>

<div class="grid-2">
  <!-- Left Side: Skills Editor & Resume PDF Upload -->
  <div style="display: flex; flex-direction: column; gap: 1.5rem;">
    <!-- Resume Upload -->
    <div class="card">
      <h2 style="font-size: 1.25rem; font-weight: 600; margin-bottom: 1rem;"><i class="fa-solid fa-file-pdf" style="color: var(--color-danger);"></i> Curriculum Vitae (Resume)</h2>
      
      <?php if (!empty($profile['resume_path'])): ?>
        <div style="display: flex; align-items: center; justify-content: space-between; background-color: var(--bg-primary); padding: 0.75rem 1rem; border-radius: var(--radius-md); border: 1px solid var(--border-glass); margin-bottom: 1.25rem;">
          <div style="display: flex; align-items: center; gap: 0.75rem;">
            <i class="fa-solid fa-file-pdf" style="font-size: 2rem; color: var(--color-danger);"></i>
            <div style="font-size: 0.85rem;">
              <div style="font-weight: 600; color: var(--text-primary);">Resume Uploaded</div>
              <a href="../<?php echo sanitize($profile['resume_path']); ?>" target="_blank" style="color: var(--color-accent); text-decoration: none;">View Resume &rarr;</a>
            </div>
          </div>
          <span style="font-size: 0.75rem; color: var(--color-success); font-weight: 600;"><i class="fa-solid fa-circle-check"></i> Active</span>
        </div>
      <?php else: ?>
        <div class="alert alert-warning" style="font-size: 0.85rem; padding: 0.75rem 1rem; margin-bottom: 1.25rem;">
          <i class="fa-solid fa-triangle-exclamation"></i> No resume uploaded. You must upload a resume to apply to drives.
        </div>
      <?php endif; ?>
      
      <form action="profile.php" method="POST" enctype="multipart/form-data">
        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
        
        <div class="form-group">
          <label for="resume" class="form-label">Upload New Resume (PDF Format, Max 2MB)</label>
          <input type="file" id="resume" name="resume" class="form-control" accept="application/pdf" required style="padding: 0.5rem 0.75rem;">
        </div>
        
        <button type="submit" class="btn btn-primary btn-sm" style="width: 100%;">
          <i class="fa-solid fa-cloud-arrow-up"></i> Upload and Link to Profile
        </button>
      </form>
    </div>
    
    <!-- Skills Tag Manager -->
    <div class="card">
      <h2 style="font-size: 1.25rem; font-weight: 600; margin-bottom: 1rem;"><i class="fa-solid fa-tags" style="color: var(--color-accent);"></i> Professional Skills</h2>
      
      <div style="margin-bottom: 1.25rem;">
        <h4 style="font-size: 0.85rem; color: var(--text-secondary); margin-bottom: 0.5rem;">Current Skills Tags:</h4>
        <div class="skills-list">
          <?php 
          if (!empty($profile['skills'])) {
              $tags = explode(',', $profile['skills']);
              foreach ($tags as $tag) {
                  $t = trim($tag);
                  if (!empty($t)) {
                      echo '<span class="skill-tag">' . sanitize($t) . '</span>';
                  }
              }
          } else {
              echo '<p style="font-size: 0.85rem; color: var(--text-muted);">No skills added yet.</p>';
          }
          ?>
        </div>
      </div>
      
      <form action="profile.php" method="POST">
        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
        <input type="hidden" name="action" value="update_skills">
        
        <div class="form-group">
          <label for="skills" class="form-label">Edit Skills (Comma-separated list)</label>
          <input type="text" id="skills" name="skills" class="form-control" placeholder="e.g. C++, Java, AWS, Docker, HTML" value="<?php echo sanitize($profile['skills']); ?>">
        </div>
        
        <button type="submit" class="btn btn-secondary btn-sm" style="width: 100%;">
          <i class="fa-solid fa-floppy-disk"></i> Update Skills
        </button>
      </form>
    </div>
  </div>
  
  <!-- Right Side: Academic Profile and Verification Requests -->
  <div style="display: flex; flex-direction: column; gap: 1.5rem;">
    <!-- Profile Academic Details -->
    <div class="card">
      <h2 style="font-size: 1.25rem; font-weight: 600; margin-bottom: 1rem;"><i class="fa-solid fa-award" style="color: var(--color-warning);"></i> Academic Information</h2>
      
      <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1.5rem; background-color: var(--bg-primary); padding: 1rem; border-radius: var(--radius-md); border: 1px solid var(--border-glass);">
        <div>
          <div style="font-size: 0.8rem; color: var(--text-muted);">DEPARTMENT</div>
          <div style="font-weight: 600; color: var(--text-primary); font-size: 1.1rem;"><?php echo sanitize($profile['department']); ?></div>
        </div>
        <div>
          <div style="font-size: 0.8rem; color: var(--text-muted);">STUDENT ID</div>
          <div style="font-weight: 600; color: var(--text-primary); font-size: 1.1rem;">#<?php echo $student_id; ?></div>
        </div>
        <div>
          <div style="font-size: 0.8rem; color: var(--text-muted);">VERIFIED CGPA</div>
          <div style="font-weight: 700; color: var(--color-success); font-size: 1.25rem;"><?php echo number_format($profile['cgpa'], 2); ?></div>
        </div>
        <div>
          <div style="font-size: 0.8rem; color: var(--text-muted);">ACTIVE BACKLOGS</div>
          <div style="font-weight: 700; color: <?php echo $profile['backlog_count'] > 0 ? 'var(--color-danger)' : 'var(--color-success)'; ?>; font-size: 1.25rem;"><?php echo intval($profile['backlog_count']); ?></div>
        </div>
      </div>
      
      <!-- Verification Request Form -->
      <h3 style="font-size: 1.05rem; font-weight: 600; margin-bottom: 0.75rem;"><i class="fa-solid fa-circle-question"></i> Request Academic Edit</h3>
      <p style="font-size: 0.8rem; color: var(--text-secondary); margin-bottom: 1rem; line-height: 1.4;">
        For academic integrity, changing CGPA or Backlog count submits a request to the TPO Office for review and approval.
      </p>
      
      <form action="profile.php" method="POST">
        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
        <input type="hidden" name="action" value="request_verification">
        
        <div class="grid-2" style="gap: 1rem; margin-bottom: 0.75rem;">
          <div class="form-group" style="margin-bottom: 0;">
            <label for="field_name" class="form-label">Field to update</label>
            <select id="field_name" name="field_name" class="form-control" required style="padding: 0.5rem 0.75rem; font-size: 0.9rem;">
              <option value="cgpa">CGPA</option>
              <option value="backlog_count">Backlog Count</option>
            </select>
          </div>
          
          <div class="form-group" style="margin-bottom: 0;">
            <label for="new_value" class="form-label">New Value</label>
            <input type="number" id="new_value" name="new_value" class="form-control" step="0.01" min="0" max="10" required placeholder="e.g. 8.85" style="padding: 0.5rem 0.75rem; font-size: 0.9rem;">
          </div>
        </div>
        
        <button type="submit" class="btn btn-secondary btn-sm" style="width: 100%;">
          <i class="fa-solid fa-share-from-square"></i> Submit for TPO Review
        </button>
      </form>
    </div>
    
    <!-- Requests Timeline -->
    <div class="card">
      <h2 style="font-size: 1.25rem; font-weight: 600; margin-bottom: 1rem;"><i class="fa-solid fa-clock-rotate-left" style="color: var(--text-muted);"></i> Edit Request History</h2>
      
      <?php if (empty($requests)): ?>
        <p style="color: var(--text-muted); font-size: 0.85rem; text-align: center;">No academic requests submitted yet.</p>
      <?php else: ?>
        <div style="display: flex; flex-direction: column; gap: 0.75rem; max-height: 250px; overflow-y: auto; padding-right: 0.25rem;">
          <?php foreach ($requests as $req): ?>
            <div style="background-color: var(--bg-primary); padding: 0.75rem 1rem; border-radius: var(--radius-md); border: 1px solid var(--border-glass); font-size: 0.85rem;">
              <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.25rem;">
                <span style="font-weight: 600; color: var(--text-primary);">
                  Update <?php echo strtoupper($req['field_name']); ?>: <?php echo sanitize($req['old_value']); ?> &rarr; <?php echo sanitize($req['new_value']); ?>
                </span>
                <span class="badge badge-<?php echo strtolower($req['status']); ?>"><?php echo sanitize($req['status']); ?></span>
              </div>
              <div style="color: var(--text-muted); font-size: 0.75rem;">
                Requested on: <?php echo date('M d, Y h:i A', strtotime($req['created_at'])); ?>
              </div>
              
              <?php if ($req['status'] === 'rejected' && !empty($req['reason'])): ?>
                <div style="margin-top: 0.5rem; padding: 0.4rem 0.6rem; background-color: var(--color-danger-light); border-left: 3px solid var(--color-danger); border-radius: 4px; font-size: 0.8rem; color: #fca5a5;">
                  <strong>Reason:</strong> <?php echo sanitize($req['reason']); ?>
                </div>
              <?php elseif ($req['status'] === 'approved'): ?>
                <div style="font-size: 0.75rem; color: var(--color-success); margin-top: 0.25rem;">
                  Approved by: <?php echo sanitize($req['resolved_by_name']); ?>
                </div>
              <?php endif; ?>
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
