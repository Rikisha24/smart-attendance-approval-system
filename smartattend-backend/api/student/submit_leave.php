<?php
// ============================================================
//  api/student/submit_leave.php
//  POST /api/student/submit_leave
//  Multipart form data (because of file upload):
//    Fields: student_id, leave_start_date, leave_end_date,
//            reason, category, description,
//            subjects (JSON string of array)
//    File:   permission_file
//  Returns: { success, message, data: { req_id, req_code } }
// ============================================================

require_once '../../config/db.php';
require_once '../../config/xmpp.php';

authCheck(['student']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    response(false, 'Only POST method allowed');
}

// ---- Read form fields --------------------------------------
$student_id  = $_SESSION['user_id'];
$start_date  = $_POST['leave_start_date'] ?? '';
$end_date    = $_POST['leave_end_date']   ?? '';
$reason      = trim($_POST['reason']      ?? '');
$category    = $_POST['category']         ?? '';
$description = trim($_POST['description'] ?? '');
$subjects    = json_decode($_POST['subjects'] ?? '[]', true);

if (!$start_date || !$end_date || !$reason || !$category || empty($subjects)) {
    response(false, 'All required fields must be filled');
}

// ---- Validate dates ----------------------------------------
$d_start = DateTime::createFromFormat('Y-m-d', $start_date);
$d_end   = DateTime::createFromFormat('Y-m-d', $end_date);

if (!$d_start || !$d_end) {
    response(false, 'Invalid date format. Use YYYY-MM-DD.');
}
if ($d_end < $d_start) {
    response(false, 'End date cannot be before start date.');
}

// ---- Handle permission letter upload -----------------------
$permission_file = null;
if (isset($_FILES['permission_file']) && $_FILES['permission_file']['error'] === UPLOAD_ERR_OK) {
    $file     = $_FILES['permission_file'];
    $ext      = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowed  = ['pdf', 'jpg', 'jpeg', 'png'];

    if (!in_array($ext, $allowed)) {
        response(false, 'Only PDF, JPG, PNG files are allowed');
    }
    if ($file['size'] > MAX_FILE_SIZE) {
        response(false, 'File size exceeds 5MB limit');
    }

    $filename        = 'perm_' . $student_id . '_' . time() . '.' . $ext;
    $permission_file = 'permissions/' . $filename;
    move_uploaded_file($file['tmp_name'], UPLOAD_DIR . $permission_file);
} else {
    response(false, 'Permission letter is required');
}

// ---- Get student's mentor and HOD --------------------------
$db = getDB();

$stmt = $db->prepare(
    "SELECT s.mentor_id, s.name AS student_name, s.email AS student_email,
            m.name  AS mentor_name,  m.email AS mentor_email,
            d.hod_email,
            h.hod_id
     FROM   students s
     JOIN   mentors  m  ON s.mentor_id = m.mentor_id
     JOIN   departments d ON s.dept_id = d.dept_id
     JOIN   hods h ON h.dept_id = s.dept_id
     WHERE  s.student_id = ?
     LIMIT 1"
);
$stmt->bind_param('i', $student_id);
$stmt->execute();
$info = $stmt->get_result()->fetch_assoc();

if (!$info) {
    response(false, 'Student record not found');
}

$mentor_id = $info['mentor_id'];
$hod_id    = $info['hod_id'];

// ---- Generate unique req_code ------------------------------
$req_code = generateReqCode($db);

// ---- Insert leave_requests row -----------------------------
$stmt = $db->prepare(
    "INSERT INTO leave_requests
        (req_code, student_id, mentor_id, hod_id,
         leave_start_date, leave_end_date, reason, category,
         description, permission_file, status)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending_mentor')"
);
$stmt->bind_param('siiissssss',
    $req_code, $student_id, $mentor_id, $hod_id,
    $start_date, $end_date, $reason, $category,
    $description, $permission_file
);
$stmt->execute();
$req_id = $db->insert_id;

// ---- Insert leave_subjects rows ----------------------------
$subj_stmt = $db->prepare(
    "INSERT INTO leave_subjects (req_id, subject_name, faculty_email, class_date)
     VALUES (?, ?, ?, ?)"
);
foreach ($subjects as $subj) {
    $subj_stmt->bind_param('isss',
        $req_id,
        $subj['subject_name'],
        $subj['faculty_email'],
        $subj['class_date']
    );
    $subj_stmt->execute();
}

// ---- Send XMPP notification to mentor ----------------------
notifyMentorNewRequest(
    $mentor_id,
    $info['mentor_email'],
    $info['student_name'],
    $req_code,
    $req_id
);

$db->close();

response(true, 'Leave request submitted successfully', [
    'req_id'   => $req_id,
    'req_code' => $req_code,
    'status'   => 'pending_mentor'
]);
?>
