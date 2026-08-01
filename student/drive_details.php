<?php
// Student Drive Details & Eligibility Checking

$page_title = "Drive Details - Campus Connect";
$active_nav = "dashboard";
require_once '../includes/auth.php';
checkRole(['student']);
require_once '../includes/header.php';
require_once '../includes/audit.php';

$student_id = $_SESSION['user_id'];
$drive_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Fetch Student academic details
$stmtStudent = $pdo->prepare("SELECT * FROM students WHERE user_id = :user_id");
$stmtStudent->execute([':user_id' => $student_id]);
$student = $stmtStudent->fetch();

if (!$student) {
    $_SESSION['error_message'] = "Please complete your academic profile first.";
    header("Location: dashboard.php");
    exit;
}

// Fetch Drive and Company details
$stmtDrive = $pdo->prepare("
    SELECT d.*, c.name as company_name, c.description as company_description, c.package_range
    FROM drives d
    JOIN companies c ON d.company_id = c.id
    WHERE d.id = :drive_id
");
$stmtDrive->execute([':drive_id' => $drive_id]);
$drive = $stmtDrive->fetch();

if (!$drive) {
    $_SESSION['error_message'] = "Drive not found.";
    header("Location: dashboard.php");
    exit;
}

// Server-side Eligibility Verification
$cgpa = floatval($student['cgpa']);
$backlogs = intval($student['backlog_count']);
$dept = $student['department'];

$elig_cgpa = floatval($drive['eligibility_cgpa']);
$elig_backlogs = intval($drive['eligibility_max_backlogs']);
$elig_branches = strtolower($drive['eligibility_branch']);

// Parse branch lists
$branch_list = array_map('trim', explode(',', $elig_branches));
$branch_match = ($elig_branches === 'all' || in_array(strtolower($dept), $branch_list));
$cgpa_match = ($cgpa >= $elig_cgpa);
$backlog_match = ($backlogs <= $elig_backlogs);

$is_eligible = ($branch_match && $cgpa_match && $backlog_match);

// Critical rule: do NOT display ineligible drives at all. If they manually type the URL, block them.
if (!$is_eligible) {
    $_SESSION['error_message'] = "You are not eligible to view or apply to that drive based on CGPA, Backlogs, or Department cutoff rules.";
    header("Location: dashboard.php");
    exit;
}

// Fetch existing application status
$stmtApp = $pdo->prepare("SELECT * FROM applications WHERE student_id = :student_id AND drive_id = :drive_id");
$stmtApp->execute([':student_id' => $student_id, ':drive_id' => $drive_id]);
$application = $stmtApp->fetch();
$has_applied = ($application !== false);

// Clash Detector
// Check if the test date or interview date overlaps with any OTHER drive the student has applied to.
$clash_warning = '';
if (!$has_applied && !empty($drive['test_date'])) {
    try {
        $stmtClash = $pdo->prepare("
            SELECT d.title, d.test_date, d.interview_date, c.name as company_name
            FROM applications a
            JOIN drives d ON a.drive_id = d.id
            JOIN companies c ON d.company_id = c.id
            WHERE a.student_id = :student_id
              AND (
                -- Check test date overlap (same day and hour)
                (d.test_date IS NOT NULL AND ABS(TIMESTAMPDIFF(MINUTE, d.test_date, :test_date)) < 120)
                OR 
                -- Check interview date overlap
                (d.interview_date IS NOT NULL AND ABS(TIMESTAMPDIFF(MINUTE, d.interview_date, :interview_date)) < 120)
              )
        ");
        $stmtClash->execute([
            ':student_id' => $student_id,
            ':test_date' => $drive['test_date'],
            ':interview_date' => $drive['interview_date']
        ]);
        $clashes = $stmtClash->fetchAll();
        
        if (!empty($clashes)) {
            $clash_items = [];
            foreach ($clashes as $c) {
                $clash_items[] = sanitize($c['company_name'] . " - " . $c['title']);
            }
            $clash_warning = "<strong>Schedule Overlap Warning!</strong> The test/interview schedule for this drive conflicts or is close to another drive you applied for (" . implode(', ', $clash_items) . "). Please confirm you can manage both times before applying.";
        }
    } catch (PDOException $e) {
        // Log query error and continue
    }
}

// Process Application Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF
    $token = $_POST['csrf_token'] ?? '';
    if (!verifyCsrfToken($token)) {
        $_SESSION['error_message'] = "Invalid CSRF session token. Please try again.";
        header("Location: drive_details.php?id=" . $drive_id);
        exit;
    }
    
    // Double check resume path exists
    if (empty($student['resume_path'])) {
        $_SESSION['error_message'] = "You must upload your resume in PDF format before applying.";
        header("Location: drive_details.php?id=" . $drive_id);
        exit;
    }
    
    if ($has_applied) {
        $_SESSION['error_message'] = "You have already applied to this drive.";
        header("Location: drive_details.php?id=" . $drive_id);
        exit;
    }
    
    // Fetch cached Gemini score if posted
    $match_score = isset($_POST['match_score_val']) ? intval($_POST['match_score_val']) : 0;
    $missing_skills = isset($_POST['missing_skills_val']) ? trim($_POST['missing_skills_val']) : '';
    
    try {
        $stmtApply = $pdo->prepare("
            INSERT INTO applications (student_id, drive_id, status, match_score, match_missing_skills) 
            VALUES (:student_id, :drive_id, 'Applied', :match_score, :missing_skills)
        ");
        $stmtApply->execute([
            ':student_id' => $student_id,
            ':drive_id'   => $drive_id,
            ':match_score'=> $match_score,
            ':missing_skills' => $missing_skills
        ]);
        
        // Log audit log
        logAudit('Drive Application Submitted', 'Drive ID: ' . $drive_id . ' (Score: ' . $match_score . '%)', $student_id);
        
        // Create in-app notification
        $stmtNotif = $pdo->prepare("
            INSERT INTO notifications (user_id, message) 
            VALUES (:user_id, :message)
        ");
        $stmtNotif->execute([
            ':user_id' => $student_id,
            ':message' => "Successfully applied to " . $drive['company_name'] . " - " . $drive['title'] . "."
        ]);
        
        $_SESSION['success_message'] = "Application submitted successfully!";
        header("Location: dashboard.php");
        exit;
    } catch (PDOException $e) {
        $_SESSION['error_message'] = "Database error during application: " . $e->getMessage();
        header("Location: drive_details.php?id=" . $drive_id);
        exit;
    }
}

$csrf_token = getCsrfToken();
?>

<div class="page-title-area">
  <div>
    <h1 class="page-title"><?php echo sanitize($drive['title']); ?></h1>
    <p class="page-subtitle"><i class="fa-solid fa-building"></i> <?php echo sanitize($drive['company_name']); ?></p>
  </div>
  <div>
    <a href="dashboard.php" class="btn btn-secondary btn-sm">
      <i class="fa-solid fa-arrow-left"></i> Back to Dashboard
    </a>
  </div>
</div>

<?php if (!empty($clash_warning)): ?>
  <div class="alert alert-warning">
    <i class="fa-solid fa-circle-exclamation"></i>
    <div><?php echo $clash_warning; ?></div>
  </div>
<?php endif; ?>

<div class="grid-2">
  <!-- Left Side: Drive Details -->
  <div style="display: flex; flex-direction: column; gap: 1.5rem;">
    <!-- Job Description -->
    <div class="card">
      <h2 style="font-size: 1.25rem; font-weight: 600; margin-bottom: 1rem;"><i class="fa-solid fa-file-invoice" style="color: var(--color-primary);"></i> Job Description</h2>
      <div style="white-space: pre-wrap; font-size: 0.95rem; color: var(--text-secondary); line-height: 1.6;">
        <?php echo sanitize($drive['job_description']); ?>
      </div>
    </div>
    
    <!-- About Company -->
    <div class="card">
      <h2 style="font-size: 1.25rem; font-weight: 600; margin-bottom: 1rem;"><i class="fa-solid fa-circle-info" style="color: var(--color-accent);"></i> About the Company</h2>
      <p style="font-size: 0.95rem; color: var(--text-secondary); line-height: 1.6;">
        <?php echo sanitize($drive['company_description'] ?: 'No description provided.'); ?>
      </p>
    </div>
  </div>
  
  <!-- Right Side: Eligibility & AI Match Widget -->
  <div style="display: flex; flex-direction: column; gap: 1.5rem;">
    <!-- Criteria details -->
    <div class="card">
      <h2 style="font-size: 1.25rem; font-weight: 600; margin-bottom: 1rem;"><i class="fa-solid fa-graduation-cap" style="color: var(--color-warning);"></i> Eligibility Check</h2>
      
      <div class="eligibility-info-bar">
        <ul style="list-style: none; display: flex; flex-direction: column; gap: 0.5rem; font-size: 0.9rem;">
          <li>
            <i class="fa-solid <?php echo $cgpa_match ? 'fa-circle-check' : 'fa-circle-xmark'; ?>" style="color: <?php echo $cgpa_match ? 'var(--color-success)' : 'var(--color-danger)'; ?>; width: 20px;"></i>
            <strong>Your CGPA:</strong> <?php echo number_format($cgpa, 2); ?> (Cutoff: <?php echo number_format($elig_cgpa, 2); ?>)
          </li>
          <li>
            <i class="fa-solid <?php echo $backlog_match ? 'fa-circle-check' : 'fa-circle-xmark'; ?>" style="color: <?php echo $backlog_match ? 'var(--color-success)' : 'var(--color-danger)'; ?>; width: 20px;"></i>
            <strong>Your Backlogs:</strong> <?php echo $backlogs; ?> (Max Allowed: <?php echo $elig_backlogs; ?>)
          </li>
          <li>
            <i class="fa-solid <?php echo $branch_match ? 'fa-circle-check' : 'fa-circle-xmark'; ?>" style="color: <?php echo $branch_match ? 'var(--color-success)' : 'var(--color-danger)'; ?>; width: 20px;"></i>
            <strong>Your Branch:</strong> <?php echo sanitize($dept); ?> (Eligible: <?php echo sanitize($drive['eligibility_branch']); ?>)
          </li>
        </ul>
      </div>

      <div style="margin-bottom: 1rem; font-size: 0.9rem; color: var(--text-secondary); display: flex; flex-direction: column; gap: 0.5rem;">
        <div><i class="fa-solid fa-money-bill-wave" style="width: 20px;"></i> <strong>Compensation:</strong> <?php echo sanitize($drive['package_range']); ?></div>
        <div><i class="fa-solid fa-calendar-day" style="width: 20px;"></i> <strong>Aptitude Test:</strong> <?php echo $drive['test_date'] ? date('M d, Y h:i A', strtotime($drive['test_date'])) : 'TBD'; ?></div>
        <div><i class="fa-solid fa-calendar-check" style="width: 20px;"></i> <strong>Interview:</strong> <?php echo $drive['interview_date'] ? date('M d, Y h:i A', strtotime($drive['interview_date'])) : 'TBD'; ?></div>
      </div>
    </div>

    <!-- AI Resume-Drive Match Score (Gemini integration) -->
    <div class="card" id="aiMatchWidget">
      <h2 style="font-size: 1.25rem; font-weight: 600; margin-bottom: 1rem;">
        <i class="fa-solid fa-wand-magic-sparkles" style="color: var(--color-accent);"></i> AI Profile Match
      </h2>
      
      <?php if (empty($student['resume_path'])): ?>
        <p style="color: var(--text-muted); font-size: 0.9rem;">
          Please upload your resume in the profile page to enable AI matching.
        </p>
      <?php else: ?>
        <div class="ai-match-container" id="aiMatchContainer" style="display: none;">
          <div class="ai-match-circle-wrapper">
            <svg class="ai-match-circle" width="80" height="80">
              <circle class="ai-match-circle-bg" cx="40" cy="40" r="36"></circle>
              <circle class="ai-match-circle-fg" id="aiMatchCircleFg" cx="40" cy="40" r="36"></circle>
            </svg>
            <div class="ai-match-percentage" id="aiMatchPercentText">0%</div>
          </div>
          <div class="ai-match-details">
            <div class="ai-match-title" id="aiMatchResultText">Analyzing Profile...</div>
            <div class="ai-match-desc" id="aiMatchSuggestions">Comparing resume text against job keywords.</div>
          </div>
        </div>

        <div id="aiMissingSkillsSection" style="display: none; margin-bottom: 1.5rem;">
          <h4 style="font-size: 0.85rem; font-weight: 600; color: var(--text-secondary); margin-bottom: 0.5rem;">Missing Skills (AI Callouts):</h4>
          <div class="skills-list" id="aiMissingSkillsList"></div>
        </div>

        <div id="aiMatchLoading" style="text-align: center; padding: 1.5rem;">
          <i class="fa-solid fa-spinner fa-spin" style="font-size: 1.75rem; color: var(--color-accent); margin-bottom: 0.5rem;"></i>
          <p style="color: var(--text-muted); font-size: 0.85rem;">Gemini AI is parsing your resume PDF and scanning requirements...</p>
        </div>
      <?php endif; ?>

      <!-- Apply Form Action -->
      <?php if ($has_applied): ?>
        <div style="text-align: center; padding: 1rem; background-color: var(--color-success-light); border: 1px solid var(--color-success); border-radius: var(--radius-md); color: white;">
          <i class="fa-solid fa-circle-check" style="font-size: 1.5rem; margin-bottom: 0.25rem;"></i>
          <div>Applied with AI score of <strong><?php echo intval($application['match_score']); ?>%</strong></div>
          <div style="font-size: 0.8rem; color: var(--text-secondary); margin-top: 0.25rem;">Submitted on: <?php echo date('M d, Y', strtotime($application['applied_at'])); ?></div>
        </div>
      <?php else: ?>
        <form action="drive_details.php?id=<?php echo $drive_id; ?>" method="POST" id="applyForm" style="margin-top: 1rem;">
          <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
          
          <!-- Hidden inputs populated after Gemini loading finishes -->
          <input type="hidden" name="match_score_val" id="matchScoreVal" value="0">
          <input type="hidden" name="missing_skills_val" id="missingSkillsVal" value="">
          
          <button type="submit" class="btn btn-primary" id="applyBtn" style="width: 100%;" <?php echo empty($student['resume_path']) ? 'disabled' : ''; ?>>
            <i class="fa-solid fa-paper-plane"></i> Apply for Drive
          </button>
        </form>
      <?php endif; ?>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
  const driveId = <?php echo $drive_id; ?>;
  const hasResume = <?php echo !empty($student['resume_path']) ? 'true' : 'false'; ?>;
  const hasApplied = <?php echo $has_applied ? 'true' : 'false'; ?>;
  
  if (hasResume && !hasApplied) {
    // Fetch AI match score
    fetch(`../api/match_resume.php?drive_id=${driveId}`)
      .then(response => response.json())
      .then(data => {
        document.getElementById('aiMatchLoading').style.display = 'none';
        
        if (data.success) {
          const score = parseInt(data.match_score);
          
          // Show widget details
          const container = document.getElementById('aiMatchContainer');
          container.style.display = 'flex';
          
          document.getElementById('aiMatchPercentText').textContent = score + '%';
          
          // Animate Circle SVG dashoffset
          // Circumference is 2 * PI * r = 2 * 3.14159 * 36 = 226.19
          const circle = document.getElementById('aiMatchCircleFg');
          const circumference = 226;
          const offset = circumference - (score / 100) * circumference;
          circle.style.strokeDashoffset = offset;
          
          // Change colors based on score
          if (score >= 80) {
            circle.style.stroke = 'var(--color-success)';
            document.getElementById('aiMatchPercentText').style.color = 'var(--color-success)';
          } else if (score >= 50) {
            circle.style.stroke = 'var(--color-warning)';
            document.getElementById('aiMatchPercentText').style.color = 'var(--color-warning)';
          } else {
            circle.style.stroke = 'var(--color-danger)';
            document.getElementById('aiMatchPercentText').style.color = 'var(--color-danger)';
          }

          document.getElementById('aiMatchResultText').textContent = score >= 75 ? 'Strong Match!' : (score >= 50 ? 'Moderate Match' : 'Potential Match');
          document.getElementById('aiMatchSuggestions').textContent = data.suggestions || 'Check missing keywords below.';
          
          // Store in form hidden fields
          document.getElementById('matchScoreVal').value = score;
          
          // Show missing skills callouts
          if (data.missing_skills && data.missing_skills.length > 0) {
            document.getElementById('missingSkillsVal').value = data.missing_skills.join(', ');
            
            const listContainer = document.getElementById('aiMissingSkillsList');
            listContainer.innerHTML = data.missing_skills.map(skill => {
              return `<span class="skill-tag skill-tag-missing"><i class="fa-solid fa-triangle-exclamation"></i> ${escapeHtml(skill)}</span>`;
            }).join('');
            
            document.getElementById('aiMissingSkillsSection').style.display = 'block';
          } else {
            document.getElementById('missingSkillsVal').value = '';
          }
        } else {
          // If Gemini fails (no API key configured or connection timeout), show fail graceful message
          const container = document.getElementById('aiMatchContainer');
          container.style.display = 'flex';
          document.getElementById('aiMatchResultText').textContent = 'Match Pending';
          document.getElementById('aiMatchSuggestions').textContent = data.suggestions || 'Gemini API not configured. You can still apply.';
          
          // Animate default gray circle
          const circle = document.getElementById('aiMatchCircleFg');
          circle.style.stroke = 'var(--text-muted)';
          circle.style.strokeDashoffset = 150; 
          document.getElementById('aiMatchPercentText').textContent = '--';
          document.getElementById('aiMatchPercentText').style.color = 'var(--text-muted)';
        }
      })
      .catch(error => {
        console.error("AI matching fetch failed:", error);
        document.getElementById('aiMatchLoading').style.display = 'none';
      });
  }
  
  function escapeHtml(str) {
    return str.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;");
  }
});
</script>

<?php
require_once '../includes/footer.php';
?>
