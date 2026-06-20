<?php
// ============================================================
//  api/hod/approve_reject.php
//  POST /api/hod/approve_reject
//  Body: { "req_id": 5, "action": "approve" | "reject",
//          "rejection_reason": "..." }
//
//  HOD approves → status = 'hod_approved'
//                 proof_deadline set to today + 3 days
//                 student gets provisional approval notification
//  HOD rejects  → status = 'rejected', student notified
// ============================================================

require_once '../../config/db.php';
require_once '../../config/xmpp.php';

authCheck(['hod']);

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

$hod_id = $_SESSION['user_id'];
$db     = getDB();

// ---- Verify request is mentor_approved and belongs to HOD's dept
$stmt = $db->prepare(
    "SELECT lr.req_code, lr.leave_end_date,
            s.student_id, s.name AS student_name, s.email AS student_email
     FROM   leave_requests lr
     JOIN   students s ON lr.student_id = s.student_id
     WHERE  lr.req_id = ? AND lr.hod_id = ? AND lr.status = 'mentor_approved'
     LIMIT 1"
);
$stmt->bind_param('ii', $req_id, $hod_id);
$stmt->execute();
$req = $stmt->get_result()->fetch_assoc();

if (!$req) {
    response(false, 'Request not found or not mentor-approved yet');
}

if ($action === 'approve') {
    // Proof deadline = event end date + PROOF_DEADLINE_DAYS
    $deadline = date('Y-m-d', strtotime($req['leave_end_date'] . ' +' . PROOF_DEADLINE_DAYS . ' days'));

    $stmt = $db->prepare(
        "UPDATE leave_requests
         SET status='hod_approved', proof_deadline=?
         WHERE req_id=?"
    );
    $stmt->bind_param('si', $deadline, $req_id);
    $stmt->execute();

    // Notify student — provisional approval
    notifyStudentHODApproved(
        $req['student_id'],
        $req['student_email'],
        $req['req_code'],
        $deadline,
        $req_id
    );

    response(true, 'Leave approved. Student notified with proof deadline.', [
        'req_id'        => $req_id,
        'status'        => 'hod_approved',
        'proof_deadline'=> $deadline
    ]);

} else {
    $stmt = $db->prepare(
        "UPDATE leave_requests
         SET status='rejected', rejection_reason=?, rejected_by='hod'
         WHERE req_id=?"
    );
    $stmt->bind_param('si', $reason, $req_id);
    $stmt->execute();

    notifyStudentRejected(
        $req['student_id'], $req['student_email'],
        $req['req_code'], $reason, 'HOD', $req_id
    );

    response(true, 'Request rejected. Student notified.', ['status' => 'rejected']);
}

$db->close();
?>
