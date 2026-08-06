<?php
// ============================================================
// VerifiedSMS API configuration
// ------------------------------------------------------------
// 1. Register at https://verifiedsms.com/register.php
// 2. Log in and generate a key at
//    https://verifiedsms.com/dashboard/generate_key.php
// 3. Paste it below
// 4. Contact support@hamro.com to top up SMS balance
// ============================================================

define('SMS_API_KEY', 'VERIFIEDSMScddc0f3ad2d8929d2d06159333295c0d');
define('SMS_API_URL', 'https://verifiedsms.com/api/v1/send.php');

// Set to false in production
define('SMS_DEBUG', true);

// Test the API connection
function testSMSAPI() {
    $testData = [
        'key' => SMS_API_KEY,
        'destination' => '9779845230513', // Test number
        'message' => 'Test message from HDC Votes'
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, SMS_API_URL);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($testData));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    return [
        'http_code' => $httpCode,
        'response' => json_decode($response, true),
        'raw_response' => $response
    ];
}
?>