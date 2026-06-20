<?php
// ============================================================
//  api/hod/verify_proof.php
//  POST /api/hod/verify_proof
//  Body: { "req_id": 5, "action": "verify" | "reject" }
//
//  This is the FINAL step in the workflow:
//  verify → marks attendance, notifies each faculty, notifies student
//  reject → status = 'rejected', student notified
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

if (!$req_id || !in_array($action, ['verify', 'reject'])) {
    response(false, 'req_id and valid action are required');
}

$hod_id = $_SESSION['user_id'];
$db     = getDB();

// ---- Get request details -----------------------------------
$stmt = $db->prepare(
    "SELECT lr.req_code, lr.status,
            s.student_id, s.name AS student_name, s.email AS student_email, s.usn
     FROM   leave_requests lr
     JOIN   students s ON lr.student_id = s.student_id
     WHERE  lr.req_id = ? AND lr.hod_id = ? AND lr.status = 'mentor_verified'
     LIMIT 1"
);
$stmt->bind_param('ii', $req_id, $hod_id);
$stmt->execute();
$req = $stmt->get_result()->fetch_assoc();

if (!$req) {
    response(false, 'Request not found or mentor has not verified proof yet');
}

if ($action === 'verify') {

    // ---- Mark HOD proof verified ---------------------------
    $upd1 = $db->prepare("UPDATE proof_documents SET hod_verified=1 WHERE req_id=?");
    $upd1->bind_param('i', $req_id);
    $upd1->execute();

    $upd2 = $db->prepare("UPDATE leave_requests SET status='hod_verified' WHERE req_id=?");
    $upd2->bind_param('i', $req_id);
    $upd2->execute();

    // ---- Get all subjects to notify faculty ----------------
    $subj_stmt = $db->prepare(
        "SELECT subject_name, faculty_email, class_date
         FROM leave_subjects WHERE req_id=?"
    );
    $subj_stmt->bind_param('i', $req_id);
    $subj_stmt->execute();
    $subjects      = $subj_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $classes_count = count($subjects);

    // ---- Notify each faculty member (email) ----------------
    foreach ($subjects as $subj) {
        $msg = "Dear Faculty,\n\n"
             . "Please mark {$req['student_name']} (USN: {$req['usn']}) as PRESENT for "
             . "{$subj['subject_name']} on {$subj['class_date']}.\n\n"
             . "This is based on an approved leave request (Ref: {$req['req_code']}), "
             . "which has been verified and approved by the Mentor and HOD.\n\n"
             . "Regards,\nSmartAttend Portal";

        sendXMPP($subj['faculty_email'], $msg, $req_id,
                 "Attendance Update - {$req['req_code']} - Mark {$req['student_name']} Present");

        // Mark subject as notified
        $notif_stmt = $db->prepare(
            "UPDATE leave_subjects SET notified=1
             WHERE req_id=? AND faculty_email=? AND class_date=?"
        );
        $notif_stmt->bind_param('iss', $req_id, $subj['faculty_email'], $subj['class_date']);
        $notif_stmt->execute();
    }

    // ---- Update final status to attendance_updated ---------
    $upd3 = $db->prepare(
        "UPDATE leave_requests SET status='attendance_updated' WHERE req_id=?"
    );
    $upd3->bind_param('i', $req_id);
    $upd3->execute();

    // ---- Notify student — attendance updated ----------------
    notifyStudentAttendanceUpdated(
        $req['student_id'],
        $req['student_email'],
        $req['req_code'],
        $classes_count,
        $req_id
    );

    $db->close();
    response(true, 'Proof verified. Faculty notified. Attendance updated.', [
        'req_id'         => $req_id,
        'status'         => 'attendance_updated',
        'classes_updated'=> $classes_count
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
        $req['req_code'], $reason, 'HOD (proof rejected)', $req_id
    );

    $db->close();
    response(true, 'Proof rejected. Student notified.', ['status' => 'rejected']);
}
?>
