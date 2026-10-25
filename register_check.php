<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$source = file_get_contents(__DIR__ . '/register.php');
if (!preg_match("~preg_match\('([^']+)', ~", $source, $match)) {
    throw new RuntimeException('Contact validation missing');
}
foreach (['09123456789' => true, '+639123456789' => true, '123' => false, '091234567890' => false, "09123456789\n" => false] as $contact => $valid) {
    if ((preg_match($match[1], (string)$contact) === 1) !== $valid) {
        throw new RuntimeException('Contact validation failed');
    }
}
print "Registration contact checks passed.\n";
