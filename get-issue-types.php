
<?php
$config = require __DIR__ . '/jira-config.php';

$url = rtrim($config['site_url'], '/') .
       '/rest/api/3/issuetype/project?projectId=10000';

// Get the project ID using the project key instead of assuming its ID.
$projectUrl = rtrim($config['site_url'], '/') .
              '/rest/api/3/project/KAN';

function jiraGet($url, $config) {
    $ch = curl_init($url);

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_USERPWD => $config['email'] . ':' . $config['api_token'],
        CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
        CURLOPT_HTTPHEADER => [
            'Accept: application/json'
        ],
        CURLOPT_TIMEOUT => 30
    ]);

    $response = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    return [$response, $status, $error];
}

[$projectResponse, $projectStatus, $projectError] =
    jiraGet($projectUrl, $config);

if ($projectResponse === false || $projectStatus !== 200) {
    exit(
        'Could not retrieve project details. HTTP status: ' .
        htmlspecialchars((string)$projectStatus, ENT_QUOTES, 'UTF-8')
    );
}

$project = json_decode($projectResponse, true);
$projectId = $project['id'] ?? '';

if ($projectId === '') {
    exit('Could not determine the Jira project ID.');
}

$url = rtrim($config['site_url'], '/') .
       '/rest/api/3/issuetype/project?projectId=' .
       rawurlencode($projectId);

[$response, $status, $error] = jiraGet($url, $config);

header('Content-Type: text/plain; charset=UTF-8');

if ($response === false) {
    exit('Connection error: ' . $error);
}

echo "HTTP status: " . $status . "\n\n";

$data = json_decode($response, true);

if ($status === 200 && is_array($data)) {
    echo "Issue types available for project KAN:\n\n";

    foreach ($data as $type) {
        echo "Name: " . ($type['name'] ?? 'Unknown') . "\n";
        echo "ID: " . ($type['id'] ?? 'Unknown') . "\n";
        echo "Subtask: " . (!empty($type['subtask']) ? 'Yes' : 'No') . "\n";
        echo "------------------------\n";
    }
} else {
    echo "Jira returned an error:\n" . $response;
}
?>
