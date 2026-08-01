<?php
// Admin Export Student Placement Data to CSV

require_once '../includes/auth.php';
checkRole(['admin']);
require_once '../config/db.php';
require_once '../includes/audit.php';

try {
    // 1. Fetch complete student placement applications dataset
    $stmt = $pdo->query("
        SELECT 
            u.name as student_name, 
            u.email as student_email, 
            s.department, 
            s.cgpa, 
            s.backlog_count,
            IFNULL(c.name, 'N/A') as company_name, 
            IFNULL(d.title, 'N/A') as drive_title, 
            IFNULL(a.status, 'No Applications') as placement_status, 
            IFNULL(a.match_score, 0) as match_score, 
            IFNULL(a.applied_at, 'N/A') as applied_date
        FROM students s
        JOIN users u ON s.user_id = u.id
        LEFT JOIN applications a ON s.user_id = a.student_id
        LEFT JOIN drives d ON a.drive_id = d.id
        LEFT JOIN companies c ON d.company_id = c.id
        ORDER BY s.department ASC, u.name ASC
    ");
    $data = $stmt->fetchAll();
    
    // 2. Set response headers for download
    $filename = "campus_connect_placements_" . date('Y-m-d_H-i-s') . ".csv";
    
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    // 3. Open output stream
    $output = fopen('php://output', 'w');
    
    // 4. Output column headers
    fputcsv($output, [
        'Student Name', 
        'Email', 
        'Department', 
        'CGPA', 
        'Backlogs', 
        'Recruiter Company', 
        'Drive Title', 
        'Placement Status', 
        'AI Match Score (%)', 
        'Applied Date'
    ]);
    
    // 5. Output rows
    foreach ($data as $row) {
        fputcsv($output, [
            $row['student_name'],
            $row['student_email'],
            $row['department'],
            $row['cgpa'],
            $row['backlog_count'],
            $row['company_name'],
            $row['drive_title'],
            $row['placement_status'],
            $row['match_score'] > 0 ? $row['match_score'] . '%' : 'N/A',
            $row['applied_date']
        ]);
    }
    
    fclose($output);
    
    // Log audit log
    logAudit('CSV Export Generated', "File: $filename (" . count($data) . " rows exported)");
    exit;
} catch (PDOException $e) {
    die("Export failed: " . $e->getMessage());
}
?>
