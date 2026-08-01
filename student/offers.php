<?php
// Student Offer Letter Access

$page_title = "My Offer Letters - Campus Connect";
$active_nav = "offers";
require_once '../includes/auth.php';
checkRole(['student']);
require_once '../includes/header.php';

$student_id = $_SESSION['user_id'];

// Query offer letters for the student
try {
    $stmtOffers = $pdo->prepare("
        SELECT ol.id, ol.file_path, ol.uploaded_at, d.title as drive_title, c.name as company_name, u.name as uploader_name
        FROM offer_letters ol
        JOIN drives d ON ol.drive_id = d.id
        JOIN companies c ON d.company_id = c.id
        LEFT JOIN users u ON ol.uploaded_by = u.id
        WHERE ol.student_id = :student_id
        ORDER BY ol.uploaded_at DESC
    ");
    $stmtOffers->execute([':student_id' => $student_id]);
    $offers = $stmtOffers->fetchAll();
} catch (PDOException $e) {
    echo '<div class="alert alert-error">Failed to query offer letters: ' . sanitize($e->getMessage()) . '</div>';
    $offers = [];
}
?>

<div class="page-title-area">
  <div>
    <h1 class="page-title">Offer Letters</h1>
    <p class="page-subtitle">View and download your secured placement offer letters.</p>
  </div>
  <div>
    <a href="dashboard.php" class="btn btn-secondary btn-sm">
      <i class="fa-solid fa-arrow-left"></i> Back to Dashboard
    </a>
  </div>
</div>

<?php if (empty($offers)): ?>
  <div class="card" style="text-align: center; padding: 3rem;">
    <i class="fa-solid fa-file-signature" style="font-size: 3rem; color: var(--text-muted); margin-bottom: 1rem;"></i>
    <h3>No offer letters available</h3>
    <p style="color: var(--text-muted); font-size: 0.95rem; margin-top: 0.5rem; max-width: 500px; margin-left: auto; margin-right: auto;">
      Once you are selected in a placement drive, the TPO Office will verify and upload your digital offer letter here. Keep working hard!
    </p>
  </div>
<?php else: ?>
  <div class="table-container">
    <table>
      <thead>
        <tr>
          <th>Company</th>
          <th>Designation</th>
          <th>Uploaded By</th>
          <th>Release Date</th>
          <th>Action</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($offers as $offer): ?>
          <tr>
            <td><strong><?php echo sanitize($offer['company_name']); ?></strong></td>
            <td><?php echo sanitize($offer['drive_title']); ?></td>
            <td><?php echo sanitize($offer['uploader_name'] ?: 'Placement Cell'); ?></td>
            <td><?php echo date('M d, Y', strtotime($offer['uploaded_at'])); ?></td>
            <td>
              <a href="../<?php echo sanitize($offer['file_path']); ?>" target="_blank" class="btn btn-success btn-sm">
                <i class="fa-solid fa-file-arrow-down"></i> View / Download PDF
              </a>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>

<?php
require_once '../includes/footer.php';
?>
