<?php
// System Audit Trail Logger

require_once __DIR__ . '/../config/db.php';

/**
 * Inserts a record into the audit_logs table.
 * 
 * @param string $action The operation performed (e.g., 'Approved CGPA Edit', 'Created Placement Drive')
 * @param string $target The affected entity description (e.g., 'Student ID: 5', 'Drive ID: 22')
 * @param int|null $actorId The user performing the action (defaults to current session user_id)
 * @return bool True if logged successfully, false otherwise
 */
function logAudit($action, $target, $actorId = null) {
    global $pdo;
    
    if ($actorId === null) {
        $actorId = $_SESSION['user_id'] ?? null;
    }
    
    if ($actorId === null) {
        // Can be a registration or system-level action
        $actorId = null;
    }
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO audit_logs (actor_id, action, target) 
            VALUES (:actor_id, :action, :target)
        ");
        return $stmt->execute([
            ':actor_id' => $actorId,
            ':action'   => $action,
            ':target'   => $target
        ]);
    } catch (PDOException $e) {
        // Fail silently in production, or log to error log
        error_log("Failed to write audit log: " . $e->getMessage());
        return false;
    }
}
?>
