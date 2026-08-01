<?php
// Staff Offer Letter Upload & Assignment

$page_title = "Upload Offer Letter - Campus Connect";
$active_nav = "upload_offer";
require_once '../includes/auth.php';
checkRole(['staff']);
require_once '../includes/header.php';
require_once '../includes/audit.php';

$staff_id = $_SESSION['user_id'];
$error = '';

// Ensure uploads offers directory exists
$offer_dir = __DIR__ . '/../uploads/offers/';
if (!is_dir($offer_dir)) {
    mkdir($offer_dir, 0777, true);
}

// 1. Fetch Selected placements for dropdown list
try {
    $stmtSelections = $pdo->query("
        SELECT a.id as app_id, a.student_id, a.drive_id, u.name as student_name, d.title as drive_title, c.name as company_name
        FROM applications a
        JOIN users u ON a.student_id = u.id
        JOIN drives d ON a.drive_id = d.id
        JOIN companies c ON d.company_id = c.id
        WHERE a.status = 'Selected'
        ORDER BY u.name ASC
    ");
    $placements = $stmtSelections->fetchAll();
} catch (PDOException $e) {
    $placements = [];
}

// 2. Handle Upload action
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF
    $token = $_POST['csrf_token'] ?? '';
    if (!verifyCsrfToken($token)) {
        $_SESSION['error_message'] = "Invalid CSRF session token.";
        header("Location: upload_offer.php");
        exit;
    }
    
    $placement_val = $_POST['placement_id'] ?? ''; // Format: "student_id|drive_id"
    $file = $_FILES['offer_letter'] ?? null;
    
    if (empty($placement_val) || !$file || $file['error'] !== UPLOAD_ERR_OK) {
        $error = "Please select a student and provide a valid PDF document.";
    } else {
        list($student_id_target, $drive_id_target) = explode('|', $placement_val);
        $student_id_target = intval($student_id_target);
        $drive_id_target = intval($drive_id_target);
        
        $allowed_mime = 'application/pdf';
        $file_mime = mime_content_type($file['tmp_name']);
        $file_size = $file['size'];
        
        if ($file_mime !== $allowed_mime) {
            $error = "Only PDF documents are allowed for offer letters.";
        } elseif ($file_size > 2 * 1024 * 1024) { // 2MB Limit
            $error = "File size must be less than 2MB.";
        } else {
            try {
                // Fetch company & drive names for audit logging / notification
                $stmtNames = $pdo->prepare("
                    SELECT d.title as drive_title, c.name as company_name, u.name as student_name
                    FROM drives d
                    JOIN companies c ON d.company_id = c.id
                    JOIN users u ON u.id = :student_id
                    WHERE d.id = :drive_id
                ");
                $stmtNames->execute([':student_id' => $student_id_target, ':drive_id' => $drive_id_target]);
                $names = $stmtNames->fetch();
                
                $filename = "offer_" . $student_id_target . "_" . $drive_id_target . "_" . time() . ".pdf";
                $dest_path = $offer_dir . $filename;
                
                // Delete existing offer letter entry/file if already uploaded for this specific student-drive
                $stmtCheck = $pdo->prepare("SELECT file_path FROM offer_letters WHERE student_id = :student_id AND drive_id = :drive_id");
                $stmtCheck->execute([':student_id' => $student_id_target, ':drive_id' => $drive_id_target]);
                $existPath = $stmtCheck->fetchColumn();
                if ($existPath && file_exists(__DIR__ . '/../' . $existPath)) {
                    unlink(__DIR__ . '/../' . $existPath);
                }
                
                if (move_uploaded_file($file['tmp_name'], $dest_path)) {
                    $relative_path = "uploads/offers/" . $filename;
                    
                    // Upsert DB record
                    if ($existPath) {
                        $stmtUpdate = $pdo->prepare("
                            UPDATE offer_letters 
                            SET file_path = :path, uploaded_by = :staff_id, uploaded_at = CURRENT_TIMESTAMP 
                            WHERE student_id = :student_id AND drive_id = :drive_id
                        ");
                        $stmtUpdate->execute([
                            ':path' => $relative_path,
                            ':staff_id' => $staff_id,
                            ':student_id' => $student_id_target,
                            ':drive_id' => $drive_id_target
                        ]);
                    } else {
                        $stmtInsert = $pdo->prepare("
                            INSERT INTO offer_letters (student_id, drive_id, file_path, uploaded_by) 
                            VALUES (:student_id, :drive_id, :path, :staff_id)
                        ");
                        $stmtInsert->execute([
                            ':student_id' => $student_id_target,
                            ':drive_id' => $drive_id_target,
                            ':path' => $relative_path,
                            ':staff_id' => $staff_id
                        ]);
                    }
                    
                    // Notify student
                    $notifMsg = "Your official offer letter for " . $names['company_name'] . " - " . $names['drive_title'] . " has been uploaded! View/Download it in the Offer Letters section.";
                    $stmtNotif = $pdo->prepare("INSERT INTO notifications (user_id, message) VALUES (:user_id, :message)");
                    $stmtNotif->execute([
                        ':user_id' => $student_id_target,
                        ':message' => $notifMsg
                    ]);
                    
                    logAudit('Offer Letter Uploaded', "Uploaded offer letter for $names[student_name] ($names[company_name] - $names[drive_title])");
                    
                    $_SESSION['success_message'] = "Offer letter uploaded and student notified successfully!";
                    header("Location: upload_offer.php");
                    exit;
                } else {
                    $error = "Failed to save file on server directory.";
                }
            } catch (PDOException $e) {
                $error = "Database write failed: " . $e->getMessage();
            }
        }
    }
}

$csrf_token = getCsrfToken();
?>

<div class="page-title-area">
  <div>
    <h1 class="page-title">Upload Offer Letters</h1>
    <p class="page-subtitle">Publish digital offer letters (PDF) for selected students.</p>
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

<div class="grid-2">
  <!-- Upload Form -->
  <div class="card">
    <h3 style="font-size: 1.15rem; font-weight: 600; margin-bottom: 1rem;">Upload Form</h3>
    
    <?php if (empty($placements)): ?>
      <div style="text-align: center; padding: 2rem; color: var(--text-muted);">
        <i class="fa-solid fa-circle-exclamation" style="font-size: 2.5rem; margin-bottom: 0.5rem; display: block;"></i>
        No students are currently marked as "Selected".
      </div>
    <?php else: ?>
      <form action="upload_offer.php" method="POST" enctype="multipart/form-data">
        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
        
        <div class="form-group">
          <label for="placement_id" class="form-label">Select Placed Student *</label>
          <select id="placement_id" name="placement_id" class="form-control" required>
            <option value="" disabled selected>Select Placement</option>
            <?php foreach ($placements as $pl): ?>
              <option value="<?php echo $pl['student_id'] . '|' . $pl['drive_id']; ?>">
                <?php echo sanitize($pl['student_name']); ?> &mdash; <?php echo sanitize($pl['company_name']); ?> (<?php echo sanitize($pl['drive_title']); ?>)
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        
        <div class="form-group">
          <label for="offer_letter" class="form-label">Offer Letter Document (PDF Format, Max 2MB) *</label>
          <input type="file" id="offer_letter" name="offer_letter" class="form-control" accept="application/pdf" required style="padding: 0.5rem 0.75rem;">
        </div>
        
        <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 1rem;">
          <i class="fa-solid fa-cloud-arrow-up"></i> Upload and Assign Offer
        </button>
      </form>
    <?php endif; ?>
  </div>
  
  <!-- Upload Guidelines -->
  <div class="card">
    <h3 style="font-size: 1.15rem; font-weight: 600; margin-bottom: 1rem;">Uploader Guidelines</h3>
    <ul style="padding-left: 1.25rem; font-size: 0.9rem; color: var(--text-secondary); display: flex; flex-direction: column; gap: 0.75rem; line-height: 1.5;">
      <li>
        <strong>Verification Required:</strong> You can only upload offer letters for students who have been explicitly marked as <span class="badge badge-selected" style="padding: 0.1rem 0.4rem; font-size: 0.7rem;">Selected</span> in the drive applications verification pipeline.
      </li>
      <li>
        <strong>Formatting:</strong> The document must be saved in PDF format. Text and image quality should be clear for students to print or submit for campus records.
      </li>
      <li>
        <strong>File Size Limit:</strong> Files must not exceed 2MB. To upload, compress documents if they exceed this limit.
      </li>
      <li>
        <strong>Re-uploads:</strong> If you upload a file for an already assigned student-drive, the previous document is overwritten automatically.
      </li>
    </ul>
  </div>
</div>

<?php
require_once '../includes/footer.php';
?>
