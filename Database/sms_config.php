<?php
// ============================================================
// VerifiedSMS API configuration
// ------------------------------------------------------------
// 1. Register at https://verifiedsms.com/register.php
// 2. Log in and generate a key at
//    https://verifiedsms.com/dashboard/generate_key.php
// 3. Paste it below (looks like: VERIFIEDSMSa1b2c3d4e5f6a1b2c3d4e5f6a1b2c3d4)
// 4. Contact support@hamro.com to top up SMS balance
//    (1 balance point = 1 SMS)
// ============================================================

define('SMS_API_KEY', 'VERIFIEDSMScddc0f3ad2d8929d2d06159333295c0d');
define('SMS_API_URL', 'https://verifiedsms.com/api/v1/send.php');

// Set this to true while you're setting things up so student_verify.php
// shows you the REAL reason an SMS failed (bad key, no balance, bad
// number, network issue, etc.) instead of the generic student-facing
// message. Set it back to false before this goes live for real voting,
// so students never see raw API error text.
define('SMS_DEBUG', true);
