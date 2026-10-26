
<?php
$result = '';
$error = '';
$issueType = 'Task';
$issueDescription = '';
$ticketTitle = '';
$ticketDescription = '';
$ticketPriority = 'Medium';

$allowedTypes = ['Task', 'Story', 'Epic', 'Subtask'];
$allowedPriorities = ['Highest', 'High', 'Medium', 'Low', 'Lowest'];

function escapeHtml($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function extractTicketField($text, $field) {
    $pattern = '/^\s*(?:\*\*' . preg_quote($field, '/') .
               '\s*:\*\*|' . preg_quote($field, '/') .
               '\s*:)\s*(.*?)\s*$/mi';

    if (preg_match($pattern, $text, $matches)) {
        return trim($matches[1]);
    }

    return '';
}

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && ($_POST['action'] ?? '') === 'generate'
) {
    $issueType = $_POST['issue_type'] ?? 'Task';
    $issueDescription = trim($_POST['issue_description'] ?? '');

    if (!in_array($issueType, $allowedTypes, true)) {
        $issueType = 'Task';
    }

    if ($issueDescription === '') {
        $error = 'Please describe the issue first.';
    } else {
        $prompt = "You are a Jira ticket assistant.

Generate a useful Jira ticket based on the user's input.

Return exactly these sections, each on its own line:

Title: A short, clear ticket title
Description: A clear explanation of the issue
Priority: Medium
Reproduction Steps:
1. First step
2. Second step
Expected Result: What should happen

Choose a priority from Highest, High, Medium, Low, or Lowest.
Do not use Markdown bold formatting for section labels.
Keep the title on one line.
Make the description useful and concise.

Requested ticket type: " . $issueType . "

User's issue:
" . $issueDescription;

        $payload = [
            'model' => 'llama3.2:3b',
            'prompt' => $prompt,
            'stream' => false
        ];

        $options = [
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/json\r\n",
                'content' => json_encode($payload),
                'timeout' => 180,
                'ignore_errors' => true
            ]
        ];

        $context = stream_context_create($options);

        $response = @file_get_contents(
            'http://127.0.0.1:11434/api/generate',
            false,
            $context
        );

        if ($response === false) {
            $error = 'Could not connect to Ollama. Make sure Ollama is running.';
        } else {
            $data = json_decode($response, true);

            if (isset($data['response'])) {
                $result = trim($data['response']);

                $ticketTitle = extractTicketField($result, 'Title');
                $ticketPriority = extractTicketField($result, 'Priority');

                // Remove optional surrounding quotation marks from the title.
                $ticketTitle = trim($ticketTitle, " \t\n\r\0\x0B\"'");

                // If the model does not follow the requested format,
                // use a safe fallback instead of displaying blank fields.
                if ($ticketTitle === '') {
                    $firstLine = strtok($result, "\n");
                    $ticketTitle = $firstLine !== false
                        ? trim(preg_replace('/^\s*#+\s*/', '', $firstLine))
                        : '';
                }

                if ($ticketTitle === '') {
                    $ticketTitle = 'AI-generated Jira ticket';
                }

                if (mb_strlen($ticketTitle) > 255) {
                    $ticketTitle = mb_substr($ticketTitle, 0, 255);
                }

                if (!in_array($ticketPriority, $allowedPriorities, true)) {
                    $ticketPriority = 'Medium';
                }

                // Remove the title and priority lines from the description.
                $ticketDescription = preg_replace(
                    '/^\s*(?:\*\*Title:\*\*|Title:).*$(\R)?/mi',
                    '',
                    $result
                );

                $ticketDescription = preg_replace(
                    '/^\s*(?:\*\*Priority:\*\*|Priority:).*$(\R)?/mi',
                    '',
                    $ticketDescription
                );

                $ticketDescription = trim($ticketDescription);

                // Ensure the description field is never blank.
                if ($ticketDescription === '') {
                    $ticketDescription = $issueDescription;
                }
            } else {
                $error = 'Ollama returned an unexpected response. Please try again.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>AI Jira Ticket Assistant</title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet">
</head>

<body class="bg-light">

<div class="container py-5" style="max-width: 850px;">
    <div class="card shadow-sm border-0">
        <div class="card-body p-4">

            <h2 class="mb-2">AI Jira Ticket Assistant</h2>

            <p class="text-secondary">
                Generate, review, edit, and create Jira tickets using local AI.
            </p>

            <?php if ($error !== ''): ?>
                <div class="alert alert-danger">
                    <?= escapeHtml($error) ?>
                </div>
            <?php endif; ?>

            <!-- Step 1: Generate the AI ticket -->
            <form method="POST">
                <input type="hidden" name="action" value="generate">

                <div class="mb-3">
                    <label for="issue_type" class="form-label">
                        Ticket Type
                    </label>

                    <select
                        class="form-select"
                        id="issue_type"
                        name="issue_type"
                        required>

                        <?php foreach ($allowedTypes as $type): ?>
                            <option
                                value="<?= escapeHtml($type) ?>"
                                <?= $issueType === $type ? 'selected' : '' ?>>
                                <?= escapeHtml($type) ?>
                            </option>
                        <?php endforeach; ?>

                    </select>
                </div>

                <div class="mb-3">
                    <label for="issue_description" class="form-label">
                        Describe the issue
                    </label>

                    <textarea
                        class="form-control"
                        id="issue_description"
                        name="issue_description"
                        rows="5"
                        required
                        placeholder="Example: The login page shows an unexpected error when an incorrect password is entered."><?= escapeHtml($issueDescription) ?></textarea>
                </div>

                <button type="submit" class="btn btn-primary">
                    Generate Jira Ticket
                </button>
            </form>

            <!-- Step 2: Review and submit the ticket -->
            <?php if ($result !== ''): ?>

                <hr class="my-4">

                <h4>Review Your AI-Generated Ticket</h4>

                <div class="alert alert-info">
                    Review and edit the fields below before creating the Jira issue.
                </div>

                <form method="POST" action="create-issue.php">

                    <div class="mb-3">
                        <label for="summary" class="form-label">
                            Ticket Title
                        </label>

                        <input
                            type="text"
                            class="form-control"
                            id="summary"
                            name="summary"
                            maxlength="255"
                            value="<?= escapeHtml($ticketTitle) ?>"
                            required>
                    </div>

                    <div class="mb-3">
                        <label for="description" class="form-label">
                            Ticket Description
                        </label>

                        <textarea
                            class="form-control"
                            id="description"
                            name="description"
                            rows="10"
                            required><?= escapeHtml($ticketDescription) ?></textarea>
                    </div>

                    <div class="mb-3">
                        <label for="create_issue_type" class="form-label">
                            Ticket Type
                        </label>

                        <select
                            class="form-select"
                            id="create_issue_type"
                            name="issue_type"
                            required>

                            <?php foreach ($allowedTypes as $type): ?>
                                <option
                                    value="<?= escapeHtml($type) ?>"
                                    <?= $issueType === $type ? 'selected' : '' ?>>
                                    <?= escapeHtml($type) ?>
                                </option>
                            <?php endforeach; ?>

                        </select>
                    </div>

                    <div class="mb-4">
                        <label for="priority" class="form-label">
                            Priority
                        </label>

                        <select
                            class="form-select"
                            id="priority"
                            name="priority"
                            required>

                            <?php foreach ($allowedPriorities as $priority): ?>
                                <option
                                    value="<?= escapeHtml($priority) ?>"
                                    <?= $ticketPriority === $priority ? 'selected' : '' ?>>
                                    <?= escapeHtml($priority) ?>
                                </option>
                            <?php endforeach; ?>

                        </select>
                    </div>

                    <button type="submit" class="btn btn-success">
                        Create Jira Ticket
                    </button>

                </form>

                <details class="mt-4">
                    <summary>View the original AI response</summary>
                    <pre class="border rounded bg-white p-3 mt-2 text-wrap"><?= escapeHtml($result) ?></pre>
                </details>

            <?php endif; ?>

        </div>
    </div>
</div>

</body>
</html>
