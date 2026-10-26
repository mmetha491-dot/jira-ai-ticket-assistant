
<?php
header('Content-Type: text/html; charset=UTF-8');

$url = 'http://127.0.0.1:11434/api/generate';

$data = [
    'model' => 'llama3.2:3b',
    'prompt' => 'Explain what a Jira bug ticket is in simple words.',
    'stream' => false
];

$options = [
    'http' => [
        'method' => 'POST',
        'header' => "Content-Type: application/json\r\n",
        'content' => json_encode($data),
        'timeout' => 180,
        'ignore_errors' => true
    ]
];

$context = stream_context_create($options);
$response = @file_get_contents($url, false, $context);

if ($response === false) {
    exit('Could not connect to Ollama. Check that Ollama is running.');
}

$result = json_decode($response, true);

if (isset($result['response'])) {
    echo '<h2>Local AI Response</h2>';
    echo '<p>' . nl2br(htmlspecialchars(
        $result['response'],
        ENT_QUOTES,
        'UTF-8'
    )) . '</p>';
} else {
    echo '<h2>Ollama Error</h2>';
    echo '<pre>' . htmlspecialchars(
        $response,
        ENT_QUOTES,
        'UTF-8'
    ) . '</pre>';
}
?>
