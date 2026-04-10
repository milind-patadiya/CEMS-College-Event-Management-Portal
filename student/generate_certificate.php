<?php
session_start();
require_once("../config/db.php");
if (!isset($_SESSION['student_id'])) { header("Location: ../auth/login.php"); exit(); }

$student_id    = (int)$_SESSION['student_id'];
$certificate_id = (int)($_GET['cert_id'] ?? 0);

if (!$certificate_id) { header("Location: certificates.php"); exit(); }

// Verify this certificate belongs to this student AND they were Present
$q = $conn->prepare("
    SELECT c.certificate_id, c.issued_date,
           s.full_name AS student_name, s.enrollment_no,
           e.event_name, e.event_date, e.venue,
           f.full_name AS faculty_name, f.department,
           a.status AS att_status
    FROM certificates c
    JOIN students  s ON s.student_id = c.student_id
    JOIN events    e ON e.event_id   = c.event_id
    LEFT JOIN faculty f ON f.faculty_id = e.created_by
    LEFT JOIN attendance a ON a.student_id = c.student_id AND a.event_id = c.event_id
    WHERE c.certificate_id = ? AND c.student_id = ? AND a.status = 'Present'
");
$q->bind_param("ii", $certificate_id, $student_id);
$q->execute();
$cert = $q->get_result()->fetch_assoc();

if (!$cert) { header("Location: certificates.php"); exit(); }

$student_name = htmlspecialchars($cert['student_name']);
$enrollment   = htmlspecialchars($cert['enrollment_no']);
$event_name   = htmlspecialchars($cert['event_name']);
$event_date   = date('d F Y', strtotime($cert['event_date']));
$issued_date  = date('d F Y', strtotime($cert['issued_date']));
$venue        = htmlspecialchars($cert['venue'] ?: 'College Campus');
$faculty_name = htmlspecialchars($cert['faculty_name'] ?: 'Faculty Coordinator');
$department   = htmlspecialchars($cert['department'] ?: '');
$cert_no      = 'CEMS-' . date('Y') . '-' . str_pad($cert['certificate_id'], 5, '0', STR_PAD_LEFT);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Certificate — <?= $student_name ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,700;1,400&family=Lato:wght@300;400;700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            background: #f0f0f0;
            font-family: 'Lato', sans-serif;
            display: flex;
            flex-direction: column;
            align-items: center;
            min-height: 100vh;
            padding: 30px 20px;
        }

        /* ── Action Bar (hidden on print) ── */
        .action-bar {
            width: 100%;
            max-width: 900px;
            display: flex;
            gap: 12px;
            margin-bottom: 20px;
            align-items: center;
        }
        .btn-action {
            display: inline-flex; align-items: center; gap: 8px;
            padding: 10px 22px; border-radius: 8px;
            font-family: 'Lato', sans-serif; font-weight: 700; font-size: 0.9rem;
            cursor: pointer; border: none; text-decoration: none; transition: 0.2s;
        }
        .btn-print  { background: #1a237e; color: white; }
        .btn-print:hover  { background: #283593; }
        .btn-back   { background: white; color: #555; border: 1.5px solid #ddd; }
        .btn-back:hover { background: #f5f5f5; }
        .cert-id-label { margin-left: auto; font-size: 0.8rem; color: #888; }

        /* ── Certificate Paper ── */
        .certificate {
            width: 900px;
            background: white;
            position: relative;
            overflow: hidden;
            box-shadow: 0 8px 40px rgba(0,0,0,0.18);
        }

        /* Outer border */
        .cert-outer-border {
            position: absolute; inset: 12px;
            border: 3px solid #1a237e;
            pointer-events: none; z-index: 2;
        }
        .cert-inner-border {
            position: absolute; inset: 18px;
            border: 1px solid #c5a028;
            pointer-events: none; z-index: 2;
        }

        /* Corner ornaments */
        .corner {
            position: absolute; width: 50px; height: 50px;
            z-index: 3;
        }
        .corner-tl { top: 10px; left: 10px; border-top: 4px solid #c5a028; border-left: 4px solid #c5a028; }
        .corner-tr { top: 10px; right: 10px; border-top: 4px solid #c5a028; border-right: 4px solid #c5a028; }
        .corner-bl { bottom: 10px; left: 10px; border-bottom: 4px solid #c5a028; border-left: 4px solid #c5a028; }
        .corner-br { bottom: 10px; right: 10px; border-bottom: 4px solid #c5a028; border-right: 4px solid #c5a028; }

        /* Background watermark */
        .cert-watermark {
            position: absolute; inset: 0;
            display: flex; align-items: center; justify-content: center;
            pointer-events: none; z-index: 1;
            opacity: 0.04;
            font-family: 'Playfair Display', serif;
            font-size: 160px; font-weight: 700;
            color: #1a237e;
            transform: rotate(-30deg);
            letter-spacing: -8px;
        }

        /* Header band */
        .cert-header-band {
            background: linear-gradient(135deg, #1a237e 0%, #283593 60%, #1565c0 100%);
            padding: 28px 60px 22px;
            text-align: center;
            position: relative; z-index: 3;
        }
        .cert-college-name {
            font-family: 'Playfair Display', serif;
            font-size: 1.5rem; font-weight: 700;
            color: #ffd54f; letter-spacing: 1px;
            margin-bottom: 4px;
        }
        .cert-portal-name {
            font-size: 0.78rem; color: rgba(255,255,255,0.75);
            text-transform: uppercase; letter-spacing: 3px;
        }

        /* Gold divider */
        .gold-divider {
            height: 4px;
            background: linear-gradient(90deg, transparent, #c5a028 20%, #ffd54f 50%, #c5a028 80%, transparent);
        }

        /* Body */
        .cert-body {
            padding: 40px 70px 36px;
            text-align: center;
            position: relative; z-index: 3;
        }

        .cert-type {
            font-family: 'Playfair Display', serif;
            font-size: 2.6rem; font-weight: 400; font-style: italic;
            color: #1a237e; letter-spacing: 1px;
            margin-bottom: 6px;
        }
        .cert-of-participation {
            font-size: 0.72rem; font-weight: 700;
            text-transform: uppercase; letter-spacing: 5px;
            color: #888; margin-bottom: 28px;
        }

        .cert-presented-to {
            font-size: 0.85rem; color: #666;
            text-transform: uppercase; letter-spacing: 2px;
            margin-bottom: 6px;
        }
        .cert-student-name {
            font-family: 'Playfair Display', serif;
            font-size: 2.4rem; font-weight: 700;
            color: #c5a028;
            border-bottom: 2px solid #e8d5a0;
            padding-bottom: 8px;
            margin-bottom: 6px;
            display: inline-block;
        }
        .cert-enrollment {
            font-size: 0.8rem; color: #999;
            margin-bottom: 24px;
        }

        .cert-body-text {
            font-size: 1rem; color: #444; line-height: 1.9;
            max-width: 620px; margin: 0 auto 28px;
        }
        .cert-body-text strong {
            color: #1a237e; font-weight: 700;
        }

        /* Detail pills */
        .cert-details {
            display: flex; justify-content: center; gap: 30px;
            margin-bottom: 36px; flex-wrap: wrap;
        }
        .cert-detail-item {
            text-align: center;
        }
        .cert-detail-label {
            font-size: 0.65rem; text-transform: uppercase;
            letter-spacing: 1.5px; color: #aaa; margin-bottom: 3px;
        }
        .cert-detail-value {
            font-size: 0.88rem; font-weight: 700; color: #333;
        }

        /* Signature row */
        .cert-signature-row {
            display: flex; justify-content: space-between;
            align-items: flex-end;
            padding: 0 30px;
            margin-top: 8px;
        }
        .cert-sig-block { text-align: center; min-width: 160px; }
        .cert-sig-line {
            width: 150px; height: 1.5px;
            background: #333; margin: 0 auto 6px;
        }
        .cert-sig-name { font-size: 0.82rem; font-weight: 700; color: #333; }
        .cert-sig-title { font-size: 0.72rem; color: #888; }

        /* Seal */
        .cert-seal {
            width: 80px; height: 80px; border-radius: 50%;
            background: linear-gradient(135deg, #1a237e, #283593);
            display: flex; align-items: center; justify-content: center;
            flex-direction: column;
            border: 3px solid #c5a028;
            box-shadow: 0 0 0 2px #1a237e;
        }
        .cert-seal i { font-size: 1.6rem; color: #ffd54f; }
        .cert-seal span { font-size: 0.45rem; color: rgba(255,255,255,0.7); text-transform: uppercase; letter-spacing: 1px; margin-top: 2px; }

        /* Footer band */
        .cert-footer-band {
            background: linear-gradient(135deg, #1a237e, #283593);
            padding: 10px 60px;
            display: flex; justify-content: space-between; align-items: center;
            position: relative; z-index: 3;
        }
        .cert-footer-band span {
            font-size: 0.72rem; color: rgba(255,255,255,0.6);
            letter-spacing: 0.5px;
        }
        .cert-id-band {
            font-family: 'Lato', monospace;
            font-size: 0.7rem; color: #ffd54f; letter-spacing: 1px;
        }

        /* ── Print styles ── */
        @media print {
            body { background: white; padding: 0; }
            .action-bar { display: none !important; }
            .certificate {
                width: 100%; box-shadow: none;
                page-break-inside: avoid;
            }
            @page { size: A4 landscape; margin: 0; }
        }
    </style>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
</head>
<body>

<!-- Action Bar -->
<div class="action-bar">
    <a href="certificates.php" class="btn-action btn-back">
        <i class="fas fa-arrow-left"></i> Back
    </a>
    <button onclick="window.print()" class="btn-action btn-print">
        <i class="fas fa-print"></i> Print / Save as PDF
    </button>
    <span class="cert-id-label">Certificate No: <strong><?= $cert_no ?></strong></span>
</div>

<!-- Certificate -->
<div class="certificate" id="certificate">

    <!-- Decorative borders & corners -->
    <div class="cert-outer-border"></div>
    <div class="cert-inner-border"></div>
    <div class="corner corner-tl"></div>
    <div class="corner corner-tr"></div>
    <div class="corner corner-bl"></div>
    <div class="corner corner-br"></div>
    <div class="cert-watermark">CEMS</div>

    <!-- Header -->
    <div class="cert-header-band">
        <div class="cert-college-name">Marwadi University</div>
        <div class="cert-portal-name">College Event Management System &nbsp;·&nbsp; CEMS</div>
    </div>
    <div class="gold-divider"></div>

    <!-- Body -->
    <div class="cert-body">

        <div class="cert-type">Certificate</div>
        <div class="cert-of-participation">of Participation</div>

        <div class="cert-presented-to">This is to certify that</div>
        <div class="cert-student-name"><?= $student_name ?></div>
        <div class="cert-enrollment">Enrollment No: <?= $enrollment ?></div>

        <div class="cert-body-text">
            has successfully participated in the event
            <strong><?= $event_name ?></strong>
            held on <strong><?= $event_date ?></strong>
            <?php if ($venue): ?>at <strong><?= $venue ?></strong><?php endif; ?>
            and has demonstrated commendable enthusiasm and commitment.
        </div>

        <!-- Details pills -->
        <div class="cert-details">
            <div class="cert-detail-item">
                <div class="cert-detail-label">Event Date</div>
                <div class="cert-detail-value"><?= $event_date ?></div>
            </div>
            <div class="cert-detail-item">
                <div class="cert-detail-label">Venue</div>
                <div class="cert-detail-value"><?= $venue ?></div>
            </div>
            <div class="cert-detail-item">
                <div class="cert-detail-label">Issued On</div>
                <div class="cert-detail-value"><?= $issued_date ?></div>
            </div>
        </div>

        <!-- Signatures -->
        <div class="cert-signature-row">
            <div class="cert-sig-block">
                <div class="cert-sig-line"></div>
                <div class="cert-sig-name"><?= $faculty_name ?></div>
                <div class="cert-sig-title">Event Coordinator<?= $department ? ', ' . $department : '' ?></div>
            </div>

            <div class="cert-seal">
                <i class="fas fa-award"></i>
                <span>Certified</span>
            </div>

            <div class="cert-sig-block">
                <div class="cert-sig-line"></div>
                <div class="cert-sig-name">Admin / HOD</div>
                <div class="cert-sig-title">Marwadi University, CEMS</div>
            </div>
        </div>

    </div>

    <!-- Footer -->
    <div class="gold-divider"></div>
    <div class="cert-footer-band">
        <span>Marwadi University &nbsp;·&nbsp; College Event Management System</span>
        <span class="cert-id-band"><?= $cert_no ?></span>
        <span>Generated: <?= date('d M Y') ?></span>
    </div>

</div>

<script>
// Auto-trigger print dialog if ?print=1 in URL
if (new URLSearchParams(window.location.search).get('print') === '1') {
    window.addEventListener('load', () => setTimeout(() => window.print(), 500));
}
</script>
</body>
</html>
