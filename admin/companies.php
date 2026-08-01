<?php
// Admin Companies Management & Recruiter Contacts

$page_title = "Company Management - Campus Connect";
$active_nav = "companies";
require_once '../includes/auth.php';
checkRole(['admin']);
require_once '../includes/header.php';
require_once '../includes/audit.php';

$action = isset($_GET['action']) ? $_GET['action'] : 'list';
$company_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$error = '';

// 1. Process Create/Update Company Form
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($action === 'new' || $action === 'edit')) {
    // Verify CSRF
    $token = $_POST['csrf_token'] ?? '';
    if (!verifyCsrfToken($token)) {
        $_SESSION['error_message'] = "Invalid CSRF session token.";
        header("Location: companies.php");
        exit;
    }
    
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $package_range = trim($_POST['package_range'] ?? '');
    $contact_name = trim($_POST['contact_name'] ?? '');
    $contact_email = trim($_POST['contact_email'] ?? '');
    
    if (empty($name)) {
        $error = "Company Name cannot be empty.";
    } else {
        try {
            if ($action === 'new') {
                $stmt = $pdo->prepare("
                    INSERT INTO companies (name, description, package_range, contact_name, contact_email) 
                    VALUES (:name, :description, :package_range, :contact_name, :contact_email)
                ");
                $stmt->execute([
                    ':name' => $name,
                    ':description' => $description,
                    ':package_range' => $package_range,
                    ':contact_name' => $contact_name,
                    ':contact_email' => $contact_email
                ]);
                $new_id = $pdo->lastInsertId();
                
                logAudit('Company Added', "Created Company ID: $new_id ($name)");
                $_SESSION['success_message'] = "Company profile added successfully!";
            } else {
                // Edit
                $stmt = $pdo->prepare("
                    UPDATE companies 
                    SET name = :name, description = :description, package_range = :package_range, 
                        contact_name = :contact_name, contact_email = :contact_email
                    WHERE id = :id
                ");
                $stmt->execute([
                    ':name' => $name,
                    ':description' => $description,
                    ':package_range' => $package_range,
                    ':contact_name' => $contact_name,
                    ':contact_email' => $contact_email,
                    ':id' => $company_id
                ]);
                
                logAudit('Company Edited', "Modified Company ID: $company_id ($name)");
                $_SESSION['success_message'] = "Company profile updated successfully!";
            }
            header("Location: companies.php");
            exit;
        } catch (PDOException $e) {
            $error = "Database error: " . $e->getMessage();
        }
    }
}

// 2. Process Delete Action (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'delete' && $company_id > 0) {
    // Verify CSRF
    $token = $_POST['csrf_token'] ?? '';
    if (!verifyCsrfToken($token)) {
        $_SESSION['error_message'] = "Invalid CSRF session token.";
        header("Location: companies.php");
        exit;
    }
    
    try {
        // Fetch company name for audit trail
        $stmtName = $pdo->prepare("SELECT name FROM companies WHERE id = :id");
        $stmtName->execute([':id' => $company_id]);
        $compName = $stmtName->fetchColumn();
        
        if ($compName) {
            $stmtDel = $pdo->prepare("DELETE FROM companies WHERE id = :id");
            $stmtDel->execute([':id' => $company_id]);
            
            logAudit('Company Deleted', "Deleted Company ID: $company_id ($compName)");
            $_SESSION['success_message'] = "Company '$compName' profile deleted successfully.";
        }
    } catch (PDOException $e) {
        $_SESSION['error_message'] = "Failed to delete company: " . $e->getMessage();
    }
    header("Location: companies.php");
    exit;
}

// Fetch edit data if needed
$company_data = null;
if ($action === 'edit' && $company_id > 0) {
    $stmtEdit = $pdo->prepare("SELECT * FROM companies WHERE id = :id");
    $stmtEdit->execute([':id' => $company_id]);
    $company_data = $stmtEdit->fetch();
    if (!$company_data) {
        $_SESSION['error_message'] = "Company not found.";
        header("Location: companies.php");
        exit;
    }
}

$csrf_token = getCsrfToken();
?>

<!-- Form: Add/Edit Company -->
<?php if ($action === 'new' || $action === 'edit'): ?>
  <div class="page-title-area">
    <div>
      <h1 class="page-title"><?php echo $action === 'new' ? 'Add Recruiting Company' : 'Edit Company Details'; ?></h1>
      <p class="page-subtitle">Configure descriptions, recruiter contacts, and package standards.</p>
    </div>
    <div>
      <a href="companies.php" class="btn btn-secondary btn-sm">
        <i class="fa-solid fa-arrow-left"></i> Cancel and Return
      </a>
    </div>
  </div>

  <?php if (!empty($error)): ?>
    <div class="alert alert-error">
      <i class="fa-solid fa-circle-exclamation"></i> <?php echo sanitize($error); ?>
    </div>
  <?php endif; ?>

  <div class="card" style="max-width: 700px; margin: 0 auto;">
    <form action="companies.php?action=<?php echo $action; ?>&id=<?php echo $company_id; ?>" method="POST">
      <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
      
      <div class="form-group">
        <label for="name" class="form-label">Company Name *</label>
        <input type="text" id="name" name="name" class="form-control" placeholder="e.g. Google LLC" required value="<?php 
          echo sanitize($company_data['name'] ?? $_POST['name'] ?? ''); 
        ?>">
      </div>
      
      <div class="form-group">
        <label for="description" class="form-label">Company Description</label>
        <textarea id="description" name="description" class="form-control" rows="4" placeholder="Brief outline of company background, services, or expectations..."><?php 
          echo sanitize($company_data['description'] ?? $_POST['description'] ?? ''); 
        ?></textarea>
      </div>

      <div class="form-group">
        <label for="package_range" class="form-label">Average Package / CTC Range (LPA)</label>
        <input type="text" id="package_range" name="package_range" class="form-control" placeholder="e.g. 10 - 15 LPA or 8 LPA" value="<?php 
          echo sanitize($company_data['package_range'] ?? $_POST['package_range'] ?? ''); 
        ?>">
      </div>
      
      <h3 style="font-size: 1.05rem; font-weight: 600; margin-bottom: 1rem; border-bottom: 1px solid var(--border-glass); padding-bottom: 0.5rem; color: var(--color-accent); margin-top: 1.5rem;">
        <i class="fa-solid fa-address-card"></i> Recruiter Contact Details
      </h3>

      <div class="grid-2" style="gap: 1.5rem; margin-bottom: 1.5rem;">
        <div class="form-group" style="margin-bottom: 0;">
          <label for="contact_name" class="form-label">Contact Person Name</label>
          <input type="text" id="contact_name" name="contact_name" class="form-control" placeholder="e.g. Sundar Pichai" value="<?php 
            echo sanitize($company_data['contact_name'] ?? $_POST['contact_name'] ?? ''); 
          ?>">
        </div>
        
        <div class="form-group" style="margin-bottom: 0;">
          <label for="contact_email" class="form-label">Contact Email Address</label>
          <input type="email" id="contact_email" name="contact_email" class="form-control" placeholder="recruiter@company.com" value="<?php 
            echo sanitize($company_data['contact_email'] ?? $_POST['contact_email'] ?? ''); 
          ?>">
        </div>
      </div>
      
      <button type="submit" class="btn btn-primary" style="width: 100%;">
        <i class="fa-solid fa-floppy-disk"></i> Save Company Profile
      </button>
    </form>
  </div>

<!-- Action: Companies List (Default) -->
<?php else: ?>
  <div class="page-title-area">
    <div>
      <h1 class="page-title">Manage Registered Companies</h1>
      <p class="page-subtitle">Add recruiting corporations and manage recruiter contacts.</p>
    </div>
    <div>
      <a href="companies.php?action=new" class="btn btn-primary btn-sm">
        <i class="fa-solid fa-plus"></i> Register New Company
      </a>
    </div>
  </div>

  <?php
  // Query all companies and drive history counts
  try {
      $stmtList = $pdo->query("
          SELECT c.*, 
                 (SELECT COUNT(*) FROM drives WHERE company_id = c.id) as drives_count,
                 (SELECT MAX(created_at) FROM drives WHERE company_id = c.id) as last_visit
          FROM companies c
          ORDER BY c.name ASC
      ");
      $companies_list = $stmtList->fetchAll();
  } catch (PDOException $e) {
      echo '<div class="alert alert-error">Failed to query companies: ' . sanitize($e->getMessage()) . '</div>';
      $companies_list = [];
  }
  ?>

  <?php if (empty($companies_list)): ?>
    <div class="card" style="text-align: center; padding: 3rem;">
      <i class="fa-solid fa-building" style="font-size: 3rem; color: var(--text-muted); margin-bottom: 1rem;"></i>
      <h3>No companies registered</h3>
      <p style="color: var(--text-muted); margin-top: 0.5rem;">Click the button above to register your first corporate recruiter.</p>
    </div>
  <?php else: ?>
    <div class="table-container">
      <table>
        <thead>
          <tr>
            <th>Company Profile</th>
            <th>Compensation Level</th>
            <th>Primary Recruiter Contact</th>
            <th>Placement Visits</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($companies_list as $comp): ?>
            <tr>
              <td>
                <strong style="font-size: 1rem; color: var(--text-primary);"><?php echo sanitize($comp['name']); ?></strong>
                <div style="font-size: 0.8rem; color: var(--text-muted); max-width: 300px; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; margin-top: 0.25rem;">
                  <?php echo sanitize($comp['description'] ?: 'No details.'); ?>
                </div>
              </td>
              <td><strong><?php echo sanitize($comp['package_range'] ?: 'TBD'); ?></strong></td>
              <td>
                <?php if (!empty($comp['contact_name'])): ?>
                  <div style="font-size: 0.85rem; font-weight: 500; color: var(--text-primary);"><?php echo sanitize($comp['contact_name']); ?></div>
                  <div style="font-size: 0.75rem; color: var(--text-muted);"><i class="fa-solid fa-envelope"></i> <?php echo sanitize($comp['contact_email']); ?></div>
                <?php else: ?>
                  <span style="font-size: 0.85rem; color: var(--text-muted);">None Listed</span>
                <?php endif; ?>
              </td>
              <td>
                <span class="badge badge-applied" style="font-size: 0.75rem;">
                  <?php echo intval($comp['drives_count']); ?> Drives Created
                </span>
                <?php if ($comp['last_visit']): ?>
                  <div style="font-size: 0.7rem; color: var(--text-muted); margin-top: 0.25rem;">
                    Last: <?php echo date('M d, Y', strtotime($comp['last_visit'])); ?>
                  </div>
                <?php endif; ?>
              </td>
              <td>
                <div style="display: flex; gap: 0.4rem;">
                  <a href="companies.php?action=edit&id=<?php echo $comp['id']; ?>" class="btn btn-secondary btn-sm" title="Edit Company Details" style="padding: 0.3rem 0.5rem;">
                    <i class="fa-solid fa-pen"></i>
                  </a>
                  
                  <form action="companies.php?action=delete&id=<?php echo $comp['id']; ?>" method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this company? All associated drives and student applications will be deleted permanently.');">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    <button type="submit" class="btn btn-secondary btn-sm" title="Delete Profile" style="padding: 0.3rem 0.5rem; color: var(--color-danger); border-color: rgba(239,68,68,0.2);">
                      <i class="fa-solid fa-trash-can"></i>
                    </button>
                  </form>
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
