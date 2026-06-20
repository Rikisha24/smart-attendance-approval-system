<?php
// Quick test: open http://localhost/smartattend-backend/test_mail.php
// Change the recipient email below to your own, then check inbox/spam.
require_once 'config/mailer.php';

$result = sendEmail('rikishapbl@gmail.com', 'Test from SmartAttend', 'If you see this, email is working!');

if ($result) {
    echo "SUCCESS - email sent! Check the inbox (and spam folder).";
} else {
    echo "FAILED - check C:/xampp/apache/logs/error.log for the SmartAttend Mail FAILED line.";
}
?>
