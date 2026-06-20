<?php
// ============================================================
//  api/mentor/get_pending.php
//  GET /api/mentor/get_pending
//  Returns all leave requests assigned to this mentor
//  that are in 'pending_mentor' status
// ============================================================

require_once '../../config/db.php';
authCheck(['mentor']);

$mentor_id = $_SESSION['user_id'];
$db        = getDB();

$stmt = $db->prepare(
    "SELECT lr.req_id, lr.req_code, lr.leave_start_date, lr.leave_end_date,
            lr.reason, lr.category, lr.status, lr.permission_file,
            lr.created_at,
            s.name AS student_name, s.usn, s.email AS student_email,
            s.semester, s.section,
            COUNT(ls.subject_id) AS subject_count
     FROM   leave_requests lr
     JOIN   students s ON lr.student_id = s.student_id
     LEFT JOIN leave_subjects ls ON lr.req_id = ls.req_id
     WHERE  lr.mentor_id = ?
       AND  lr.status = 'pending_mentor'
     GROUP  BY lr.req_id
     ORDER  BY lr.created_at ASC"
);
$stmt->bind_param('i', $mentor_id);
$stmt->execute();
$pending = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Also fetch proof_verification queue
$stmt2 = $db->prepare(
    "SELECT lr.req_id, lr.req_code, lr.reason,
            s.name AS student_name, s.usn,
            pd.proof_type, pd.file_path, pd.uploaded_at
     FROM   leave_requests lr
     JOIN   students s  ON lr.student_id = s.student_id
     JOIN   proof_documents pd ON lr.req_id = pd.req_id
     WHERE  lr.mentor_id = ?
       AND  lr.status    = 'proof_uploaded'
     ORDER  BY pd.uploaded_at ASC"
);
$stmt2->bind_param('i', $mentor_id);
$stmt2->execute();
$proof_queue = $stmt2->get_result()->fetch_all(MYSQLI_ASSOC);

$db->close();
response(true, 'Data fetched', [
    'pending_requests' => $pending,
    'proof_queue'      => $proof_queue
]);
?>
