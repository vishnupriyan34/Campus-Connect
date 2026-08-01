<?php
// Staff Placement Reports & Analytics Dashboard

$page_title = "Placement Reports & Analytics - Campus Connect";
$active_nav = "analytics";
require_once '../includes/auth.php';
checkRole(['staff']);
require_once '../includes/header.php';

// Fetch all drives for the funnel select filter
try {
    $drivesList = $pdo->query("
        SELECT d.id, d.title, c.name as company_name 
        FROM drives d 
        JOIN companies c ON d.company_id = c.id 
        ORDER BY d.created_at DESC
    ")->fetchAll();
} catch (PDOException $e) {
    $drivesList = [];
}
?>

<div class="page-title-area">
  <div>
    <h1 class="page-title">Placement Analytics & Visualizations</h1>
    <p class="page-subtitle">Interactive reporting charts for student selections, Packages, and pipeline funnels.</p>
  </div>
  <div>
    <a href="dashboard.php" class="btn btn-secondary btn-sm">
      <i class="fa-solid fa-arrow-left"></i> Back to Dashboard
    </a>
  </div>
</div>

<!-- Grid layout for the Chart.js visual panels -->
<div class="charts-grid">
  <!-- Chart 1: Branch-wise Selections -->
  <div class="chart-card">
    <div class="chart-title">
      <span><i class="fa-solid fa-chart-column" style="color: var(--color-primary); margin-right: 0.5rem;"></i> Selections by Department</span>
      <span style="font-size: 0.75rem; color: var(--text-muted);">Unique Placements</span>
    </div>
    <div class="chart-canvas-container">
      <canvas id="branchSelectionsChart"></canvas>
    </div>
  </div>

  <!-- Chart 2: Average CTC Packages -->
  <div class="chart-card">
    <div class="chart-title">
      <span><i class="fa-solid fa-chart-line" style="color: var(--color-accent); margin-right: 0.5rem;"></i> Average Packages (CTC in LPA)</span>
      <span style="font-size: 0.75rem; color: var(--text-muted);">Department Averages</span>
    </div>
    <div class="chart-canvas-container">
      <canvas id="avgPackageChart"></canvas>
    </div>
  </div>

  <!-- Chart 3: Participation Rate -->
  <div class="chart-card">
    <div class="chart-title">
      <span><i class="fa-solid fa-chart-pie" style="color: var(--color-success); margin-right: 0.5rem;"></i> Placement Participation</span>
      <span style="font-size: 0.75rem; color: var(--text-muted);">Applied vs Idle</span>
    </div>
    <div class="chart-canvas-container">
      <canvas id="participationChart"></canvas>
    </div>
  </div>

  <!-- Chart 4: Drive-wise Conversion Funnel -->
  <div class="chart-card">
    <div class="chart-title" style="flex-wrap: wrap; gap: 0.5rem;">
      <span><i class="fa-solid fa-filter" style="color: var(--color-warning); margin-right: 0.5rem;"></i> Drive Conversion Pipeline</span>
      <select id="funnelDriveSelect" class="form-control" style="width: auto; padding: 0.25rem 2rem 0.25rem 0.75rem; font-size: 0.75rem; border-radius: var(--radius-sm); margin: 0; background-position: right 0.5rem center;">
        <?php foreach ($drivesList as $idx => $d): ?>
          <option value="<?php echo $d['id']; ?>" <?php echo $idx === 0 ? 'selected' : ''; ?>>
            <?php echo sanitize($d['company_name'] . " - " . $d['title']); ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="chart-canvas-container">
      <canvas id="driveFunnelChart"></canvas>
    </div>
  </div>
</div>

<!-- Load Chart.js from CDN -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<!-- Load local charts processor -->
<script src="../js/charts.js"></script>

<?php
require_once '../includes/footer.php';
?>
