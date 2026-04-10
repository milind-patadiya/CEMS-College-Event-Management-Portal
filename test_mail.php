<?php
// ── CEMS SMTP Debug Test ──────────────────────────────────
// Access: http://localhost/CEMS_PROJECT_FIXED/test_mail.php
// DELETE THIS FILE after testing!
// ─────────────────────────────────────────────────────────

require_once("config/email.php");

echo "<style>
body{font-family:monospace;background:#1a1a2e;color:#eee;padding:30px;}
.ok{color:#66bb6a;font-weight:bold;}
.fail{color:#ef5350;font-weight:bold;}
.info{color:#64b5f6;}
.box{background:#16213e;padding:20px;border-radius:8px;margin:10px 0;border-left:4px solid #4a90d9;}
h2{color:#ffd54f;}
</style>";

echo "<h2>🔧 CEMS SMTP Debug Test</h2>";

// ── Step 1: Check PHP Extensions ─────────────────────────
echo "<div class='box'><b class='info'>Step 1: PHP Extensions</b><br>";
$openssl = extension_loaded('openssl');
$sockets = extension_loaded('sockets');
echo "OpenSSL: " . ($openssl ? "<span class='ok'>✅ Enabled</span>" : "<span class='fail'>❌ DISABLED — php.ini ma extension=openssl uncomment karo!</span>") . "<br>";
echo "Sockets: " . ($sockets ? "<span class='ok'>✅ Enabled</span>" : "<span class='fail'>❌ Disabled</span>") . "<br>";
echo "</div>";

// ── Step 2: Check Config ──────────────────────────────────
echo "<div class='box'><b class='info'>Step 2: Email Config</b><br>";
echo "Host: <span class='ok'>" . MAIL_HOST . "</span><br>";
echo "Port: <span class='ok'>" . MAIL_PORT . "</span><br>";
echo "Username: <span class='ok'>" . MAIL_USERNAME . "</span><br>";
$pass = MAIL_PASS;
$pass_masked = substr($pass, 0, 4) . str_repeat('*', max(0, strlen($pass)-8)) . substr($pass, -4);
echo "Password: <span class='ok'>" . htmlspecialchars($pass_masked) . "</span> (" . strlen($pass) . " chars)<br>";
if (strlen(str_replace(' ', '', $pass)) !== 16) {
    echo "<span class='fail'>⚠️ App Password 16 chars hovi joiye (spaces without) — check karo!</span><br>";
} else {
    echo "<span class='ok'>✅ Password length OK (16 chars)</span><br>";
}
echo "</div>";

// ── Step 3: TCP Connection Test ───────────────────────────
echo "<div class='box'><b class='info'>Step 3: TCP Connection to smtp.gmail.com:587</b><br>";
$sock = @fsockopen("tcp://smtp.gmail.com", 587, $errno, $errstr, 10);
if ($sock) {
    $greeting = fgets($sock, 512);
    echo "<span class='ok'>✅ Connected!</span> Greeting: " . htmlspecialchars(trim($greeting)) . "<br>";
    fclose($sock);
} else {
    echo "<span class='fail'>❌ Connection FAILED: $errstr ($errno)</span><br>";
    echo "<span class='fail'>Fix: Windows Firewall → Allow port 587 outbound</span><br>";
}
echo "</div>";

// ── Step 4: Full SMTP Auth Test ───────────────────────────
echo "<div class='box'><b class='info'>Step 4: Full SMTP Authentication Test</b><br>";

if (!$openssl) {
    echo "<span class='fail'>❌ Skipped — OpenSSL not enabled</span><br>";
} elseif (!$sock && $errno) {
    echo "<span class='fail'>❌ Skipped — TCP connection failed</span><br>";
} else {
    try {
        // Connect
        $s = fsockopen("tcp://smtp.gmail.com", 587, $errno, $errstr, 10);
        if (!$s) throw new Exception("Connect failed: $errstr");
        stream_set_timeout($s, 15);
        $r = fgets($s, 512);
        echo "Connect: <span class='ok'>" . htmlspecialchars(trim($r)) . "</span><br>";

        // EHLO
        fwrite($s, "EHLO localhost\r\n");
        $r = '';
        while ($line = fgets($s, 512)) { $r .= $line; if ($line[3] === ' ') break; }
        echo "EHLO: <span class='ok'>OK</span><br>";

        // STARTTLS
        fwrite($s, "STARTTLS\r\n");
        $r = fgets($s, 512);
        echo "STARTTLS: <span class='ok'>" . htmlspecialchars(trim($r)) . "</span><br>";
        if (substr(trim($r), 0, 3) !== '220') throw new Exception("STARTTLS failed: $r");

        // TLS upgrade
        $ok = stream_socket_enable_crypto($s, true, STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT);
        if (!$ok) {
            $ok = stream_socket_enable_crypto($s, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
        }
        echo "TLS Upgrade: " . ($ok ? "<span class='ok'>✅ Success</span>" : "<span class='fail'>❌ Failed</span>") . "<br>";
        if (!$ok) throw new Exception("TLS upgrade failed");

        // EHLO again
        fwrite($s, "EHLO localhost\r\n");
        $r = '';
        while ($line = fgets($s, 512)) { $r .= $line; if ($line[3] === ' ') break; }
        echo "EHLO after TLS: <span class='ok'>OK</span><br>";

        // AUTH LOGIN
        fwrite($s, "AUTH LOGIN\r\n");
        $r = fgets($s, 512);
        echo "AUTH LOGIN: <span class='ok'>" . htmlspecialchars(trim($r)) . "</span><br>";
        if (substr(trim($r), 0, 3) !== '334') throw new Exception("AUTH LOGIN failed: $r");

        // Username
        fwrite($s, base64_encode(MAIL_USERNAME) . "\r\n");
        $r = fgets($s, 512);
        echo "Username sent: <span class='ok'>" . htmlspecialchars(trim($r)) . "</span><br>";
        if (substr(trim($r), 0, 3) !== '334') throw new Exception("Username rejected: $r");

        // Password
        fwrite($s, base64_encode(MAIL_PASS) . "\r\n");
        $r = fgets($s, 512);
        echo "Password response: <span class='ok'>" . htmlspecialchars(trim($r)) . "</span><br>";

        if (substr(trim($r), 0, 3) === '235') {
            echo "<br><span class='ok'>✅✅✅ AUTH SUCCESS! Gmail SMTP working perfectly!</span><br>";

            // Send test email
            $test_to = 'prashantvala40@gmail.com'; // Send to external email
            fwrite($s, "MAIL FROM:<" . MAIL_FROM . ">\r\n");
            $r = fgets($s, 512);
            fwrite($s, "RCPT TO:<$test_to>\r\n");
            $r = fgets($s, 512);
            fwrite($s, "DATA\r\n");
            $r = fgets($s, 512);

            $msg  = "Date: " . date('r') . "\r\n";
            $msg .= "From: \"CEMS Portal\" <" . MAIL_FROM . ">\r\n";
            $msg .= "To: $test_to\r\n";
            $msg .= "Subject: CEMS SMTP Test\r\n";
            $msg .= "Content-Type: text/plain\r\n\r\n";
            $msg .= "CEMS SMTP test successful! Email system is working.\r\n.\r\n";
            fwrite($s, $msg);
            $r = fgets($s, 512);
            echo "Test email sent to <b>" . MAIL_USERNAME . "</b>: <span class='ok'>" . htmlspecialchars(trim($r)) . "</span><br>";
            echo "<br><span class='ok'>Check " . MAIL_USERNAME . " inbox for test email!</span>";
        } else {
            echo "<br><span class='fail'>❌ AUTH FAILED: " . htmlspecialchars(trim($r)) . "</span><br>";
            echo "<span class='fail'>Fix: Generate new App Password from myaccount.google.com/apppasswords</span><br>";
        }

        fwrite($s, "QUIT\r\n");
        fclose($s);

    } catch (Exception $e) {
        echo "<span class='fail'>❌ Error: " . htmlspecialchars($e->getMessage()) . "</span><br>";
    }
}
echo "</div>";

echo "<div class='box' style='border-color:#ef5350;'>";
echo "<span class='fail'>⚠️ DELETE test_mail.php after testing! Never leave it on production.</span>";
echo "</div>";
?>
