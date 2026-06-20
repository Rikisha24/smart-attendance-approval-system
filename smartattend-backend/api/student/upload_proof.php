<?php
// ============================================================
//  api/student/upload_proof.php
//  POST /api/student/upload_proof
//  Multipart form data:
//    Fields: req_id, proof_type, description
//    File:   proof_file
//  Only allowed if leave status is 'hod_approved'
// ============================================================

require_once '../../config/db.php';
require_once '../../config/xmpp.php';

authCheck(['student']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    response(false, 'Only POST method allowed');
}

$student_id  = $_SESSION['user_id'];
$req_id      = intval($_POST['req_id']      ?? 0);
$proof_type  = $_POST['proof_type']         ?? '';
$description = trim($_POST['description']   ?? '');

if (!$req_id || !$proof_type) {
    response(false, 'req_id and proof_type are required');
}

$db = getDB();

// ---- Verify this request belongs to student & is approved --
$stmt = $db->prepare(
    "SELECT lr.req_code, lr.status, lr.mentor_id,
            s.name AS student_name, s.email AS student_email,
            m.email AS mentor_email
     FROM   leave_requests lr
     JOIN   students s ON lr.student_id = s.student_id
     JOIN   mentors  m ON lr.mentor_id  = m.mentor_id
     WHERE  lr.req_id = ? AND lr.student_id = ?
     LIMIT 1"
);
$stmt->bind_param('ii', $req_id, $student_id);
$stmt->execute();
$req = $stmt->get_result()->fetch_assoc();

if (!$req) {
    response(false, 'Request not found');
}
if ($req['status'] !== 'hod_approved') {
    response(false, 'Proof can only be uploaded after HOD approval. Current status: ' . $req['status']);
}

// ---- Handle file upload ------------------------------------
if (!isset($_FILES['proof_file']) || $_FILES['proof_file']['error'] !== UPLOAD_ERR_OK) {
    response(false, 'Proof file is required');
}

$file    = $_FILES['proof_file'];
$ext     = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
$allowed = ['pdf', 'jpg', 'jpeg', 'png'];

if (!in_array($ext, $allowed)) {
    response(false, 'Only PDF, JPG, PNG allowed');
}
if ($file['size'] > MAX_FILE_SIZE * 2) {   // 10MB for proofs
    response(false, 'File exceeds 10MB limit');
}

$filename  = 'proof_' . $req_id . '_' . time() . '.' . $ext;
$file_path = 'proofs/' . $filename;
move_uploaded_file($file['tmp_name'], UPLOAD_DIR . $file_path);

// ---- Insert proof record -----------------------------------
$stmt = $db->prepare(
    "INSERT INTO proof_documents (req_id, proof_type, file_path, description)
     VALUES (?, ?, ?, ?)
     ON DUPLICATE KEY UPDATE
        proof_type  = VALUES(proof_type),
        file_path   = VALUES(file_path),
        description = VALUES(description),
        uploaded_at = CURRENT_TIMESTAMP"
);
$stmt->bind_param('isss', $req_id, $proof_type, $file_path, $description);
$stmt->execute();

// ---- Update leave request status ---------------------------
$db->query("UPDATE leave_requests SET status='proof_uploaded' WHERE req_id={$req_id}");

// ---- Notify mentor -----------------------------------------
notifyMentorProofUploaded(
    $req['mentor_id'],
    $req['mentor_email'],
    $req['student_name'],
    $req['req_code'],
    $req_id
);

$db->close();
response(true, 'Proof uploaded successfully. Mentor has been notified.', [
    'req_id' => $req_id,
    'status' => 'proof_uploaded'
]);
?>
