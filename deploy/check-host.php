<?php

/*
| Run this on the hosting account BEFORE deploying, over SSH:   php check-host.php
| It checks the things the AFISAB application needs (DECISIONS.md Q8). It refuses to run in a web browser
| so that it never exposes server details to visitors.
*/

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$results = [];
$add = function (string $label, bool $ok, string $detail = '', bool $required = true) use (&$results) {
    $results[] = [$ok ? 'ok' : ($required ? 'FAIL' : 'warn'), $label, $detail];
};

$add('PHP 8.3 or newer', version_compare(PHP_VERSION, '8.3.0', '>='), 'running '.PHP_VERSION);

foreach (['bcmath', 'ctype', 'curl', 'dom', 'fileinfo', 'gd', 'intl', 'mbstring', 'openssl', 'pdo', 'tokenizer', 'xml', 'xmlwriter', 'zip'] as $ext) {
    $add("Extension: {$ext}", extension_loaded($ext));
}
$add('Database driver (pdo_mysql or pdo_pgsql)', extension_loaded('pdo_mysql') || extension_loaded('pdo_pgsql'), implode(', ', PDO::getAvailableDrivers()));
$add('GD can draw PNG (certificate QR code)', function_exists('imagecreatetruecolor') && (gd_info()['PNG Support'] ?? false));
$add('memory_limit is at least 256M', (int) ini_get('memory_limit') === -1 || (int) ini_get('memory_limit') >= 256, 'is '.ini_get('memory_limit').' (PDF generation needs memory)', false);
$add('max_execution_time is at least 60s', (int) ini_get('max_execution_time') === 0 || (int) ini_get('max_execution_time') >= 60, 'is '.ini_get('max_execution_time'), false);
$add('upload_max_filesize is at least 4M', (int) ini_get('upload_max_filesize') >= 4, 'is '.ini_get('upload_max_filesize').' (KYC documents and receipts are up to 4 MB)', false);
$add('post_max_size is at least 8M', (int) ini_get('post_max_size') >= 8, 'is '.ini_get('post_max_size'), false);
$add('allow_url_fopen / cURL available for Paystack', extension_loaded('curl'), '', true);
$add('open_basedir is not blocking the app folder', ini_get('open_basedir') === '' || ini_get('open_basedir') === false, 'open_basedir='.ini_get('open_basedir'), false);

foreach (['proc_open', 'exec'] as $fn) {
    $add("Function {$fn} is enabled (Composer / cron helpers)", function_exists($fn) && ! in_array($fn, array_map('trim', explode(',', (string) ini_get('disable_functions'))), true), '', false);
}

$composer = trim((string) @shell_exec('command -v composer 2>/dev/null'));
$add('Composer is installed', $composer !== '', $composer ?: 'upload vendor/ from a release build instead', false);
$mysqldump = trim((string) @shell_exec('command -v mysqldump 2>/dev/null'));
$add('mysqldump is installed (backups)', $mysqldump !== '', $mysqldump ?: 'use cPanel Backup instead', false);

$add('This folder is writable (test file)', is_writable(__DIR__), __DIR__);
$add('Server time zone', true, date_default_timezone_get().' (set APP_TIMEZONE=Africa/Lagos in .env)');

$fails = 0;
$warns = 0;
foreach ($results as [$status, $label, $detail]) {
    printf("%-5s %-58s %s\n", $status, $label, $detail);
    $fails += $status === 'FAIL';
    $warns += $status === 'warn';
}
echo "\n{$fails} failure(s), {$warns} warning(s). ".($fails ? "Do not deploy until the failures are fixed (ask the host to enable them in MultiPHP INI Editor / Select PHP Version).\n" : "This host can run AFISAB.\n");
exit($fails ? 1 : 0);
