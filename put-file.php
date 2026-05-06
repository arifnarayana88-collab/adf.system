<?php

/**
 * Simple PUT-based file upload for deployment
 * Access: https://adfsystem.online/put-file.php (configure path/content below or via POST)
 */

// Token check
$token = $_GET['token'] ?? $_POST['token'] ?? '';
if ($token !== 'adf-deploy-2025-secure') {
    http_response_code(403);
    die('Forbidden');
}

// Get request method
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    // Show upload form
?>
    <!DOCTYPE html>
    <html>

    <head>
        <title>File Upload</title>
    </head>

    <body>
        <form method="POST">
            <input type="hidden" name="token" value="<?php echo htmlspecialchars($_GET['token']); ?>">
            <label>Path (from /home/adfb2574/public_html/):</label><br>
            <input type="text" name="path" size="50" value="modules/frontdesk/breakfast.php" required><br><br>

            <label>Content:</label><br>
            <textarea name="content" rows="30" cols="100" required id="content"></textarea><br><br>

            <button type="submit">Upload</button>
        </form>
        <script>
            // Load default content from textarea
            document.getElementById('content').placeholder = 'Paste file content here...';
        </script>
    </body>

    </html>
<?php
} elseif ($method === 'POST') {
    $path = trim($_POST['path'] ?? '');
    $content = $_POST['content'] ?? '';

    if (!$path || !$content) {
        die('Missing path or content');
    }

    // Sanitize path
    $path = str_replace(['..', "\0", '\\'], '', $path);
    $path = ltrim($path, '/');

    $baseDir = '/home/adfb2574/public_html';
    $fullPath = $baseDir . '/' . $path;

    // Ensure still within base dir
    $realBase = realpath($baseDir);
    $realPath = dirname($fullPath);
    if ($realBase === false || $realPath === false || strpos(realpath($realPath) ?: '', $realBase) !== 0) {
        die('Invalid path');
    }

    // Create dir
    @mkdir(dirname($fullPath), 0755, true);

    // Write
    $bytes = file_put_contents($fullPath, $content);
    if ($bytes !== false) {
        echo "SUCCESS: Wrote $bytes bytes to $path\n";
        echo "File path: $fullPath\n";
    } else {
        echo "FAILED to write file\n";
    }
}
?>