<?php
// Gemini AI Resume Match Score API

header('Content-Type: application/json');
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/gemini.php';

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$user_id = $_SESSION['user_id'] ?? null;
$role = $_SESSION['role'] ?? '';

if (!$user_id) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$drive_id = isset($_GET['drive_id']) ? intval($_GET['drive_id']) : 0;
// Staff can request score analysis for a specific student, otherwise analyze the logged-in student
$student_id_target = ($role === 'staff' && isset($_GET['student_id'])) ? intval($_GET['student_id']) : $user_id;

if ($drive_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid placement drive ID.']);
    exit;
}

try {
    // 1. Fetch Student profile & resume path
    $stmtS = $pdo->prepare("SELECT resume_path FROM students WHERE user_id = :student_id");
    $stmtS->execute([':student_id' => $student_id_target]);
    $student = $stmtS->fetch();
    
    if (!$student || empty($student['resume_path'])) {
        echo json_encode([
            'success' => false,
            'message' => 'No resume uploaded for this student.',
            'suggestions' => 'Please upload a resume PDF first.'
        ]);
        exit;
    }
    
    $resume_absolute_path = __DIR__ . '/../' . $student['resume_path'];
    
    // 2. Fetch Job Description
    $stmtD = $pdo->prepare("SELECT job_description FROM drives WHERE id = :drive_id");
    $stmtD->execute([':drive_id' => $drive_id]);
    $drive = $stmtD->fetch();
    
    if (!$drive || empty($drive['job_description'])) {
        echo json_encode([
            'success' => false,
            'message' => 'Placement drive details or job description not found.',
            'suggestions' => 'Drive details are incomplete.'
        ]);
        exit;
    }
    
    // 3. Call Gemini AI helper
    $result = getResumeMatchScore($resume_absolute_path, $drive['job_description']);
    
    echo json_encode($result);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Server error: ' . $e->getMessage()
    ]);
}
?>
