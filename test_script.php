<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require 'vendor/autoload.php';
require 'includes/config.php';

use RingCentral\SDK\SDK;

echo "<h2>JWT Authentication Test</h2>";

try {
    // Initialize SDK with fresh instance
    $rcsdk = new SDK(
        $ringcentralConfig['clientId'],
        $ringcentralConfig['clientSecret'],
        $ringcentralConfig['server']
    );
    
    $platform = $rcsdk->platform();
    
    // Direct JWT login
    echo "<p>Attempting direct JWT authentication...</p>";
    $auth = $platform->login([
        'jwt' => $ringcentralConfig['jwt']
    ]);
    
    echo "<p>Authentication response received</p>";
    
    // Verify authentication
    $response = $platform->get('/restapi/v1.0/account/~');
    
    echo "<h3 style='color:green'>Authentication Successful!</h3>";
    echo "<pre>Account Info: " . print_r($response->json(), true) . "</pre>";

} catch (Exception $e) {
    echo "<h3 style='color:red'>Authentication Failed</h3>";
    echo "<p><strong>Error:</strong> " . $e->getMessage() . "</p>";
    echo "<p><strong>Trace:</strong><br>" . nl2br($e->getTraceAsString()) . "</p>";
    
    // Additional debug info
    echo "<h4>JWT Debug:</h4>";
    echo "<pre>JWT: " . substr($ringcentralConfig['jwt'], 0, 50) . "...</pre>";
    echo "<p>JWT Length: " . strlen($ringcentralConfig['jwt']) . " characters</p>";
}