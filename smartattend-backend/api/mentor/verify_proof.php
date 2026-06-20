<?php
// ============================================================
//  api/mentor/verify_proof.php
//  POST /api/mentor/verify_proof
//  Body: { "req_id": 5, "action": "verify" | "reject",
//          "rejection_reason": "..." }
//
//  verify → proof.mentor_verified = 1, status = 'mentor_verified'
//           HOD is notified to do final verification
//  reject → status = 'rejected', student is notified
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

if (!$req_id || !in_array($action, ['verify', 'reject'])) {
    response(false, 'req_id and valid action are required');
}

$mentor_id = $_SESSION['user_id'];
$db        = getDB();

$stmt = $db->prepare(
    "SELECT lr.req_code, lr.hod_id,
            s.student_id, s.name AS student_name, s.email AS student_email,
            h.email AS hod_email
     FROM leave_requests lr
     JOIN students s ON lr.student_id = s.student_id
     JOIN hods h ON lr.hod_id = h.hod_id
     WHERE lr.req_id = ? AND lr.mentor_id = ? AND lr.status = 'proof_uploaded'
     LIMIT 1"
);
$stmt->bind_param('ii', $req_id, $mentor_id);
$stmt->execute();
$req = $stmt->get_result()->fetch_assoc();

if (!$req) {
    response(false, 'Request not found or proof not uploaded yet');
}

if ($action === 'verify') {
    // Mark proof as mentor-verified
    $upd1 = $db->prepare("UPDATE proof_documents SET mentor_verified=1 WHERE req_id=?");
    $upd1->bind_param('i', $req_id);
    $upd1->execute();

    $upd2 = $db->prepare("UPDATE leave_requests SET status='mentor_verified' WHERE req_id=?");
    $upd2->bind_param('i', $req_id);
    $upd2->execute();

    // Notify HOD for final verification
    notifyHODMentorApproved(
        $req['hod_id'],
        $req['hod_email'],
        $req['student_name'],
        $req['req_code'] . ' (proof)',
        $req_id
    );

    response(true, 'Proof verified. HOD notified for final check.', [
        'req_id' => $req_id,
        'status' => 'mentor_verified'
    ]);

} else {
    $stmt = $db->prepare(
        "UPDATE leave_requests
         SET status='rejected', rejection_reason=?, rejected_by='mentor'
         WHERE req_id=?"
    );
    $stmt->bind_param('si', $reason, $req_id);
    $stmt->execute();

    notifyStudentRejected(
        $req['student_id'], $req['student_email'],
        $req['req_code'], $reason, 'Mentor (proof rejected)', $req_id
    );

    response(true, 'Proof rejected. Student notified.', ['status' => 'rejected']);
}

$db->close();
?>
