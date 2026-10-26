
<?php
$config = require __DIR__ . '/jira-config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Please submit a ticket using the ticket creation form.');
}

$summary = trim($_POST['summary'] ?? '');
$description = trim($_POST['description'] ?? '');
$issueType = trim($_POST['issue_type'] ?? 'Task');
$priority = trim($_POST['priority'] ?? 'Medium');

$issueTypeIds = [
    'Epic' => '10006',
    'Subtask' => '10007',
    'Task' => '10008',
    'Story' => '10009'
];
$allowedPriorities = ['Highest', 'High', 'Medium', 'Low', 'Lowest'];

if ($summary === '' || $description === '') {
    exit('Summary and description are required.');
}

if (!isset($issueTypeIds[$issueType])) {
    exit('Invalid issue type for project KAN.');
}

if (!in_array($priority, $allowedPriorities, true)) {
    exit('Invalid priority.');
}

// Convert the description into Jira Cloud's document format.
$paragraphs = preg_split('/\R/', $description);
$content = [];

foreach ($paragraphs as $line) {
    $content[] = [
        'type' => 'paragraph',
        'content' => [
            [
                'type' => 'text',
                'text' => $line !== '' ? $line : ' '
            ]
        ]
    ];
}

$payload = [
    'fields' => [
        'project' => [
            'key' => 'KAN'
        ],
        'summary' => $summary,
        'description' => [
            'type' => 'doc',
            'version' => 1,
            'content' => $content
        ],
        'issuetype' => [
    'id' => $issueTypeIds[$issueType]
],
        'priority' => [
            'name' => $priority
        ]
    ]
];

$url = rtrim($config['site_url'], '/') . '/rest/api/3/issue';

$ch = curl_init($url);

curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_USERPWD => $config['email'] . ':' . $config['api_token'],
    CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
    CURLOPT_HTTPHEADER => [
        'Accept: application/json',
        'Content-Type: application/json'
    ],
    CURLOPT_POSTFIELDS => json_encode($payload),
    CURLOPT_CONNECTTIMEOUT => 10,
    CURLOPT_TIMEOUT => 30
]);

$response = curl_exec($ch);
$curlError = curl_error($ch);
$statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

curl_close($ch);

header('Content-Type: text/html; charset=UTF-8');

function escapeOutput($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

echo '<!DOCTYPE html><html><head><meta charset="UTF-8">';
echo '<meta name="viewport" content="width=device-width, initial-scale=1">';
echo '<title>Jira Ticket Result</title></head><body>';
echo '<h2>Jira Ticket Creation</h2>';

if ($response === false) {
    echo '<p>Connection error: ' . escapeOutput($curlError) . '</p>';
} else {
    $result = json_decode($response, true);

    if ($statusCode >= 200 && $statusCode < 300 && !empty($result['key'])) {
        echo '<p>Ticket created successfully!</p>';
        echo '<p>Issue Key: <strong>' . escapeOutput($result['key']) . '</strong></p>';

        $issueUrl = rtrim($config['site_url'], '/') . '/browse/' .
                    rawurlencode($result['key']);

        echo '<p><a href="' . escapeOutput($issueUrl) .
             '" target="_blank" rel="noopener noreferrer">Open ticket in Jira</a></p>';
    } else {
        echo '<p>Jira could not create the ticket.</p>';
        echo '<p>HTTP status: ' . escapeOutput($statusCode) . '</p>';

        if (isset($result['errors'])) {
            echo '<pre>' . escapeOutput(
                json_encode($result['errors'], JSON_PRETTY_PRINT)
            ) . '</pre>';
        }

        if (isset($result['errorMessages'])) {
            echo '<pre>' . escapeOutput(
                implode("\n", $result['errorMessages'])
            ) . '</pre>';
        }

        if (!$result) {
            echo '<p>Response: ' . escapeOutput($response) . '</p>';
        }
    }
}

echo '</body></html>';
