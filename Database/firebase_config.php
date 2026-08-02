<?php
// ============================================================
// Firebase Phone Authentication configuration
// ------------------------------------------------------------
// This REPLACES the VerifiedSMS integration. Firebase (Google) sends
// the actual OTP SMS directly from the browser via their JS SDK —
// completely free within their generous free quota, and far more
// reliable than the broken VerifiedSMS account we were fighting.
//
// SETUP (one-time, ~5 minutes):
// 1. Go to https://console.firebase.google.com and click
//    "Add project" (it's free — no credit card required for this).
// 2. Once created, in the left sidebar go to
//    Build > Authentication > "Get started".
// 3. Under the "Sign-in method" tab, enable the "Phone" provider.
// 4. Click the gear icon (top-left) > "Project settings".
// 5. Under the "General" tab, scroll to "Your apps" and click the
//    </> (Web) icon to register a new web app (any nickname is fine,
//    you don't need Firebase Hosting).
// 6. Firebase will show you a config object like:
//      apiKey: "AIzaSyC...",
//      authDomain: "yourproject.firebaseapp.com",
//      projectId: "yourproject",
//      appId: "1:1234567890:web:abc123def456"
//    Copy those 4 values into the constants below.
// 7. Still in Authentication, go to the "Settings" tab >
//    "Authorized domains", and add whatever domain this app runs on
//    (e.g. "localhost" for local testing, "yourcollege.com" for live).
//    Without this step, Firebase will refuse to send SMS from your site.
// ============================================================

define('FIREBASE_API_KEY', 'AIzaSyD2Zl80J-jtX05bPV1k43N9qREZFFt2H9k');
define('FIREBASE_AUTH_DOMAIN', 'hdc-votes.firebaseapp.com');
define('FIREBASE_PROJECT_ID', 'hdc-votes');
define('FIREBASE_APP_ID', '1:97897412802:web:c7495543b3b4e4036d235b');
