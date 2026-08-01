<?php
// Admin Audit Trail Timeline View

$page_title = "Audit Logs - Campus Connect";
$active_nav = "audit";
require_once '../includes/auth.php';
checkRole(['admin']);
require_once '../includes/header.php';

// Fetch all audit logs
try {
    $stmtLogs = $pdo->query("
        SELECT al.*, u.name as actor_name, u.role as actor_role, u.email as actor_email
        FROM audit_logs al
        LEFT JOIN users u ON al.actor_id = u.id
        ORDER BY al.timestamp DESC
    ");
    $logs = $stmtLogs->fetchAll();
} catch (PDOException $e) {
    echo '<div class="alert alert-error">Failed to query audit logs: ' . sanitize($e->getMessage()) . '</div>';
    $logs = [];
}
?>

<div class="page-title-area">
  <div>
    <h1 class="page-title">System Audit Trail</h1>
    <p class="page-subtitle">Security logging and chronological trace of staff and administrative activities.</p>
  </div>
  <div>
    <a href="dashboard.php" class="btn btn-secondary btn-sm">
      <i class="fa-solid fa-arrow-left"></i> Back to Dashboard
    </a>
  </div>
</div>

<div class="card">
  <h2 style="font-size: 1.25rem; font-weight: 600; margin-bottom: 1.25rem;">
    <i class="fa-solid fa-list-check" style="color: var(--color-primary);"></i> System Logs Timeline (Total: <?php echo count($logs); ?>)
  </h2>
  
  <?php if (empty($logs)): ?>
    <p style="color: var(--text-muted); text-align: center; padding: 3rem;">No activities logged yet.</p>
  <?php else: ?>
    <div class="audit-list">
      <?php foreach ($logs as $log): 
        $actorRole = $log['actor_role'] ?? 'system';
      ?>
        <div class="audit-item <?php echo sanitize($actorRole); ?>" style="margin-bottom: 0.5rem;">
          <div class="audit-content">
            <span class="audit-action" style="font-size: 1.05rem;"><?php echo sanitize($log['action']); ?></span>
            <span class="audit-meta" style="margin-top: 0.35rem; font-size: 0.85rem;">
              Actor: <strong><?php echo sanitize($log['actor_name'] ?: 'System'); ?></strong> 
              <?php if (!empty($log['actor_email'])): ?>
                (<?php echo sanitize($log['actor_email']); ?>)
              <?php endif; ?> 
              &nbsp;&bull;&nbsp; Role: <span class="badge badge-<?php echo $actorRole === 'admin' ? 'selected' : ($actorRole === 'staff' ? 'applied' : 'rejected'); ?>" style="font-size: 0.7rem; padding: 0.1rem 0.4rem;"><?php echo sanitize($actorRole); ?></span>
              &nbsp;&bull;&nbsp; Target: <em><?php echo sanitize($log['target']); ?></em>
            </span>
          </div>
          <div class="audit-timestamp" style="font-weight: 500; color: var(--text-muted);">
            <i class="fa-regular fa-clock"></i> <?php echo date('M d, Y h:i:s A', strtotime($log['timestamp'])); ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<?php
require_once '../includes/footer.php';
?>
