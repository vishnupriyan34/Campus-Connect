<?php


$page_title = "Student Dashboard - Campus Connect";
$active_nav = "dashboard";
require_once '../includes/auth.php';
checkRole(['student']);
require_once '../includes/header.php';

$student_id = $_SESSION['user_id'];

// 1. Fetch Student academic profile
$stmtStudent = $pdo->prepare("SELECT * FROM students WHERE user_id = :user_id");
$stmtStudent->execute([':user_id' => $student_id]);
$student = $stmtStudent->fetch();

if (!$student) {
    // If student profile not completed, redirect to profile page
    $_SESSION['error_message'] = "Please complete your academic profile first.";
    header("Location: profile.php");
    exit;
}

$cgpa = floatval($student['cgpa']);
$backlogs = intval($student['backlog_count']);
$dept = $student['department'];

// 2. Fetch Eligible Drives (filtered server-side)
// Student can only see drives where they meet CGPA, max backlog, and department eligibility
try {
    $stmtDrives = $pdo->prepare("
        SELECT d.*, c.name as company_name, c.package_range, a.status as app_status, a.id as app_id
        FROM drives d
        JOIN companies c ON d.company_id = c.id
        LEFT JOIN applications a ON a.drive_id = d.id AND a.student_id = :student_id
        WHERE d.status = 'open'
          AND :cgpa >= d.eligibility_cgpa
          AND :backlogs <= d.eligibility_max_backlogs
          AND (
            LOWER(d.eligibility_branch) = 'all' 
            OR FIND_IN_SET(:dept, REPLACE(d.eligibility_branch, ' ', '')) > 0
          )
        ORDER BY d.test_date ASC
    ");
    $stmtDrives->execute([
        ':student_id' => $student_id,
        ':cgpa' => $cgpa,
        ':backlogs' => $backlogs,
        ':dept' => $dept
    ]);
    $eligibleDrives = $stmtDrives->fetchAll();
} catch (PDOException $e) {
    echo '<div class="alert alert-error">Failed to load drives: ' . sanitize($e->getMessage()) . '</div>';
    $eligibleDrives = [];
}

// 3. Fetch Application stats
try {
    $stmtStats = $pdo->prepare("
        SELECT 
            COUNT(*) as total_apps,
            SUM(CASE WHEN status = 'Shortlisted' THEN 1 ELSE 0 END) as total_shortlisted,
            SUM(CASE WHEN status = 'Selected' THEN 1 ELSE 0 END) as total_selected
        FROM applications 
        WHERE student_id = :student_id
    ");
    $stmtStats->execute([':student_id' => $student_id]);
    $stats = $stmtStats->fetch();
} catch (PDOException $e) {
    $stats = ['total_apps' => 0, 'total_shortlisted' => 0, 'total_selected' => 0];
}

// Helper to determine status stepper variables
function getStepperData($status) {
    $steps = [
        'Applied' => ['class' => 'completed', 'num' => 1],
        'Shortlisted' => ['class' => 'completed', 'num' => 2],
        'Selected' => ['class' => 'completed', 'num' => 3],
        'Rejected' => ['class' => 'rejected', 'num' => 3]
    ];
    return $steps[$status] ?? ['class' => '', 'num' => 0];
}
?>

<div class="page-title-area">
  <div>
    <h1 class="page-title">Welcome back, <?php echo sanitize($_SESSION['name']); ?></h1>
    <p class="page-subtitle">Track your recruitment eligibility and ongoing applications.</p>
  </div>
  <div>
    <a href="profile.php" class="btn btn-primary btn-sm">
      <i class="fa-solid fa-user-edit"></i> Edit Skills & Resume
    </a>
  </div>
</div>

<!-- Placement Status Statistics Tiles -->
<div class="stats-grid">
  <div class="stat-tile">
    <div class="stat-icon"><i class="fa-solid fa-file-signature"></i></div>
    <div class="user-info">
      <div class="stat-value"><?php echo intval($stats['total_apps']); ?></div>
      <div class="stat-label">Applications Submitted</div>
    </div>
  </div>
  
  <div class="stat-tile">
    <div class="stat-icon warning"><i class="fa-solid fa-hourglass-half"></i></div>
    <div class="user-info">
      <div class="stat-value"><?php echo intval($stats['total_shortlisted']); ?></div>
      <div class="stat-label">Shortlisted Drives</div>
    </div>
  </div>
  
  <div class="stat-tile">
    <div class="stat-icon success"><i class="fa-solid fa-award"></i></div>
    <div class="user-info">
      <div class="stat-value"><?php echo intval($stats['total_selected']); ?></div>
      <div class="stat-label">Offers Secured</div>
    </div>
  </div>

  <div class="stat-tile">
    <div class="stat-icon accent"><i class="fa-solid fa-user-graduate"></i></div>
    <div class="user-info">
      <div class="stat-value"><?php echo number_format($cgpa, 2); ?></div>
      <div class="stat-label">Verified CGPA (<?php echo sanitize($dept); ?>)</div>
    </div>
  </div>
</div>

<?php if (empty($student['resume_path'])): ?>
  <div class="alert alert-warning" style="margin-bottom: 2rem;">
    <i class="fa-solid fa-triangle-exclamation"></i> 
    <strong>Resume missing!</strong> You must upload a resume in the <a href="profile.php" style="color: inherit; font-weight: bold; text-decoration: underline;">Profile Section</a> before you can apply to any eligible drives.
  </div>
<?php endif; ?>

<!-- Section 1: Active Placement Applications Tracker -->
<h2 style="margin-bottom: 1.25rem;"><i class="fa-solid fa-list-check" style="color: var(--color-primary);"></i> My Placement Progress</h2>

<?php
// Filter applications
$appliedDrives = array_filter($eligibleDrives, function($d) {
    return !empty($d['app_status']);
});

if (empty($appliedDrives)):
?>
  <div class="card" style="text-align: center; padding: 2.5rem; margin-bottom: 3rem;">
    <p style="color: var(--text-muted);">You haven't applied to any drives yet. Check the eligible drives below!</p>
  </div>
<?php else: ?>
  <div style="display: flex; flex-direction: column; gap: 2rem; margin-bottom: 3rem;">
    <?php foreach ($appliedDrives as $drive): 
      $status = $drive['app_status'];
      $stepper = getStepperData($status);
      $progressPct = ($stepper['num'] - 1) * 50;
      if ($status === 'Rejected') $progressPct = 100; // Full line for rejection stepper
    ?>
      <div class="card" style="padding: 1.5rem 2rem;">
        <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 1rem;">
          <div>
            <h3 style="font-size: 1.25rem; font-weight: 600; color: var(--text-primary);"><?php echo sanitize($drive['title']); ?></h3>
            <p style="color: var(--text-muted); font-size: 0.9rem;">
              <i class="fa-solid fa-building"></i> <?php echo sanitize($drive['company_name']); ?> &nbsp;|&nbsp; 
              <i class="fa-solid fa-money-bill-wave"></i> <?php echo sanitize($drive['package_range']); ?>
            </p>
          </div>
          <div>
            <span class="badge badge-<?php echo strtolower($status); ?>"><?php echo sanitize($status); ?></span>
            <a href="drive_details.php?id=<?php echo $drive['id']; ?>" class="btn btn-secondary btn-sm" style="margin-left: 0.5rem;">
              View Details &larr;
            </a>
          </div>
        </div>

        <!-- Dynamic Visual Stepper -->
        <div class="stepper-container">
          <div class="stepper-progress-bar" style="width: <?php echo $progressPct; ?>%; <?php echo $status === 'Rejected' ? 'background: var(--gradient-danger);' : ''; ?>"></div>
          
          <div class="step-node <?php echo $stepper['num'] >= 1 ? 'completed' : ''; ?>">
            <div class="step-circle"><i class="fa-solid fa-paper-plane"></i></div>
            <div class="step-label">Applied</div>
          </div>
          
          <div class="step-node <?php echo $stepper['num'] >= 2 ? 'completed' : ''; ?>">
            <div class="step-circle"><i class="fa-solid fa-user-check"></i></div>
            <div class="step-label">Shortlisted</div>
          </div>
          
          <?php if ($status === 'Rejected'): ?>
            <div class="step-node rejected">
              <div class="step-circle"><i class="fa-solid fa-circle-xmark"></i></div>
              <div class="step-label">Rejected</div>
            </div>
          <?php else: ?>
            <div class="step-node <?php echo $stepper['num'] >= 3 ? 'completed' : ''; ?>">
              <div class="step-circle"><i class="fa-solid fa-circle-check"></i></div>
              <div class="step-label">Selected</div>
            </div>
          <?php endif; ?>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<!-- Section 2: Eligible Drives List -->
<h2 style="margin-bottom: 1.25rem;"><i class="fa-solid fa-briefcase" style="color: var(--color-accent);"></i> Eligible Placement Drives</h2>

<?php
$notAppliedDrives = array_filter($eligibleDrives, function($d) {
    return empty($d['app_status']);
});

if (empty($notAppliedDrives)):
?>
  <div class="card" style="text-align: center; padding: 2.5rem;">
    <p style="color: var(--text-muted);">No new eligible recruitment drives are active right now. Check back later!</p>
  </div>
<?php else: ?>
  <div class="grid-2">
    <?php foreach ($notAppliedDrives as $drive): ?>
      <div class="card">
        <h3 class="card-title" style="margin-bottom: 0.25rem;"><?php echo sanitize($drive['title']); ?></h3>
        <p style="color: var(--text-muted); font-size: 0.85rem; margin-bottom: 1rem;">
          <i class="fa-solid fa-building"></i> <?php echo sanitize($drive['company_name']); ?>
        </p>
        
        <div style="margin-bottom: 1.25rem; font-size: 0.9rem; color: var(--text-secondary); display: flex; flex-direction: column; gap: 0.25rem;">
          <div><i class="fa-solid fa-money-bill-wave" style="width: 20px;"></i> <strong>Package:</strong> <?php echo sanitize($drive['package_range']); ?></div>
          <div><i class="fa-solid fa-clipboard-check" style="width: 20px;"></i> <strong>Cutoff CGPA:</strong> <?php echo number_format($drive['eligibility_cgpa'], 2); ?></div>
          <div><i class="fa-solid fa-calendar-day" style="width: 20px;"></i> <strong>Test Date:</strong> <?php echo $drive['test_date'] ? date('M d, Y h:i A', strtotime($drive['test_date'])) : 'TBD'; ?></div>
        </div>
        
        <div style="display: flex; gap: 0.75rem;">
          <a href="drive_details.php?id=<?php echo $drive['id']; ?>" class="btn btn-primary btn-sm" style="flex: 1;">
            Check Eligibility & Apply
          </a>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<?php
require_once '../includes/footer.php';
?>
