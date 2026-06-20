<?php
// ============================================================
//  generate_passwords.php
//  Run this ONCE after importing database.sql to insert all
//  sample users with correct bcrypt password hashes.
//
//  Usage: Open http://localhost/smartattend-backend/generate_passwords.php
//  Default password for all users: Password@123
//
//  DELETE THIS FILE after running it.
// ============================================================

require_once 'config/db.php';

$password = 'Password@123';
$hash     = password_hash($password, PASSWORD_BCRYPT);
$db       = getDB();

$errors  = [];
$success = [];

// ---- Insert Mentors ----------------------------------------
$mentors = [
    ['Prof. Anitha S', 'anitha@college.edu', 'CSE', '9876543210'],
    ['Prof. Kumar B',  'kumar@college.edu',  'CSE', '9876543211'],
    ['Prof. Latha R',  'latha@college.edu',  'ECE', '9876543212'],
];

foreach ($mentors as $m) {
    $stmt = $db->prepare(
        "INSERT IGNORE INTO mentors (name, email, password, dept_id, phone)
         VALUES (?, ?, ?, ?, ?)"
    );
    $stmt->bind_param('sssss', $m[0], $m[1], $hash, $m[2], $m[3]);
    if ($stmt->execute()) {
        $success[] = "Mentor: {$m[0]} ({$m[1]})";
    } else {
        $errors[] = "Mentor {$m[1]}: " . $db->error;
    }
}

// ---- Insert HODs -------------------------------------------
$hods = [
    ['Prof. Ramesh K', 'ramesh.hod@college.edu', 'CSE'],
    ['Prof. Sunita M', 'sunita.hod@college.edu', 'ECE'],
];

foreach ($hods as $h) {
    $stmt = $db->prepare(
        "INSERT IGNORE INTO hods (name, email, password, dept_id)
         VALUES (?, ?, ?, ?)"
    );
    $stmt->bind_param('ssss', $h[0], $h[1], $hash, $h[2]);
    if ($stmt->execute()) {
        $success[] = "HOD: {$h[0]} ({$h[1]})";
    } else {
        $errors[] = "HOD {$h[1]}: " . $db->error;
    }
}

// ---- Get mentor IDs for student insert ---------------------
$r = $db->query("SELECT mentor_id FROM mentors WHERE email='anitha@college.edu' LIMIT 1");
$mentor1 = $r ? $r->fetch_assoc()['mentor_id'] : 1;
$r = $db->query("SELECT mentor_id FROM mentors WHERE email='kumar@college.edu' LIMIT 1");
$mentor2 = $r ? $r->fetch_assoc()['mentor_id'] : 2;

// ---- Insert Students ---------------------------------------
$students = [
    ['4NM21CS042', 'Arjun Kumar',  'arjun@college.edu', 'CSE', $mentor1, 6, 'A'],
    ['4NM21CS089', 'Priya Sharma', 'priya@college.edu', 'CSE', $mentor1, 6, 'A'],
    ['4NM21CS056', 'Rahul Naik',   'rahul@college.edu', 'CSE', $mentor2, 6, 'B'],
];

foreach ($students as $s) {
    $stmt = $db->prepare(
        "INSERT IGNORE INTO students (usn, name, email, password, dept_id, mentor_id, semester, section)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
    );
    $stmt->bind_param('sssssisi', $s[0], $s[1], $s[2], $hash, $s[3], $s[4], $s[5], $s[6]);
    if ($stmt->execute()) {
        $success[] = "Student: {$s[1]} ({$s[0]})";
    } else {
        $errors[] = "Student {$s[0]}: " . $db->error;
    }
}

$db->close();
?>
<!DOCTYPE html>
<html>
<head>
  <title>SmartAttend — Setup</title>
  <style>
    body { font-family: Arial, sans-serif; max-width: 600px; margin: 60px auto; padding: 0 20px; }
    h2   { color: #1e40af; }
    .ok  { background: #f0fdf4; border: 1px solid #86efac; border-radius: 8px; padding: 12px 16px; margin: 6px 0; color: #166534; }
    .err { background: #fef2f2; border: 1px solid #fca5a5; border-radius: 8px; padding: 12px 16px; margin: 6px 0; color: #991b1b; }
    .box { background: #eff6ff; border: 1px solid #93c5fd; border-radius: 8px; padding: 16px; margin-top: 24px; }
    code { background: #f1f5f9; padding: 2px 6px; border-radius: 4px; font-size: 14px; }
  </style>
</head>
<body>
  <h2>SmartAttend — Password Setup</h2>

  <?php if (!empty($success)): ?>
    <h3 style="color:#166534;">✅ Users inserted successfully:</h3>
    <?php foreach ($success as $s): ?>
      <div class="ok">✓ <?= htmlspecialchars($s) ?></div>
    <?php endforeach; ?>
  <?php endif; ?>

  <?php if (!empty($errors)): ?>
    <h3 style="color:#991b1b;">❌ Errors:</h3>
    <?php foreach ($errors as $e): ?>
      <div class="err">✗ <?= htmlspecialchars($e) ?></div>
    <?php endforeach; ?>
  <?php endif; ?>

  <div class="box">
    <strong>Default password for all users:</strong> <code>Password@123</code><br><br>
    <strong>Login credentials:</strong><br>
    Student — USN: <code>4NM21CS042</code> / Password: <code>Password@123</code><br>
    Mentor  — Email: <code>anitha@college.edu</code> / Password: <code>Password@123</code><br>
    HOD     — Email: <code>ramesh.hod@college.edu</code> / Password: <code>Password@123</code><br><br>
    <strong style="color:#991b1b;">⚠️ Delete this file after setup is complete.</strong>
  </div>
</body>
</html>
