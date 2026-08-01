<?php
// Notifications API Endpoint

header('Content-Type: application/json');
require_once __DIR__ . '/../config/db.php';

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$user_id = $_SESSION['user_id'] ?? null;

if (!$user_id) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// 1. Handle Mark All as Read (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['action']) && $_GET['action'] === 'mark_all_read') {
    try {
        $stmt = $pdo->prepare("
            UPDATE notifications 
            SET read_status = 1 
            WHERE user_id = :user_id OR user_id IS NULL
        ");
        $stmt->execute([':user_id' => $user_id]);
        
        echo json_encode(['success' => true]);
        exit;
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
        exit;
    }
}

// 2. Fetch Notifications (GET)
try {
    // Fetch notifications matching this user, or global broadcasts (user_id IS NULL)
    // Order by created_at DESC, limit to latest 10
    $stmt = $pdo->prepare("
        SELECT id, message, read_status, created_at 
        FROM notifications 
        WHERE user_id = :user_id OR user_id IS NULL
        ORDER BY created_at DESC 
        LIMIT 10
    ");
    $stmt->execute([':user_id' => $user_id]);
    $notifications = $stmt->fetchAll();
    
    // Count unread (read_status = 0)
    $stmtUnread = $pdo->prepare("
        SELECT COUNT(*) as unread_count 
        FROM notifications 
        WHERE (user_id = :user_id OR user_id IS NULL) AND read_status = 0
    ");
    $stmtUnread->execute([':user_id' => $user_id]);
    $unread_count = $stmtUnread->fetch()['unread_count'] ?? 0;
    
    echo json_encode([
        'success' => true,
        'unread_count' => intval($unread_count),
        'notifications' => $notifications
    ]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
