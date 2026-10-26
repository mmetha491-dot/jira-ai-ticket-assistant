
<?php
$siteUrl = 'https://emailtext21321.atlassian.net';
$email = getenv('emailtext21321@gmail.com');
$apiToken = getenv('ATATT3xFfGF0YC5dxwagGcZu8d-IsD-l8XJcmb-0qm3Qm9GALdcDMLhy7Nmv45vFPtjjG80h7kzoTL5yBSX7VY32Abq9zUi3GUuiaLq7uR6LtmazmE0c4wyT_P5synAAOzrqbq9um1hj3Tf2G3st4dcvIAl-9wcR3DbalCS2DyozIOYuEMPA29w=A4B2BF75');

if (!$email || !$apiToken) {
    exit("Missing JIRA_EMAIL or JIRA_API_TOKEN environment variable.\n");
}

$ch = curl_init($siteUrl . '/rest/api/3/myself');

curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_USERPWD => $email . ':' . $apiToken,
    CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
    CURLOPT_HTTPHEADER => ['Accept: application/json'],
    CURLOPT_TIMEOUT => 20
]);

$response = curl_exec($ch);
$status = curl_getinfo($ch, CURLINFO_HTTP_CODE);

if ($response === false) {
    exit("Connection error: " . curl_error($ch) . "\n");
}

curl_close($ch);

if ($status === 200) {
    $user = json_decode($response, true);
    echo "Jira connection successful!\n";
    echo "Authenticated account: " . ($user['displayName'] ?? 'Connected') . "\n";
} else {
    echo "Jira returned HTTP status: " . $status . "\n";
    echo "Check your credentials, token permissions, and site URL.\n";
}
