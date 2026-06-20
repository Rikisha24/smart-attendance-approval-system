<?php
// ============================================================
//  api/hod/get_pending.php
//  GET /api/hod/get_pending
//  Returns mentor-approved leave requests + proof verify queue
// ============================================================

require_once '../../config/db.php';
authCheck(['hod']);

$hod_id = $_SESSION['user_id'];
$db     = getDB();

// Mentor-approved requests waiting for HOD decision
$stmt = $db->prepare(
    "SELECT lr.req_id, lr.req_code, lr.leave_start_date, lr.leave_end_date,
            lr.reason, lr.category, lr.status, lr.permission_file, lr.created_at,
            s.name AS student_name, s.usn, s.email AS student_email,
            s.semester, s.section,
            m.name AS mentor_name,
            COUNT(ls.subject_id) AS subject_count
     FROM   leave_requests lr
     JOIN   students s  ON lr.student_id = s.student_id
     JOIN   mentors m   ON lr.mentor_id  = m.mentor_id
     LEFT JOIN leave_subjects ls ON lr.req_id = ls.req_id
     WHERE  lr.hod_id = ? AND lr.status = 'mentor_approved'
     GROUP  BY lr.req_id
     ORDER  BY lr.created_at ASC"
);
$stmt->bind_param('i', $hod_id);
$stmt->execute();
$pending = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Proof verification queue (mentor verified, awaiting HOD)
$stmt2 = $db->prepare(
    "SELECT lr.req_id, lr.req_code, lr.reason,
            s.name AS student_name, s.usn,
            m.name AS mentor_name,
            pd.proof_type, pd.file_path, pd.uploaded_at, pd.mentor_verified
     FROM   leave_requests lr
     JOIN   students s  ON lr.student_id = s.student_id
     JOIN   mentors m   ON lr.mentor_id  = m.mentor_id
     JOIN   proof_documents pd ON lr.req_id = pd.req_id
     WHERE  lr.hod_id = ? AND lr.status = 'mentor_verified'
     ORDER  BY pd.uploaded_at ASC"
);
$stmt2->bind_param('i', $hod_id);
$stmt2->execute();
$proof_queue = $stmt2->get_result()->fetch_all(MYSQLI_ASSOC);

$db->close();
response(true, 'Data fetched', [
    'pending_requests' => $pending,
    'proof_queue'      => $proof_queue
]);
?>
