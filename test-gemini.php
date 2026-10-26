
<?php

$apiKey = getenv('GEMINI_API_KEY');

if (!$apiKey) {
    exit("Missing GEMINI_API_KEY environment variable.\n");
}

$url = 'https://generativelanguage.googleapis.com/v1beta/interactions';

$payload = [
    'model' => 'gemini-3.8-flash',
    'input' => 'You are a Jira ticket assistant. Convert this bug report into a concise ticket title and description: The login page shows an unexpected error when a user enters an incorrect password.',
    'store' => false
];

$ch = curl_init($url);

curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'x-goog-api-key: ' . $apiKey
    ],
    CURLOPT_POSTFIELDS => json_encode($payload),
    CURLOPT_TIMEOUT => 60
]);

$response = curl_exec($ch);
$status = curl_getinfo($ch, CURLINFO_HTTP_CODE);

if ($response === false) {
    $error = curl_error($ch);
    curl_close($ch);
    exit("Connection error: " . $error . "\n");
}

curl_close($ch);

$data = json_decode($response, true);

echo "HTTP Status: " . $status . "\n\n";

if ($status >= 200 && $status < 300) {
    foreach ($data['steps'] ?? [] as $step) {
        if (($step['type'] ?? '') === 'model_output') {
            foreach ($step['content'] ?? [] as $content) {
                if (($content['type'] ?? '') === 'text') {
                    echo $content['text'] . "\n";
                }
            }
        }
    }
} else {
    echo "Gemini API error:\n";
    echo $data['error']['message'] ?? 'Unknown API error.';
    echo "\n";
}
