
<?php

$siteUrl = 'https://emailtext21321.atlassian.net';
$projectKey = 'KAN';

$config = require __DIR__ . '/jira-config.php';

$email = $config['email'];
$apiToken = $config['api_token'];

$url = $siteUrl . '/rest/api/3/search/jql?' . http_build_query([
    'jql' => 'project = ' . $projectKey . ' ORDER BY created DESC',
    'maxResults' => 10,
    'fields' => 'summary,status,priority,issuetype'
]);

$ch = curl_init($url);

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

$data = json_decode($response, true);

if ($status !== 200) {
    echo "Jira API returned HTTP status: " . $status . "\n";
    echo $data['errorMessages'][0] ?? $response;
    exit("\n");
}

$issues = $data['issues'] ?? [];

echo "Jira connection successful!\n";
echo "Project: " . $projectKey . "\n";
echo "Issues found: " . count($issues) . "\n\n";

foreach ($issues as $issue) {
    echo "Issue Key: " . $issue['key'] . "\n";
    echo "Summary: " . $issue['fields']['summary'] . "\n";
    echo "Status: " . $issue['fields']['status']['name'] . "\n";
    echo "Priority: "
        . ($issue['fields']['priority']['name'] ?? 'Not set') . "\n";
    echo "Type: " . $issue['fields']['issuetype']['name'] . "\n";
    echo str_repeat('-', 40) . "\n";
}
