<?php
// ============================================================
//  api/auth/logout.php
//  POST /api/auth/logout
//  Destroys the session
// ============================================================

require_once '../../config/db.php';
session_start();
session_destroy();
response(true, 'Logged out successfully');
?>
