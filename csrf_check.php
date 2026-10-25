<?php
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require __DIR__ . '/functions.php';

if (isset($argv[1])) {
    $_SESSION['csrf_token'] = str_repeat('a', 64);
    $_SERVER['REQUEST_METHOD'] = $argv[1] === 'get' ? 'GET' : 'POST';
    $_POST = match ($argv[1]) {
        'valid', 'get' => ['csrf_token' => $_SESSION['csrf_token']],
        'wrong' => ['csrf_token' => 'wrong'],
        'array' => ['csrf_token' => []],
        default => [],
    };
    require_csrf();
    exit('accepted');
}

foreach (['uploads/ids/example.jpg' => '../uploads/ids/example.jpg', '' => '', '../secret' => '', 'uploads/../secret' => '', 'https://example.com' => ''] as $path => $expected) {
    if (upload_url($path) !== $expected) {
        throw new RuntimeException('Upload URL check failed: ' . $path);
    }
}
if (short_date('2026-10-25') !== 'Oct 25, 2026') {
    throw new RuntimeException('Date formatting check failed');
}

$token = csrf_token();
if (strlen($token) !== 64 || csrf_token() !== $token) {
    throw new RuntimeException('Token generation failed');
}
foreach (['get', 'missing', 'wrong', 'array', 'valid'] as $case) {
    $command = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__FILE__) . ' ' . escapeshellarg($case);
    $output = shell_exec($command);
    if (($output === 'accepted') !== ($case === 'valid')) {
        throw new RuntimeException('CSRF check failed: ' . $case);
    }
}
print "CSRF checks passed.\n";
