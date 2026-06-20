<?php
// ============================================================
//  api/auth/notifications.php
//  GET  /api/auth/notifications       → fetch all
//  POST /api/auth/notifications       → mark as read
//  Body (POST): { "notif_id": 5 }  or  { "mark_all": true }
// ============================================================

require_once '../../config/db.php';
authCheck(['student', 'mentor', 'hod']);

$user_id   = $_SESSION['user_id'];
$user_type = $_SESSION['user_type'];
$db        = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {

    $stmt = $db->prepare(
        "SELECT * FROM notifications
         WHERE  user_type = ? AND user_id = ?
         ORDER  BY created_at DESC
         LIMIT  50"
    );
    $stmt->bind_param('si', $user_type, $user_id);
    $stmt->execute();
    $notifications = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    $unread = array_filter($notifications, fn($n) => !$n['is_read']);

    $db->close();
    response(true, 'Notifications fetched', [
        'notifications' => $notifications,
        'unread_count'  => count($unread)
    ]);

} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $input = json_decode(file_get_contents('php://input'), true);

    if (!empty($input['mark_all'])) {
        $stmt = $db->prepare(
            "UPDATE notifications SET is_read=1 WHERE user_type=? AND user_id=?"
        );
        $stmt->bind_param('si', $user_type, $user_id);
        $stmt->execute();
        response(true, 'All notifications marked as read');
    } elseif (!empty($input['notif_id'])) {
        $notif_id = intval($input['notif_id']);
        $db->query("UPDATE notifications SET is_read=1 WHERE notif_id={$notif_id} AND user_id={$user_id}");
        response(true, 'Notification marked as read');
    }
}

$db->close();
?>
