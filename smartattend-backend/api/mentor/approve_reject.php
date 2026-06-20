<?php
// ============================================================
//  api/mentor/approve_reject.php
//  POST /api/mentor/approve_reject
//  Body: { "req_id": 5, "action": "approve" | "reject",
//          "rejection_reason": "..." }
//
//  approve → status becomes 'mentor_approved', HOD is notified
//  reject  → status becomes 'rejected', student is notified
// ============================================================

require_once '../../config/db.php';
require_once '../../config/xmpp.php';

authCheck(['mentor']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    response(false, 'Only POST method allowed');
}

$input  = json_decode(file_get_contents('php://input'), true);
$req_id = intval($input['req_id'] ?? 0);
$action = $input['action'] ?? '';
$reason = trim($input['rejection_reason'] ?? '');

if (!$req_id || !in_array($action, ['approve', 'reject'])) {
    response(false, 'req_id and valid action are required');
}
if ($action === 'reject' && !$reason) {
    response(false, 'Rejection reason is required');
}

$mentor_id = $_SESSION['user_id'];
$db        = getDB();

// ---- Verify request belongs to this mentor -----------------
$stmt = $db->prepare(
    "SELECT lr.req_code, lr.status, lr.hod_id,
            s.student_id, s.name AS student_name, s.email AS student_email,
            h.email AS hod_email
     FROM   leave_requests lr
     JOIN   students    s ON lr.student_id = s.student_id
     JOIN   hods        h ON lr.hod_id     = h.hod_id
     WHERE  lr.req_id   = ? AND lr.mentor_id = ?
     LIMIT 1"
);
$stmt->bind_param('ii', $req_id, $mentor_id);
$stmt->execute();
$req = $stmt->get_result()->fetch_assoc();

if (!$req) {
    response(false, 'Request not found or not assigned to you');
}
if ($req['status'] !== 'pending_mentor') {
    response(false, 'This request is no longer pending. Status: ' . $req['status']);
}

// ---- Perform action ----------------------------------------
if ($action === 'approve') {
    $upd = $db->prepare(
        "UPDATE leave_requests SET status='mentor_approved' WHERE req_id=?"
    );
    $upd->bind_param('i', $req_id);
    $upd->execute();

    // Notify HOD
    notifyHODMentorApproved(
        $req['hod_id'],
        $req['hod_email'],
        $req['student_name'],
        $req['req_code'],
        $req_id
    );

    response(true, 'Request approved. HOD has been notified.', [
        'req_id' => $req_id,
        'status' => 'mentor_approved'
    ]);

} else {
    // Reject
    $stmt = $db->prepare(
        "UPDATE leave_requests
         SET status='rejected', rejection_reason=?, rejected_by='mentor'
         WHERE req_id=?"
    );
    $stmt->bind_param('si', $reason, $req_id);
    $stmt->execute();

    // Notify student
    notifyStudentRejected(
        $req['student_id'],
        $req['student_email'],
        $req['req_code'],
        $reason,
        'Mentor',
        $req_id
    );

    response(true, 'Request rejected. Student has been notified.', [
        'req_id' => $req_id,
        'status' => 'rejected'
    ]);
}

$db->close();
?>
