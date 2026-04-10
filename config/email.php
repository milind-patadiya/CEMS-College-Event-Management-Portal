<?php
// ─────────────────────────────────────────────────────────────
//  CEMS — Email Configuration
//
//  Gmail App Password Setup:
//  1. Go to https://myaccount.google.com/security
//  2. Enable 2-Step Verification
//  3. Search "App Passwords" → Create → Name it "CEMS"
//  4. Copy the 16-char password → paste in MAIL_PASS below
// ─────────────────────────────────────────────────────────────

define('MAIL_HOST',     'smtp.gmail.com');
define('MAIL_PORT',     587);
define('MAIL_USERNAME', 'portal.cems@gmail.com');
define('MAIL_PASS',     'vccw jjne nrpj lhjf');   // ← Paste App Password here
define('MAIL_FROM',     'portal.cems@gmail.com');
define('MAIL_FROM_NAME','CEMS Portal');

// Set to true to log email errors (for testing)
define('MAIL_DEBUG', true);
