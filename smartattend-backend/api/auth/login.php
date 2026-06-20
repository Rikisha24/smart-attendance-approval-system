<?php
// ============================================================
//  api/auth/login.php
//  POST /api/auth/login
//  Body: { "usn_or_email": "4NM21CS042", "password": "...", "role": "student" }
//  Returns: { success, message, data: { user, token } }
// ============================================================

require_once '../../config/db.php';
session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    response(false, 'Only POST method allowed');
}

$input    = json_decode(file_get_contents('php://input'), true);
$login    = trim($input['usn_or_email'] ?? '');
$password = $input['password'] ?? '';
$role     = $input['role'] ?? '';  // 'student' | 'mentor' | 'hod'

// Basic validation
if (!$login || !$password || !$role) {
    response(false, 'All fields are required');
}

$db = getDB();

// ---- Pick the right table based on role --------------------
switch ($role) {

    case 'student':
        // Students can login with USN or email
        $stmt = $db->prepare(
            "SELECT s.student_id AS user_id, s.name, s.email, s.password,
                    s.usn, s.dept_id, s.semester, s.section,
                    s.mentor_id,
                    m.name  AS mentor_name,
                    m.email AS mentor_email,
                    d.dept_name
             FROM   students s
             JOIN   mentors m ON s.mentor_id = m.mentor_id
             JOIN   departments d ON s.dept_id = d.dept_id
             WHERE  s.usn = ? OR s.email = ?
             LIMIT 1"
        );
        $stmt->bind_param('ss', $login, $login);
        break;

    case 'mentor':
        $stmt = $db->prepare(
            "SELECT m.mentor_id AS user_id, m.name, m.email, m.password,
                    m.dept_id, d.dept_name
             FROM   mentors m
             JOIN   departments d ON m.dept_id = d.dept_id
             WHERE  m.email = ?
             LIMIT 1"
        );
        $stmt->bind_param('s', $login);
        break;

    case 'hod':
        $stmt = $db->prepare(
            "SELECT h.hod_id AS user_id, h.name, h.email, h.password,
                    h.dept_id, d.dept_name
             FROM   hods h
             JOIN   departments d ON h.dept_id = d.dept_id
             WHERE  h.email = ?
             LIMIT 1"
        );
        $stmt->bind_param('s', $login);
        break;

    default:
        response(false, 'Invalid role');
}

$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

// ---- Verify password ---------------------------------------
if (!$user || !password_verify($password, $user['password'])) {
    response(false, 'Invalid credentials');
}

// ---- Create PHP session ------------------------------------
$_SESSION['user_id']   = $user['user_id'];
$_SESSION['user_type'] = $role;
$_SESSION['name']      = $user['name'];
$_SESSION['email']     = $user['email'];
$_SESSION['dept_id']   = $user['dept_id'];

// ---- Remove password from response -------------------------
unset($user['password']);

response(true, 'Login successful', [
    'user'      => $user,
    'user_type' => $role,
    'session_id'=> session_id()
]);
?>
