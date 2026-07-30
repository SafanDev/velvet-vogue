<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once dirname(__DIR__) . '/Config/bootstrap.php';

$failures = [];
$warnings = [];

$check = static function (bool $condition, string $message) use (&$failures): void {
    echo ($condition ? '[OK]   ' : '[FAIL] ') . $message . PHP_EOL;
    if (!$condition) {
        $failures[] = $message;
    }
};

$warn = static function (bool $condition, string $message) use (&$warnings): void {
    if ($condition) {
        echo '[OK]   ' . $message . PHP_EOL;
        return;
    }

    echo '[WARN] ' . $message . PHP_EOL;
    $warnings[] = $message;
};

$root = dirname(__DIR__);

echo "Velvet Vogue deployment preflight\n\n";

$check(version_compare(PHP_VERSION, '8.1.0', '>='), 'PHP 8.1 or newer is installed.');

foreach (['pdo_mysql', 'mbstring', 'fileinfo', 'dom', 'json', 'openssl'] as $extension) {
    $check(extension_loaded($extension), "PHP extension {$extension} is available.");
}
$warn(extension_loaded('gd'), 'The GD extension is available for image re-encoding and resizing.');
$check(is_writable(sys_get_temp_dir()), 'The PHP temporary directory is writable for throttling data.');

$postMax = vv_parse_ini_bytes((string) ini_get('post_max_size'));
$uploadMax = vv_parse_ini_bytes((string) ini_get('upload_max_filesize'));
$warn($postMax >= 6 * 1024 * 1024, 'post_max_size supports the application image limit.');
$warn($uploadMax >= 5 * 1024 * 1024, 'upload_max_filesize supports the application image limit.');

$check(is_file($root . '/.env'), 'A local .env file exists.');
$appKey = trim((string) vv_env('APP_KEY', ''));
$check(vv_app_key() !== null && !str_contains(strtolower($appKey), 'replace_with'), 'APP_KEY is a non-placeholder secret of at least 32 characters.');
$check(trim((string) vv_env('DB_NAME', '')) !== '', 'DB_NAME is configured.');
$check(trim((string) vv_env('DB_USER', '')) !== '', 'DB_USER is configured.');
$warn(strtolower((string) vv_env('DB_USER', '')) !== 'root', 'The application uses a restricted database account instead of root.');
$isProduction = strtolower((string) vv_env('APP_ENV', 'development')) === 'production';
$databasePassword = (string) vv_env('DB_PASSWORD', '');
$check(!$isProduction || ($databasePassword !== '' && !str_contains(strtolower($databasePassword), 'replace_with')), 'Production uses a non-placeholder database password.');
$warn($isProduction, 'APP_ENV is set to production for public hosting.');
$appUrl = trim((string) vv_env('APP_URL', ''));
$check(!$isProduction || str_starts_with($appUrl, 'https://'), 'APP_URL uses HTTPS in production.');
$trustProxy = strtolower(trim((string) vv_env('TRUST_PROXY', 'false')));
$check(in_array($trustProxy, ['true', 'false'], true), 'TRUST_PROXY is either true or false.');

$dbHost = trim((string) vv_env('DB_HOST', ''));
$dbPort = trim((string) vv_env('DB_PORT', '3306'));
$dbName = trim((string) vv_env('DB_NAME', ''));
$dbUser = trim((string) vv_env('DB_USER', ''));
if (extension_loaded('pdo_mysql') && $dbHost !== '' && $dbName !== '' && $dbUser !== '' && ctype_digit($dbPort)) {
    try {
        $preflightPdo = new PDO(
            "mysql:host={$dbHost};port={$dbPort};dbname={$dbName};charset=utf8mb4",
            $dbUser,
            $databasePassword,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_TIMEOUT => 5,
            ],
        );
        $check(true, 'The configured database connection succeeds.');

        $existingTables = array_map(static fn ($table): string => strtolower((string) $table), $preflightPdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN));
        $requiredTables = ['user', 'useraddress', 'product', 'productvariant', 'productimage', 'cart', 'cartitem', 'order', 'orderitem', 'payment', 'coupon'];
        $missingTables = array_values(array_diff($requiredTables, $existingTables));
        $check($missingTables === [], 'The required core database tables are present.');
        if ($missingTables !== []) {
            echo '[INFO] Missing tables: ' . implode(', ', $missingTables) . PHP_EOL;
        }
    } catch (Throwable $exception) {
        error_log('Preflight database check failed: ' . $exception->getMessage());
        $check(false, 'The configured database connection succeeds.');
    }
}

foreach ([
    'Admin/image',
    'Admin/image/category',
    'Assets/images/avatars',
    'Assets/images/promotion',
] as $directory) {
    $path = $root . '/' . $directory;
    $check(is_dir($path) && is_writable($path), "{$directory} exists and is writable.");
    $check(is_file($path . '/.htaccess'), "{$directory} blocks executable uploads and directory listing.");

    $executableUploads = array_filter(
        glob($path . '/*') ?: [],
        static fn (string $file): bool => is_file($file) && preg_match('/\.(?:php|phtml|phar|php[0-9]*|cgi|pl|py|sh|shtml)$/i', $file) === 1,
    );
    $check($executableUploads === [], "{$directory} contains no executable PHP uploads.");
}

$check(is_file($root . '/Actions/csrf_token.php'), 'The signed request-token refresh endpoint exists.');
$check(is_file($root . '/Assets/js/security.js'), 'The shared browser request-security client exists.');
$securityClientSource = is_file($root . '/Assets/js/security.js') ? (file_get_contents($root . '/Assets/js/security.js') ?: '') : '';
$dbConfigSource = is_file($root . '/Config/db.php') ? (file_get_contents($root . '/Config/db.php') ?: '') : '';
$bootstrapSource = is_file($root . '/Config/bootstrap.php') ? (file_get_contents($root . '/Config/bootstrap.php') ?: '') : '';
$requestSecurityReady = defined('VV_REQUEST_SECURITY_VERSION')
    && VV_REQUEST_SECURITY_VERSION === 7
    && defined('VV_CSRF_PROTOCOL_VERSION')
    && VV_CSRF_PROTOCOL_VERSION === 7
    && function_exists('vv_verify_write_request')
    && function_exists('vv_request_source_is_explicitly_same_origin')
    && function_exists('vv_fetch_metadata_is_cross_site')
    && function_exists('vv_csrf_token')
    && function_exists('vv_csrf_token_matches')
    && str_starts_with(vv_csrf_token(), 'v7.')
    && str_contains($dbConfigSource, 'vv_verify_write_request();')
    && str_contains($securityClientSource, 'const CSRF_PROTOCOL_VERSION = 7;')
    && str_contains($securityClientSource, '^v7\\.\\d{10}')
    && !str_contains($bootstrapSource, 'vv_prepare_csrf_binding')
    && !str_contains($dbConfigSource, 'VV_CART_MUTATION_ENDPOINT')
    && !str_contains($dbConfigSource, 'VV_CHECKOUT_MUTATION_ENDPOINT');
$check($requestSecurityReady, 'The unified session-independent request-security protocol is installed.');

$writeHandlers = [];
$unprotectedHandlers = [];
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
foreach ($iterator as $file) {
    if (!$file->isFile() || strtolower($file->getExtension()) !== 'php') {
        continue;
    }

    $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));
    if (!preg_match('#^(Actions|Admin|Customer)/#', $relative)) {
        continue;
    }

    $source = file_get_contents($file->getPathname()) ?: '';
    $handlesWrite = str_contains($source, '$_POST')
        || preg_match('/REQUEST_METHOD[^\n]{0,100}(?:POST|PUT|PATCH|DELETE)/i', $source) === 1;
    if (!$handlesWrite) {
        continue;
    }

    $writeHandlers[] = $relative;
    $protected = str_contains($source, 'Config/db.php')
        || str_contains($source, "Config\\db.php")
        || str_contains($source, 'vv_verify_write_request()');
    if (!$protected) {
        $unprotectedHandlers[] = $relative;
    }
}
$check(count($writeHandlers) >= 20, 'State-changing endpoint coverage was discovered.');
$check($unprotectedHandlers === [], 'Every state-changing endpoint enters the unified request guard.');
if ($unprotectedHandlers !== []) {
    echo '[INFO] Unprotected handlers: ' . implode(', ', $unprotectedHandlers) . PHP_EOL;
}

$allSource = $bootstrapSource . $dbConfigSource . $securityClientSource;
foreach (['Actions', 'Admin', 'Customer'] as $directory) {
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/' . $directory, FilesystemIterator::SKIP_DOTS));
    foreach ($files as $file) {
        if ($file->isFile() && in_array(strtolower($file->getExtension()), ['php', 'js'], true)) {
            $allSource .= file_get_contents($file->getPathname()) ?: '';
        }
    }
}
$check(!str_contains($allSource, 'VV_CART_MUTATION_ENDPOINT') && !str_contains($allSource, 'VV_CHECKOUT_MUTATION_ENDPOINT'), 'No endpoint-specific request-security bypass remains.');
$check(!str_contains($allSource, 'The security check could not be completed.'), 'The obsolete session-token failure path is absent.');

$checkoutSource = (string) file_get_contents($root . '/Actions/process_order.php');
$checkoutPageSource = (string) file_get_contents($root . '/Customer/checkout.php');
$checkoutClientSource = (string) file_get_contents($root . '/Assets/js/pages/checkout.js');
$checkoutSecurityReady = defined('VV_CHECKOUT_SECURITY_VERSION')
    && VV_CHECKOUT_SECURITY_VERSION === 6
    && str_contains($checkoutSource, 'vv_verify_checkout_intent')
    && str_contains($checkoutSource, 'X-VV-Checkout-Security: 6')
    && str_contains($checkoutPageSource, 'vv_checkout_intent_token($userID)')
    && str_contains($checkoutPageSource, 'id="checkoutIntentToken"')
    && str_contains($checkoutClientSource, "formData.append('checkout_intent', checkoutIntent)");
$check($checkoutSecurityReady, 'Checkout retains its dedicated signed order-intent protection.');

$check(is_file($root . '/Customer/404.php') && is_file($root . '/Assets/js/pages/404.js') && is_file($root . '/Assets/css/pages/404.css'), 'The custom 404 experience is complete.');
$warn(!function_exists('opcache_get_status') || (bool) ini_get('opcache.enable'), 'PHP OPcache is enabled when the hosting environment supports it.');

$check(is_file($root . '/.htaccess'), 'The project-root Apache protection file exists.');

foreach (['Config', 'scripts', 'database'] as $protectedDirectory) {
    $check(is_file($root . '/' . $protectedDirectory . '/.htaccess'), "{$protectedDirectory} blocks direct web access.");
}

if ($warnings !== []) {
    echo "\nWarnings should be reviewed before a public launch.\n";
}

if ($failures !== []) {
    echo "\nPreflight failed with " . count($failures) . " blocking issue(s).\n";
    exit(1);
}

echo "\nPreflight passed. Complete the browser smoke test before launch.\n";
