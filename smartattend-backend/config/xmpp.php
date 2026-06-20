<?php
// ============================================================
//  config/xmpp.php
//  NOTE: This file is kept with its original name for
//  compatibility (other API files do `require_once xmpp.php`),
//  but it NO LONGER uses ejabberd/XMPP.
//
//  Every "sendXMPP" call now sends a real EMAIL via
//  config/mailer.php (PHPMailer + Gmail SMTP). If email isn't
//  configured yet, it fails silently — in-app notifications
//  (the bell icon) ALWAYS work regardless.
// ============================================================

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/mailer.php';

// ============================================================
//  sendXMPP($to_email, $message, $req_id, $subject)
//  Kept the same name/signature for compatibility.
//  $to_email = a real email address (not a JID anymore)
// ============================================================
function sendXMPP($to_email, $message, $req_id = null, $subject = 'SmartAttend Notification') {

    $sent = sendEmail($to_email, $subject, $message);

    // Log every attempt to xmpp_messages table for audit/history
    saveXMPPMessage(MAIL_USERNAME, $to_email, $message, $req_id, $sent);

    return $sent;
}

// ============================================================
//  saveXMPPMessage()
//  Saves every notification attempt to DB for history/audit
// ============================================================
function saveXMPPMessage($from, $to, $message, $req_id, $delivered) {
    $db  = getDB();
    $stmt = $db->prepare(
        "INSERT INTO xmpp_messages
            (req_id, sender_jid, receiver_jid, message, msg_type, delivered)
         VALUES (?, ?, ?, ?, 'notification', ?)"
    );
    $delivered_int = $delivered ? 1 : 0;
    $stmt->bind_param('isssi', $req_id, $from, $to, $message, $delivered_int);
    $stmt->execute();
    $db->close();
}

// ============================================================
//  saveNotification()
//  Saves an in-app notification to the notifications table
//  This is shown in the bell icon / notifications page
// ============================================================
function saveNotification($user_type, $user_id, $req_id, $title, $message) {
    $db   = getDB();
    $stmt = $db->prepare(
        "INSERT INTO notifications
            (user_type, user_id, req_id, title, message)
         VALUES (?, ?, ?, ?, ?)"
    );
    $stmt->bind_param('siiss', $user_type, $user_id, $req_id, $title, $message);
    $stmt->execute();
    $db->close();
}

// ============================================================
//  NOTIFICATION TRIGGER FUNCTIONS
//  Called at each stage of the workflow
//  (in-app notification ALWAYS saved; email sent if configured)
// ============================================================

// Called when: student submits leave → notify mentor
function notifyMentorNewRequest($mentor_id, $mentor_email, $student_name,$student_usn, $req_code, $req_id) {
    $msg = "New leave request {$req_code} from {$student_name}. Please review on your dashboard.";

    sendXMPP($mentor_email, $msg, $req_id, "New Leave Request - {$req_code}");
    saveNotification('mentor', $mentor_id, $req_id,
        "New Leave Request — {$req_code}",
        "Student {$student_name} has submitted leave request {$req_code}. Awaiting your review."
    );
}

// Called when: mentor approves → notify HOD
function notifyHODMentorApproved($hod_id, $hod_email, $student_name, $req_code, $req_id) {
    $msg = "Leave request {$req_code} by {$student_name} approved by mentor. Awaiting your approval.";

    sendXMPP($hod_email, $msg, $req_id, "Mentor Approved - {$req_code}");
    saveNotification('hod', $hod_id, $req_id,
        "Mentor Approved — {$req_code}",
        "Leave request {$req_code} has been approved by mentor. Awaiting HOD review."
    );
}

// Called when: HOD approves → notify student (provisional approval)
function notifyStudentHODApproved($student_id, $student_email, $req_code, $deadline, $req_id) {
    $msg = "Your leave request {$req_code} is provisionally approved! Upload event proof by {$deadline}.";

    sendXMPP($student_email, $msg, $req_id, "Leave Approved - {$req_code}");
    saveNotification('student', $student_id, $req_id,
        "Leave Approved — {$req_code}",
        "HOD has approved your leave {$req_code}. Upload event proof by {$deadline} to complete the process."
    );
}

// Called when: student uploads proof → notify mentor
function notifyMentorProofUploaded($mentor_id, $mentor_email, $student_name, $req_code, $req_id) {
    $msg = "Proof uploaded for {$req_code} by {$student_name}. Please verify.";

    sendXMPP($mentor_email, $msg, $req_id, "Proof Uploaded - {$req_code}");
    saveNotification('mentor', $mentor_id, $req_id,
        "Proof Uploaded — {$req_code}",
        "Student {$student_name} has uploaded proof for {$req_code}. Awaiting your verification."
    );
}

// Called when: HOD verifies proof → notify student (attendance updated)
function notifyStudentAttendanceUpdated($student_id, $student_email, $req_code, $classes_count, $req_id) {
    $msg = "Attendance updated for {$req_code}. {$classes_count} class(es) marked present.";

    sendXMPP($student_email, $msg, $req_id, "Attendance Updated - {$req_code}");
    saveNotification('student', $student_id, $req_id,
        "Attendance Updated — {$req_code}",
        "{$classes_count} class(es) have been marked present for leave {$req_code}."
    );
}

// Called when: request is rejected at any stage
function notifyStudentRejected($student_id, $student_email, $req_code, $reason, $rejected_by, $req_id) {
    $msg = "Your leave request {$req_code} was rejected by {$rejected_by}. Reason: {$reason}";

    sendXMPP($student_email, $msg, $req_id, "Leave Rejected - {$req_code}");
    saveNotification('student', $student_id, $req_id,
        "Leave Rejected — {$req_code}",
        "Your leave request {$req_code} was rejected by {$rejected_by}. Reason: {$reason}"
    );
}
?>
