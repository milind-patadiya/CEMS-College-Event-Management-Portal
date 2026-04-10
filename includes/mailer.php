<?php
// ─────────────────────────────────────────────────────────────
//  CEMS Mailer — Socket-based SMTP (no external library needed)
//  Uses Gmail SMTP with App Password via TLS on port 587
// ─────────────────────────────────────────────────────────────
require_once __DIR__ . '/../config/email.php';

class CEMSMailer {

    private $host;
    private $port;
    private $username;
    private $password;
    private $from;
    private $fromName;
    private $sock;
    private $lastResponse;

    public function __construct() {
        $this->host     = MAIL_HOST;
        $this->port     = MAIL_PORT;
        $this->username = MAIL_USERNAME;
        $this->password = MAIL_PASS;
        $this->from     = MAIL_FROM;
        $this->fromName = MAIL_FROM_NAME;
    }

    // ── Low-level SMTP ───────────────────────────────────────

    private function connect() {
        $this->sock = fsockopen("tcp://{$this->host}", $this->port, $errno, $errstr, 15);
        if ($this->sock) stream_set_timeout($this->sock, 15);
        if (!$this->sock) throw new Exception("SMTP connect failed: $errstr ($errno)");
        $this->read(); // 220 greeting
    }

    private function send($cmd) {
        fwrite($this->sock, $cmd . "\r\n");
        return $this->read();
    }

    private function read() {
        $res = '';
        while ($line = fgets($this->sock, 512)) {
            $res .= $line;
            if (substr($line, 3, 1) === ' ') break; // last line of response
        }
        $this->lastResponse = $res;
        return $res;
    }

    private function expect($code) {
        if (substr(trim($this->lastResponse), 0, 3) !== (string)$code) {
            throw new Exception("SMTP error (expected $code): " . trim($this->lastResponse));
        }
    }

    // ── Public: send one email ───────────────────────────────

    /**
     * @param string|array $to   'email@x.com' or ['email'=>'Name', ...]
     * @param string $subject
     * @param string $htmlBody
     */
    public function sendMail($to, string $subject, string $htmlBody): bool {
        try {
            $this->connect();

            // EHLO
            $this->send("EHLO " . gethostname()); $this->expect(250);

            // STARTTLS
            $this->send("STARTTLS"); $this->expect(220);
            // Try TLS 1.2 first (most compatible with Windows XAMPP)
            $crypto = STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT;
            if (!stream_socket_enable_crypto($this->sock, true, $crypto)) {
                // Fallback to generic TLS
                stream_socket_enable_crypto($this->sock, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
            }
            $this->send("EHLO " . gethostname()); $this->expect(250);

            // AUTH LOGIN
            $this->send("AUTH LOGIN"); $this->expect(334);
            $this->send(base64_encode($this->username)); $this->expect(334);
            $this->send(base64_encode($this->password)); $this->expect(235);

            // Build recipient list
            $recipients = is_array($to) ? $to : [$to => $to];

            // MAIL FROM
            $this->send("MAIL FROM:<{$this->from}>"); $this->expect(250);

            // RCPT TO for each
            foreach ($recipients as $email => $name) {
                $email = is_int($email) ? $name : $email;
                $this->send("RCPT TO:<$email>"); $this->expect(250);
            }

            // DATA
            $this->send("DATA"); $this->expect(354);

            // Build To: header
            $toHeader = [];
            foreach ($recipients as $email => $name) {
                $email = is_int($email) ? $name : $email;
                $toHeader[] = ($name !== $email) ? "\"$name\" <$email>" : $email;
            }

            $boundary  = md5(uniqid());
            $fromHeader = "\"{$this->fromName}\" <{$this->from}>";
            $date       = date('r');
            $msgId      = '<' . md5(uniqid()) . '@cems.mu.ac.in>';

            $headers  = "Date: $date\r\n";
            $headers .= "Message-ID: $msgId\r\n";
            $headers .= "From: $fromHeader\r\n";
            $headers .= "Reply-To: $fromHeader\r\n";
            $headers .= "To: " . implode(', ', $toHeader) . "\r\n";
            $headers .= "Subject: =?UTF-8?B?" . base64_encode($subject) . "?=\r\n";
            $headers .= "MIME-Version: 1.0\r\n";
            $headers .= "Content-Type: multipart/alternative; boundary=\"$boundary\"\r\n";
            $headers .= "X-Mailer: CEMS-Portal/1.0\r\n";
            $headers .= "X-Priority: 3\r\n";
            $headers .= "Precedence: bulk\r\n";
            $headers .= "Auto-Submitted: auto-generated\r\n";

            $plain = strip_tags($htmlBody);

            $body  = "--$boundary\r\n";
            $body .= "Content-Type: text/plain; charset=UTF-8\r\n\r\n";
            $body .= $plain . "\r\n\r\n";
            $body .= "--$boundary\r\n";
            $body .= "Content-Type: text/html; charset=UTF-8\r\n\r\n";
            $body .= $htmlBody . "\r\n\r\n";
            $body .= "--$boundary--\r\n";

            fwrite($this->sock, $headers . "\r\n" . $body . "\r\n.\r\n");
            $this->read(); $this->expect(250);

            $this->send("QUIT");
            fclose($this->sock);
            return true;

        } catch (Exception $e) {
            if (MAIL_DEBUG) {
                error_log("CEMSMailer: " . $e->getMessage());
                // Store error for display
                $_SESSION['mail_error'] = $e->getMessage();
            }
            if (isset($this->sock) && $this->sock) @fclose($this->sock);
            return false;
        }
    }
}

// ─────────────────────────────────────────────────────────────
//  Helper functions — call these from faculty/admin pages
// ─────────────────────────────────────────────────────────────

/**
 * Notify ALL registered students about a new event.
 * Call after faculty creates an event.
 */
function notifyStudentsNewEvent(mysqli $conn, int $event_id): void {
    try {
        // Get event details
        $eq = $conn->prepare("
            SELECT e.event_name, e.event_date, e.event_time, e.venue, e.description,
                   f.full_name AS faculty_name, f.department
            FROM events e
            LEFT JOIN faculty f ON f.faculty_id = e.created_by
            WHERE e.event_id = ?
        ");
        $eq->bind_param("i", $event_id); $eq->execute();
        $event = $eq->get_result()->fetch_assoc();
        if (!$event) return;

        // Get ALL students (notify everyone — they can choose to register)
        $sq = $conn->query("SELECT email, full_name FROM students WHERE email != '' ORDER BY full_name");
        $students = $sq->fetch_all(MYSQLI_ASSOC);
        if (empty($students)) return;

        $mailer  = new CEMSMailer();
        $subject = "CEMS Portal — New Event: {$event['event_name']}";

        foreach ($students as $s) {
            $html = emailTemplateNewEvent($s['full_name'], $event);
            $mailer->sendMail($s['email'], $subject, $html);
        }
    } catch (Exception $e) {
        if (MAIL_DEBUG) error_log("notifyStudentsNewEvent: " . $e->getMessage());
    }
}

/**
 * Notify ALL students about a new announcement.
 * Call after faculty/admin posts an announcement.
 */
function notifyStudentsAnnouncement(mysqli $conn, string $title, string $content, string $posterName, string $posterRole, ?string $eventName = null): void {
    try {
        $sq = $conn->query("SELECT email, full_name FROM students WHERE email != ''");
        $students = $sq->fetch_all(MYSQLI_ASSOC);
        if (empty($students)) return;

        $mailer  = new CEMSMailer();
        $subject = "CEMS Portal — Announcement: $title";

        foreach ($students as $s) {
            $html = emailTemplateAnnouncement($s['full_name'], $title, $content, $posterName, $posterRole, $eventName);
            $mailer->sendMail($s['email'], $subject, $html);
        }
    } catch (Exception $e) {
        if (MAIL_DEBUG) error_log("notifyStudentsAnnouncement: " . $e->getMessage());
    }
}

/**
 * Notify a single student that their registration is confirmed.
 */
function notifyStudentRegistered(mysqli $conn, int $student_id, int $event_id): void {
    try {
        $q = $conn->prepare("
            SELECT s.email, s.full_name,
                   e.event_name, e.event_date, e.event_time, e.venue,
                   f.full_name AS faculty_name, f.department
            FROM students s
            JOIN events e ON e.event_id = ?
            LEFT JOIN faculty f ON f.faculty_id = e.created_by
            WHERE s.student_id = ?
        ");
        $q->bind_param("ii", $event_id, $student_id); $q->execute();
        $row = $q->get_result()->fetch_assoc();
        if (!$row || !$row['email']) return;

        $mailer  = new CEMSMailer();
        $subject = "CEMS Portal — Registration Confirmed: {$row['event_name']}";
        $html    = emailTemplateRegistration($row);
        $mailer->sendMail($row['email'], $subject, $html);
    } catch (Exception $e) {
        if (MAIL_DEBUG) error_log("notifyStudentRegistered: " . $e->getMessage());
    }
}

// ─────────────────────────────────────────────────────────────
//  Email HTML Templates
// ─────────────────────────────────────────────────────────────

function emailBase(string $content): string {
    return <<<HTML
<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>
<body style="margin:0;padding:0;background:#f0f4ff;font-family:'Segoe UI',Arial,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f0f4ff;padding:30px 10px;">
<tr><td align="center">
<table width="600" cellpadding="0" cellspacing="0" style="background:white;border-radius:14px;overflow:hidden;box-shadow:0 4px 24px rgba(0,0,0,0.08);">

  <!-- Header -->
  <tr>
    <td style="background:linear-gradient(135deg,#1a237e,#283593,#1565c0);padding:28px 36px;text-align:center;">
      <div style="font-size:22px;font-weight:800;color:#ffd54f;letter-spacing:0.5px;">CEMS Portal</div>
      <div style="font-size:11px;color:rgba(255,255,255,0.7);letter-spacing:2px;margin-top:4px;">MARWADI UNIVERSITY</div>
    </td>
  </tr>

  <!-- Content -->
  <tr><td style="padding:32px 36px;">$content</td></tr>

  <!-- Footer -->
  <tr>
    <td style="background:#f8f9ff;padding:18px 36px;text-align:center;border-top:1px solid #e8ecff;">
      <div style="font-size:11px;color:#aaa;">College Event Management System &nbsp;·&nbsp; Marwadi University, Rajkot</div>
      <div style="font-size:10px;color:#ccc;margin-top:4px;">This is an automated notification. Please do not reply to this email.</div>
    </td>
  </tr>

</table>
</td></tr>
</table>
</body>
</html>
HTML;
}

function emailTemplateNewEvent(string $studentName, array $ev): string {
    $name     = htmlspecialchars($studentName);
    $evName   = htmlspecialchars($ev['event_name']);
    $date     = date('D, d M Y', strtotime($ev['event_date']));
    $time     = $ev['event_time'] ? date('h:i A', strtotime($ev['event_time'])) : 'TBA';
    $venue    = htmlspecialchars($ev['venue'] ?: 'College Campus');
    $desc     = htmlspecialchars($ev['description'] ?: '');
    $faculty  = htmlspecialchars($ev['faculty_name'] ?: 'Faculty');
    $dept     = $ev['department'] ? ' · ' . htmlspecialchars($ev['department']) : '';

    $desc_row = $desc ? '
      <tr>
        <td colspan="2" style="padding:14px 0 0;font-size:13px;color:#555;line-height:1.7;border-top:1px solid #e0e0e0;">
          ' . $desc . '
        </td>
      </tr>' : '';

    $content = <<<HTML
<!-- Icon + Greeting like Image 2 -->
<div style="text-align:center;margin-bottom:20px;">
  <div style="width:64px;height:64px;background:linear-gradient(135deg,#1a237e,#283593);border-radius:50%;
              display:inline-block;line-height:64px;text-align:center;">
    <span style="color:white;font-size:28px;line-height:64px;">&#128197;</span>
  </div>
</div>

<h2 style="margin:0 0 4px;color:#1a237e;font-size:22px;font-weight:800;text-align:center;">New Event Posted!</h2>
<p style="color:#666;margin:0 0 24px;font-size:14px;text-align:center;">Hey <strong>$name</strong>! A new event has been posted on the CEMS Portal. Check it out!</p>

<!-- Event Card — Green like registration card in Image 2 -->
<table width="100%" cellpadding="0" cellspacing="0"
  style="background:#f8f9ff;border:2px solid #c5cae9;border-radius:12px;overflow:hidden;margin-bottom:24px;">
  <tr>
    <td style="background:linear-gradient(135deg,#1a237e,#283593);padding:16px 24px;">
      <div style="font-size:17px;font-weight:800;color:white;">$evName</div>
      <div style="font-size:12px;color:rgba(255,255,255,0.75);margin-top:3px;">$faculty$dept</div>
    </td>
  </tr>
  <tr>
    <td style="padding:20px 24px;">
      <table width="100%" cellpadding="6" cellspacing="0" style="font-size:13px;color:#555;">
        <tr>
          <td style="font-weight:700;color:#1a237e;width:80px;">📅 Date</td>
          <td>$date</td>
          <td style="font-weight:700;color:#1a237e;width:70px;">⏰ Time</td>
          <td>$time</td>
        </tr>
        <tr>
          <td style="font-weight:700;color:#1a237e;">📍 Venue</td>
          <td colspan="3">$venue</td>
        </tr>
        $desc_row
      </table>
    </td>
  </tr>
</table>

<div style="text-align:center;margin-bottom:8px;">
  <a href="http://localhost/CEMS_PROJECT_FIXED/auth/login.php?redirect=events"
     style="display:inline-block;background:linear-gradient(135deg,#1a237e,#283593);color:white;
            padding:13px 32px;border-radius:8px;font-weight:700;font-size:14px;text-decoration:none;">
    Register for this Event
  </a>
</div>
<p style="text-align:center;font-size:12px;color:#aaa;">Login to CEMS Portal to view My Registrations</p>
HTML;

    return emailBase($content);
}

function emailTemplateAnnouncement(string $studentName, string $title, string $content, string $poster, string $role, ?string $eventName): string {
    $name    = htmlspecialchars($studentName);
    $title   = htmlspecialchars($title);
    $content = nl2br(htmlspecialchars($content));
    $poster  = htmlspecialchars($poster);
    $role    = ucfirst(htmlspecialchars($role));
    $tag     = $eventName ? '<span style="background:#e8eaf6;color:#1a237e;padding:3px 10px;border-radius:20px;font-size:11px;font-weight:700;">' . htmlspecialchars($eventName) . '</span>' : '';

    $body = <<<HTML
<h2 style="margin:0 0 6px;color:#1a237e;font-size:20px;">Hello $name! 📢</h2>
<p style="color:#666;margin:0 0 24px;font-size:14px;">A new announcement has been posted on the CEMS Portal.</p>

<table width="100%" cellpadding="0" cellspacing="0"
  style="border:1px solid #e0e0e0;border-radius:12px;overflow:hidden;margin-bottom:24px;">
  <tr>
    <td style="background:#1a237e;padding:14px 22px;">
      <div style="font-size:16px;font-weight:800;color:white;">$title</div>
      $tag
    </td>
  </tr>
  <tr>
    <td style="padding:20px 22px;font-size:14px;color:#444;line-height:1.75;">
      $content
    </td>
  </tr>
  <tr>
    <td style="padding:12px 22px;background:#f8f9ff;border-top:1px solid #eee;font-size:12px;color:#999;">
      Posted by <strong style="color:#1a237e;">$poster</strong> · $role
    </td>
  </tr>
</table>

<div style="text-align:center;">
  <a href="http://localhost/CEMS_PROJECT_FIXED/auth/login.php"
     style="display:inline-block;background:linear-gradient(135deg,#1a237e,#283593);color:white;
            padding:12px 28px;border-radius:8px;font-weight:700;font-size:14px;text-decoration:none;">
    Login to View Announcements
  </a>
</div>
HTML;

    return emailBase($body);
}

function emailTemplateRegistration(array $row): string {
    $name    = htmlspecialchars($row['full_name']);
    $evName  = htmlspecialchars($row['event_name']);
    $date    = date('D, d M Y', strtotime($row['event_date']));
    $time    = $row['event_time'] ? date('h:i A', strtotime($row['event_time'])) : 'TBA';
    $venue   = htmlspecialchars($row['venue'] ?: 'College Campus');
    $faculty = htmlspecialchars($row['faculty_name'] ?? '');
    $dept    = htmlspecialchars($row['department'] ?? '');
    $faculty_sep  = ($faculty && $dept) ? " &middot; $dept" : '';
    $faculty_line = $faculty ? "{$faculty}{$faculty_sep}" : 'Not specified';

    $body = <<<HTML
<div style="text-align:center;margin-bottom:24px;">
  <div style="width:64px;height:64px;background:linear-gradient(135deg,#2e7d32,#388e3c);border-radius:50%;
              display:inline-block;line-height:64px;text-align:center;">
    <span style="color:white;font-size:32px;font-weight:900;line-height:64px;">&#10003;</span>
  </div>
</div>

<h2 style="margin:0 0 6px;color:#1a237e;font-size:20px;text-align:center;">You're Registered!</h2>
<p style="color:#666;margin:0 0 24px;font-size:14px;text-align:center;">
  Hi <strong>$name</strong>, your registration is confirmed. See you there!
</p>

<table width="100%" cellpadding="0" cellspacing="0"
  style="background:#f8fff8;border:2px solid #c8e6c9;border-radius:12px;overflow:hidden;margin-bottom:24px;">
  <tr>
    <td style="background:linear-gradient(135deg,#2e7d32,#388e3c);padding:16px 24px;">
      <div style="font-size:17px;font-weight:800;color:white;">$evName</div>
    </td>
  </tr>
  <tr>
    <td style="padding:20px 24px;">
      <table width="100%" cellpadding="4" cellspacing="0" style="font-size:13px;color:#555;">
        <tr><td><strong style="color:#2e7d32;">📅 Date</strong></td><td>$date</td></tr>
        <tr><td><strong style="color:#2e7d32;">⏰ Time</strong></td><td>$time</td></tr>
        <tr><td><strong style="color:#2e7d32;">📍 Venue</strong></td><td>$venue</td></tr>
        <tr><td><strong style="color:#2e7d32;">👨‍🏫 Faculty</strong></td><td>$faculty_line</td></tr>
      </table>
    </td>
  </tr>
</table>

<div style="text-align:center;">
  <a href="http://localhost/CEMS_PROJECT_FIXED/auth/login.php"
     style="display:inline-block;background:linear-gradient(135deg,#1a237e,#283593);color:white;
            padding:12px 28px;border-radius:8px;font-weight:700;font-size:14px;text-decoration:none;">
    Login to View My Registrations
  </a>
</div>
HTML;

    return emailBase($body);
}
