<?php
// Direct file upload endpoint
$token = $_GET['t'] ?? $_POST['t'] ?? '';
if ($token !== 'adf-deploy-2025-secure') {
    http_response_code(403);
    die('Forbidden');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $path = $_POST['p'] ?? '';
    $content = $_POST['c'] ?? '';

    if (!$path || !$content) {
        die(json_encode(['error' => 'Missing path or content']));
    }

    // Sanitize
    $path = str_replace(['..', "\0"], '', $path);
    $path = ltrim($path, '/');
    $fullPath = '/home/adfb2574/public_html/' . $path;
    $dir = dirname($fullPath);

    // Write
    @mkdir($dir, 0755, true);
    $bytes = @file_put_contents($fullPath, $content);

    if ($bytes !== false) {
        die(json_encode(['success' => true, 'bytes' => $bytes, 'path' => $path]));
    } else {
        die(json_encode(['error' => 'Write failed']));
    }
}

die(json_encode(['error' => 'GET not allowed']));
