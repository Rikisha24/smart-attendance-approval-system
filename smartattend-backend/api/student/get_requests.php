<?php
// ============================================================
//  api/student/get_requests.php
//  GET /api/student/get_requests
//  Returns all leave requests for the logged-in student
//  with subjects and current status
// ============================================================

require_once '../../config/db.php';
authCheck(['student']);

$student_id = $_SESSION['user_id'];
$db         = getDB();

// Get all requests with subject count
$stmt = $db->prepare(
    "SELECT lr.*,
            COUNT(ls.subject_id) AS subject_count
     FROM   leave_requests lr
     LEFT JOIN leave_subjects ls ON lr.req_id = ls.req_id
     WHERE  lr.student_id = ?
     GROUP  BY lr.req_id
     ORDER  BY lr.created_at DESC"
);
$stmt->bind_param('i', $student_id);
$stmt->execute();
$requests = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// For each request, also fetch subjects
foreach ($requests as &$req) {
    $s = $db->prepare(
        "SELECT subject_name, faculty_email, class_date
         FROM   leave_subjects WHERE req_id = ?"
    );
    $s->bind_param('i', $req['req_id']);
    $s->execute();
    $req['subjects'] = $s->get_result()->fetch_all(MYSQLI_ASSOC);
}

$db->close();
response(true, 'Requests fetched', ['requests' => $requests]);
?>
