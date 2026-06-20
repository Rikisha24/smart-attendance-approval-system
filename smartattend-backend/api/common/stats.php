<?php
// ============================================================
//  api/common/stats.php
//  GET /api/common/stats.php
//  Returns dashboard stat-card numbers based on logged-in role.
//  Replaces hardcoded values in the dashboard stat cards.
// ============================================================

require_once '../../config/db.php';
authCheck(['student','mentor','hod']);

$db   = getDB();
$role = $_SESSION['user_type'];
$uid  = $_SESSION['user_id'];

$data = [];

if ($role === 'student') {

    $stmt = $db->prepare("SELECT status FROM leave_requests WHERE student_id=?");
    $stmt->bind_param('i', $uid);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    $total   = count($rows);
    $approved = 0; $pending = 0; $proofReq = 0;

    foreach ($rows as $r) {
        switch ($r['status']) {
            case 'hod_approved':
            case 'mentor_verified':
            case 'hod_verified':
            case 'attendance_updated':
                $approved++;
                break;
            case 'pending_mentor':
            case 'mentor_approved':
                $pending++;
                break;
        }
        if ($r['status'] === 'hod_approved') $proofReq++;
    }

    // Classes protected = subjects under requests that reached attendance_updated
    $stmt2 = $db->prepare(
        "SELECT COUNT(*) AS cnt
         FROM leave_subjects ls
         JOIN leave_requests lr ON ls.req_id = lr.req_id
         WHERE lr.student_id=? AND lr.status='attendance_updated'"
    );
    $stmt2->bind_param('i', $uid);
    $stmt2->execute();
    $classesProtected = (int)($stmt2->get_result()->fetch_assoc()['cnt'] ?? 0);

    $data = [
        'total_requests'     => $total,
        'approved'           => $approved,
        'pending'            => $pending,
        'proof_required'     => $proofReq,
        'classes_protected'  => $classesProtected,
    ];

} elseif ($role === 'mentor') {

    $stmt = $db->prepare("SELECT status FROM leave_requests WHERE mentor_id=?");
    $stmt->bind_param('i', $uid);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    $pendingReview = 0; $approvedTotal = 0; $rejected = 0;
    foreach ($rows as $r) {
        if ($r['status'] === 'pending_mentor') $pendingReview++;
        elseif ($r['status'] === 'rejected') $rejected++;
        else $approvedTotal++; // anything past pending_mentor was mentor-approved at some point
    }

    $stmt2 = $db->prepare(
        "SELECT COUNT(*) AS cnt
         FROM leave_requests lr
         JOIN proof_documents pd ON lr.req_id = pd.req_id
         WHERE lr.mentor_id=? AND pd.mentor_verified=0"
    );
    $stmt2->bind_param('i', $uid);
    $stmt2->execute();
    $proofsToVerify = (int)($stmt2->get_result()->fetch_assoc()['cnt'] ?? 0);

    $data = [
        'pending_reviews'   => $pendingReview,
        'approved_total'    => $approvedTotal,
        'rejected'          => $rejected,
        'proofs_to_verify'  => $proofsToVerify,
    ];

} elseif ($role === 'hod') {

    $stmt = $db->prepare(
        "SELECT lr.status FROM leave_requests lr
         JOIN mentors m ON lr.mentor_id = m.mentor_id
         WHERE m.dept_id = (SELECT dept_id FROM hods WHERE hod_id=?)"
    );
    $stmt->bind_param('i', $uid);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    $awaiting = 0; $approvedTotal = 0; $rejected = 0; $total = count($rows);
    foreach ($rows as $r) {
        if ($r['status'] === 'mentor_approved') $awaiting++;
        elseif ($r['status'] === 'rejected') $rejected++;
        else $approvedTotal++;
    }
    $approvalRate = $total > 0 ? round((($total - $rejected) / $total) * 100) : 0;

    $stmt2 = $db->prepare(
        "SELECT COUNT(*) AS cnt
         FROM leave_requests lr
         JOIN mentors m ON lr.mentor_id = m.mentor_id
         JOIN proof_documents pd ON lr.req_id = pd.req_id
         WHERE m.dept_id = (SELECT dept_id FROM hods WHERE hod_id=?)
           AND pd.mentor_verified=1 AND pd.hod_verified=0"
    );
    $stmt2->bind_param('i', $uid);
    $stmt2->execute();
    $proofsToVerify = (int)($stmt2->get_result()->fetch_assoc()['cnt'] ?? 0);

    $data = [
        'awaiting_review'   => $awaiting,
        'approved_total'    => $approvedTotal,
        'proofs_to_verify'  => $proofsToVerify,
        'approval_rate'     => $approvalRate,
    ];
}

$db->close();
response(true, 'OK', $data);
?>
