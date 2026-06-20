<?php
// ============================================================
//  config/mailer.php
//  Self-contained SMTP client for Gmail — NO external library,
//  NO download needed. Pure PHP using sockets.
//
//  SETUP:
//  1. Create a Gmail "App Password":
//     https://myaccount.google.com/apppasswords
//     (Enable 2-Step Verification first if not already on)
//     Select app: Mail, device: Other -> "SmartAttend"
//     Copy the 16-character password (remove spaces)
//
//  2. Fill in MAIL_USERNAME and MAIL_PASSWORD below.
//
//  If not configured (or sending fails), sendEmail() returns
//  false SILENTLY — the workflow keeps working normally and
//  in-app notifications still save regardless.
// ============================================================

define('MAIL_HOST',     'smtp.gmail.com');
define('MAIL_PORT',     587);
define('MAIL_USERNAME', 'rikishapbl@gmail.com');     // change this
define('MAIL_PASSWORD', 'reycgjnpeurutodc');   // change this (Gmail App Password, no spaces)
define('MAIL_FROM_NAME','SmartAttend Portal');

// ============================================================
//  sendEmail($to, $subject, $body)
//  Returns true on success, false on any failure (never throws).
// ============================================================
function sendEmail($to, $subject, $body) {

    if (MAIL_USERNAME === 'your-email@gmail.com' || MAIL_PASSWORD === 'your-app-password') {
        error_log("SmartAttend Mail SKIPPED (not configured): to={$to} subject={$subject}");
        return false;
    }

    $host = MAIL_HOST;
    $port = MAIL_PORT;
    $user = MAIL_USERNAME;
    $pass = MAIL_PASSWORD;
    $from = MAIL_USERNAME;

    $errno = 0; $errstr = '';
    $sock = @fsockopen($host, $port, $errno, $errstr, 15);
    if (!$sock) {
        error_log("SmartAttend Mail FAILED: cannot connect to {$host}:{$port} - {$errstr}");
        return false;
    }

    stream_set_timeout($sock, 15);

    $send = function($cmd) use ($sock) {
        if ($cmd !== null) fwrite($sock, $cmd . "\r\n");
        $resp = '';
        while ($line = fgets($sock, 515)) {
            $resp .= $line;
            if (substr($line, 3, 1) === ' ') break;
        }
        return $resp;
    };

    try {
        $resp = $send(null);
        if (substr($resp, 0, 3) !== '220') throw new Exception("Bad banner: $resp");

        $resp = $send("EHLO localhost");
        if (substr($resp, 0, 3) !== '250') throw new Exception("EHLO failed: $resp");

        $resp = $send("STARTTLS");
        if (substr($resp, 0, 3) !== '220') throw new Exception("STARTTLS failed: $resp");

        if (!stream_socket_enable_crypto($sock, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
            throw new Exception("TLS handshake failed");
        }

        $resp = $send("EHLO localhost");
        if (substr($resp, 0, 3) !== '250') throw new Exception("EHLO (TLS) failed: $resp");

        $resp = $send("AUTH LOGIN");
        if (substr($resp, 0, 3) !== '334') throw new Exception("AUTH LOGIN failed: $resp");

        $resp = $send(base64_encode($user));
        if (substr($resp, 0, 3) !== '334') throw new Exception("Username rejected: $resp");

        $resp = $send(base64_encode($pass));
        if (substr($resp, 0, 3) !== '235') throw new Exception("Authentication failed: $resp");

        $resp = $send("MAIL FROM:<{$from}>");
        if (substr($resp, 0, 3) !== '250') throw new Exception("MAIL FROM failed: $resp");

        $resp = $send("RCPT TO:<{$to}>");
        if (substr($resp, 0, 3) !== '250' && substr($resp, 0, 3) !== '251') {
            throw new Exception("RCPT TO failed: $resp");
        }

        $resp = $send("DATA");
        if (substr($resp, 0, 3) !== '354') throw new Exception("DATA failed: $resp");

        $fromName = MAIL_FROM_NAME;
        $headers  = "From: {$fromName} <{$from}>\r\n";
        $headers .= "To: <{$to}>\r\n";
        $headers .= "Subject: {$subject}\r\n";
        $headers .= "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
        $headers .= "\r\n";

        $escapedBody = preg_replace('/^\./m', '..', $body);

        $message = $headers . $escapedBody . "\r\n.";
        $resp = $send($message);
        if (substr($resp, 0, 3) !== '250') throw new Exception("Message send failed: $resp");

        $send("QUIT");
        fclose($sock);
        return true;

    } catch (Exception $e) {
        error_log("SmartAttend Mail FAILED to {$to}: " . $e->getMessage());
        @fclose($sock);
        return false;
    }
}
?>
