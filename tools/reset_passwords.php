<?php
/**
 * CEMS — Password Reset Helper
 * Run this ONCE via browser: http://localhost/CEMS_PROJECT/tools/reset_passwords.php
 * DELETE this file immediately after use.
 */

// ── Safety key — change this before running ──
define('SECRET_KEY', 'cems_reset_2025');

if (($_GET['key'] ?? '') !== SECRET_KEY) {
    die('<h2 style="color:red;font-family:sans-serif;">Access Denied. Pass ?key=cems_reset_2025 in URL.</h2>');
}

require_once("../config/db.php");

$accounts = [
    'admin'      => ['table'=>'admins',  'id_col'=>'admin_id',   'user_col'=>'username', 'password'=>'admin123'],
    'amit_shah'  => ['table'=>'faculty', 'id_col'=>'faculty_id', 'user_col'=>'username', 'password'=>'admin123'],
    'priya_mehta'=> ['table'=>'faculty', 'id_col'=>'faculty_id', 'user_col'=>'username', 'password'=>'admin123'],
];

$results = [];
foreach ($accounts as $username => $info) {
    $hash  = password_hash($info['password'], PASSWORD_BCRYPT);
    $table = $info['table'];
    $ucol  = $info['user_col'];
    $stmt  = $conn->prepare("UPDATE {$table} SET password = ? WHERE {$ucol} = ?");
    $stmt->bind_param("ss", $hash, $username);
    $stmt->execute();
    $results[] = [
        'username' => $username,
        'table'    => $table,
        'rows'     => $stmt->affected_rows,
        'hash'     => $hash
    ];
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Password Reset — CEMS</title>
    <style>
        body { font-family: sans-serif; max-width: 780px; margin: 40px auto; padding: 20px; background: #f4f7fa; }
        h2   { color: #1a237e; }
        table { width: 100%; border-collapse: collapse; background: white; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.08); }
        th   { background: #1a237e; color: white; padding: 12px 16px; text-align: left; font-size: 13px; }
        td   { padding: 11px 16px; border-bottom: 1px solid #eee; font-size: 13px; font-family: monospace; }
        tr:last-child td { border: none; }
        .ok  { color: #2e7d32; font-weight: bold; }
        .err { color: #c62828; font-weight: bold; }
        .warn { background: #fff3e0; border: 1px solid #ffa726; border-radius: 6px; padding: 14px 18px; margin-top: 20px; font-size: 13px; color: #e65100; }
    </style>
</head>
<body>
<h2>✅ CEMS — Password Reset Complete</h2>
<p>All default account passwords have been reset to <strong>admin123</strong> using PHP's <code>password_hash()</code>.</p>
<table>
    <tr><th>Username</th><th>Table</th><th>Status</th><th>New Hash (bcrypt)</th></tr>
    <?php foreach ($results as $r): ?>
    <tr>
        <td><?= htmlspecialchars($r['username']) ?></td>
        <td><?= htmlspecialchars($r['table']) ?></td>
        <td class="<?= $r['rows'] > 0 ? 'ok' : 'err' ?>">
            <?= $r['rows'] > 0 ? '✔ Updated' : '✘ Not found' ?>
        </td>
        <td style="font-size:11px;word-break:break-all;"><?= htmlspecialchars($r['hash']) ?></td>
    </tr>
    <?php endforeach; ?>
</table>
<div class="warn">
    ⚠️ <strong>Delete this file immediately after use!</strong><br>
    Path: <code><?= __FILE__ ?></code>
</div>
</body>
</html>