<?php

/**
 * Upload files to production via cPanel
 * Usage: Visit https://adfsystem.online/upload-to-production.php?token=adf-deploy-2025-secure
 */

$validToken = 'adf-deploy-2025-secure';
$providedToken = $_GET['token'] ?? $_POST['token'] ?? '';

if (!hash_equals($validToken, $providedToken)) {
    http_response_code(403);
    die('Forbidden');
}

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>

<head>
    <title>Production Upload</title>
    <style>
        body {
            font-family: Arial;
            max-width: 600px;
            margin: 50px auto;
        }

        .form {
            border: 1px solid #ccc;
            padding: 20px;
            border-radius: 5px;
        }

        textarea {
            width: 100%;
            height: 400px;
            font-family: monospace;
        }

        input,
        textarea {
            margin: 10px 0;
            padding: 8px;
            width: 100%;
        }

        button {
            padding: 10px 20px;
            background: #007bff;
            color: white;
            border: none;
            cursor: pointer;
        }

        .success {
            color: green;
        }

        .error {
            color: red;
        }
    </style>
</head>

<body>
    <h1>Upload File to Production</h1>
    <div class="form">
        <form method="POST" action="">
            <input type="hidden" name="token" value="<?php echo htmlspecialchars($_GET['token']); ?>">

            <label>Target Path (relative to /home/adfb2574/public_html):</label>
            <input type="text" name="path" placeholder="modules/frontdesk/breakfast.php" required value="modules/frontdesk/breakfast.php">

            <label>File Content:</label>
            <textarea name="content" required id="content"></textarea>

            <button type="submit">Upload</button>
        </form>
    </div>

    <?php
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $targetPath = $_POST['path'] ?? '';
        $content = $_POST['content'] ?? '';

        // Sanitize path
        $targetPath = str_replace(['..', "\0"], '', $targetPath);
        $targetPath = ltrim($targetPath, '/');

        $baseDir = '/home/adfb2574/public_html';
        $fullPath = $baseDir . '/' . $targetPath;

        // Ensure path is within base dir
        $realPath = realpath(dirname($fullPath));
        if ($realPath === false || strpos($realPath, $baseDir) !== 0) {
            echo '<p class="error">Invalid path</p>';
            exit;
        }

        // Create directory if needed
        if (!is_dir(dirname($fullPath))) {
            @mkdir(dirname($fullPath), 0755, true);
        }

        // Write file
        $bytes = file_put_contents($fullPath, $content);
        if ($bytes !== false) {
            echo "<p class='success'>✓ Uploaded successfully! ($bytes bytes to $targetPath)</p>";
        } else {
            echo "<p class='error'>✗ Failed to write file</p>";
        }
    }
    ?>
</body>

</html>