<?php
// Placement Analytics JSON API Endpoint

header('Content-Type: application/json');
require_once __DIR__ . '/../config/db.php';

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Access restricted to Staff and Admins
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['staff', 'admin'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$type = $_GET['type'] ?? '';

// Helper function to extract numeric values from package string (e.g. "12 - 25 LPA" -> average 18.5)
function parsePackageToNumeric($rangeString) {
    if (empty($rangeString)) return 0.0;
    
    // Clean string and find numbers
    $clean = strtolower(str_replace('lpa', '', $rangeString));
    preg_match_all('/[0-9.]+(?:\s*-\s*[0-9.]+)?/', $clean, $matches);
    
    if (!empty($matches[0])) {
        $parts = explode('-', str_replace(' ', '', $matches[0][0]));
        if (count($parts) === 2) {
            return (floatval($parts[0]) + floatval($parts[1])) / 2.0;
        }
        return floatval($parts[0]);
    }
    return 0.0;
}

try {
    switch ($type) {
        case 'branch_selections':
            // Selected students by department
            $stmt = $pdo->query("
                SELECT s.department, COUNT(DISTINCT a.student_id) as count
                FROM applications a
                JOIN students s ON a.student_id = s.user_id
                WHERE a.status = 'Selected'
                GROUP BY s.department
                ORDER BY count DESC
            ");
            $rows = $stmt->fetchAll();
            
            $labels = [];
            $data = [];
            foreach ($rows as $r) {
                $labels[] = $r['department'];
                $data[] = intval($r['count']);
            }
            
            // Add default labels if empty
            if (empty($labels)) {
                $labels = ['CSE', 'ECE', 'ME', 'IT', 'CE'];
                $data = [0, 0, 0, 0, 0];
            }
            
            echo json_encode([
                'success' => true,
                'labels' => $labels,
                'data' => $data
            ]);
            break;
            
        case 'average_package':
            // Selected students and their packages grouped by department
            $stmt = $pdo->query("
                SELECT s.department, c.package_range
                FROM applications a
                JOIN students s ON a.student_id = s.user_id
                JOIN drives d ON a.drive_id = d.id
                JOIN companies c ON d.company_id = c.id
                WHERE a.status = 'Selected'
            ");
            $rows = $stmt->fetchAll();
            
            $deptPackages = [];
            foreach ($rows as $r) {
                $dept = $r['department'];
                $pkg = parsePackageToNumeric($r['package_range']);
                if ($pkg > 0) {
                    $deptPackages[$dept][] = $pkg;
                }
            }
            
            $labels = [];
            $data = [];
            foreach ($deptPackages as $dept => $pkgs) {
                $labels[] = $dept;
                $data[] = round(array_sum($pkgs) / count($pkgs), 2);
            }
            
            if (empty($labels)) {
                $labels = ['CSE', 'ECE', 'ME', 'IT', 'CE'];
                $data = [0, 0, 0, 0, 0];
            }
            
            echo json_encode([
                'success' => true,
                'labels' => $labels,
                'data' => $data
            ]);
            break;
            
        case 'participation':
            // Participated (at least 1 application) vs Not Participated
            $totalStudents = $pdo->query("SELECT COUNT(*) FROM students")->fetchColumn() ?: 0;
            $appliedStudents = $pdo->query("SELECT COUNT(DISTINCT student_id) FROM applications")->fetchColumn() ?: 0;
            $unappliedStudents = max(0, $totalStudents - $appliedStudents);
            
            echo json_encode([
                'success' => true,
                'labels' => ['Applied', 'No Applications'],
                'data' => [intval($appliedStudents), intval($unappliedStudents)]
            ]);
            break;
            
        case 'drive_funnel':
            // Funnel stats for specific drive (Applied -> Shortlisted -> Selected)
            $drive_id = isset($_GET['drive_id']) ? intval($_GET['drive_id']) : 0;
            if ($drive_id <= 0) {
                // Fall back to first open drive
                $drive_id = $pdo->query("SELECT id FROM drives ORDER BY created_at DESC LIMIT 1")->fetchColumn() ?: 0;
            }
            
            if ($drive_id <= 0) {
                echo json_encode([
                    'success' => true,
                    'labels' => ['Applied', 'Shortlisted', 'Selected'],
                    'data' => [0, 0, 0]
                ]);
                exit;
            }
            
            $stmtFunnel = $pdo->prepare("
                SELECT 
                    COUNT(CASE WHEN status IN ('Applied', 'Shortlisted', 'Selected') THEN 1 END) as applied,
                    COUNT(CASE WHEN status IN ('Shortlisted', 'Selected') THEN 1 END) as shortlisted,
                    COUNT(CASE WHEN status = 'Selected' THEN 1 END) as selected
                FROM applications
                WHERE drive_id = :drive_id
            ");
            $stmtFunnel->execute([':drive_id' => $drive_id]);
            $funnel = $stmtFunnel->fetch();
            
            echo json_encode([
                'success' => true,
                'labels' => ['Applied', 'Shortlisted', 'Selected'],
                'data' => [
                    intval($funnel['applied'] ?? 0),
                    intval($funnel['shortlisted'] ?? 0),
                    intval($funnel['selected'] ?? 0)
                ]
            ]);
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid analytics type.']);
            break;
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Query error: ' . $e->getMessage()]);
}
?>
