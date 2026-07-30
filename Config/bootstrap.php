<?php

const VV_CSRF_PROTOCOL_VERSION = 7;
const VV_REQUEST_SECURITY_VERSION = 7;
const VV_CART_SECURITY_VERSION = 5;
const VV_CHECKOUT_SECURITY_VERSION = 6;
const VV_LEGACY_REMEMBER_COOKIE = 'vv_user_auth';
const VV_LEGACY_SIGNED_REMEMBER_COOKIE = 'vv_remember';

function vv_project_root(): string
{
    return dirname(__DIR__);
}

function vv_load_env_file(string $path): void
{
    if (!is_file($path) || !is_readable($path)) {
        return;
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        return;
    }

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
            continue;
        }

        [$name, $value] = array_map('trim', explode('=', $line, 2));
        if ($name === '' || getenv($name) !== false) {
            continue;
        }

        if (strlen($value) >= 2) {
            $first = $value[0];
            $last = $value[strlen($value) - 1];
            if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
                $value = substr($value, 1, -1);
            }
        }

        putenv($name . '=' . $value);
        $_ENV[$name] = $value;
    }
}

vv_load_env_file(vv_project_root() . '/.env');

if (strtolower((string) getenv('APP_ENV')) === 'production') {
    ini_set('display_errors', '0');
    ini_set('display_startup_errors', '0');
    error_reporting(E_ALL);
}

function vv_env(string $name, ?string $default = null): ?string
{
    $value = getenv($name);
    return $value === false ? $default : $value;
}

function vv_is_production(): bool
{
    return strtolower((string) vv_env('APP_ENV', 'development')) === 'production';
}

function vv_versioned_asset(string $publicPath): string
{
    $relativePath = preg_replace('#^(?:\.\./)+#', '', str_replace('\\', '/', $publicPath)) ?? $publicPath;
    $filePath = vv_project_root() . '/' . ltrim($relativePath, '/');
    $version = is_file($filePath) ? (string) filemtime($filePath) : '1';
    $separator = str_contains($publicPath, '?') ? '&' : '?';
    return $publicPath . $separator . 'v=' . rawurlencode($version);
}

function vv_app_url(string $path = ''): string
{
    $configured = rtrim(trim((string) vv_env('APP_URL', '')), '/');

    if ($configured === '' && PHP_SAPI !== 'cli') {
        $scheme = vv_is_https() ? 'https' : 'http';
        $host = trim((string) ($_SERVER['HTTP_HOST'] ?? 'localhost'));
        $scriptName = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
        $basePath = preg_replace('#/(?:Customer|Admin|Actions|AdminActions|Config|scripts)(?:/.*)?$#i', '', $scriptName) ?? '';
        $configured = $scheme . '://' . $host . rtrim($basePath, '/');
    }

    if ($path === '') {
        return $configured;
    }

    return $configured . '/' . ltrim($path, '/');
}

function vv_is_https(): bool
{
    if (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off') {
        return true;
    }

    if (strtolower((string) vv_env('TRUST_PROXY', 'false')) === 'true') {
        $forwardedProto = strtolower(trim(explode(',', (string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''))[0]));
        if ($forwardedProto === 'https') {
            return true;
        }
    }

    return isset($_SERVER['SERVER_PORT']) && (int) $_SERVER['SERVER_PORT'] === 443;
}

function vv_cookie_path(): string
{
    $documentRoot = realpath((string) ($_SERVER['DOCUMENT_ROOT'] ?? ''));
    $projectRoot = realpath(vv_project_root());

    if (is_string($documentRoot) && $documentRoot !== '' && is_string($projectRoot)) {
        $normalizedDocumentRoot = rtrim(str_replace('\\', '/', $documentRoot), '/');
        $normalizedProjectRoot = rtrim(str_replace('\\', '/', $projectRoot), '/');
        $isWindows = DIRECTORY_SEPARATOR === '\\';
        $rootMatches = $isWindows
            ? str_starts_with(strtolower($normalizedProjectRoot), strtolower($normalizedDocumentRoot))
            : str_starts_with($normalizedProjectRoot, $normalizedDocumentRoot);

        if ($rootMatches) {
            $relativePath = substr($normalizedProjectRoot, strlen($normalizedDocumentRoot));
            $relativePath = '/' . trim($relativePath, '/');
            return $relativePath === '/' ? '/' : $relativePath . '/';
        }
    }

    $scriptName = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    $path = '';

    if ($scriptName !== '') {
        $path = preg_replace('#/(?:Customer|Admin|Actions|Config|scripts)(?:/.*)?$#i', '', $scriptName) ?? '';
        if ($path === $scriptName && str_ends_with(strtolower($scriptName), '.php')) {
            $path = str_replace('\\', '/', dirname($scriptName));
        } elseif ($path === $scriptName) {
            $path = '';
        }
    }

    if ($path === '') {
        $configuredUrl = trim((string) vv_env('APP_URL', ''));
        $configuredPath = $configuredUrl !== '' ? parse_url($configuredUrl, PHP_URL_PATH) : null;
        $path = is_string($configuredPath) ? $configuredPath : '';
    }

    $path = '/' . trim((string) $path, '/');
    return $path === '/' ? '/' : $path . '/';
}

function vv_cookie_scope(): string
{
    $host = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
    if ($host === '') {
        $configuredUrl = trim((string) vv_env('APP_URL', ''));
        $configuredHost = $configuredUrl !== '' ? parse_url($configuredUrl, PHP_URL_HOST) : null;
        $host = is_string($configuredHost) && $configuredHost !== '' ? strtolower($configuredHost) : 'local';
    }

    return substr(hash('sha256', $host . '|' . vv_cookie_path()), 0, 12);
}

function vv_session_cookie_name(): string
{
    return 'vv_session_' . vv_cookie_scope();
}

function vv_remember_cookie_name(): string
{
    return 'vv_remember_' . vv_cookie_scope();
}

function vv_clear_legacy_session_cookie(): void
{
    if (!isset($_COOKIE['vv_session'])) {
        return;
    }

    $cookiePath = vv_cookie_path();
    vv_expire_cookie('vv_session', $cookiePath);
    if ($cookiePath !== '/') {
        vv_expire_cookie('vv_session', '/');
    }
}

function vv_expire_cookie(string $name, string $path): void
{
    setcookie($name, '', [
        'expires' => time() - 42000,
        'path' => $path,
        'secure' => vv_is_https(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    unset($_COOKIE[$name]);
}

function vv_send_security_headers(): void
{
    if (PHP_SAPI === 'cli' || headers_sent()) {
        return;
    }

    header_remove('X-Powered-By');
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=()');
    header('Cross-Origin-Opener-Policy: same-origin');
    header('X-Permitted-Cross-Domain-Policies: none');

    $contentSecurityPolicy = [
        "default-src 'self'",
        "base-uri 'self'",
        "form-action 'self'",
        "frame-ancestors 'self'",
        "object-src 'none'",
        "script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com https://cdn.quilljs.com",
        "style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com https://cdn.quilljs.com https://fonts.googleapis.com",
        "font-src 'self' data: https://fonts.gstatic.com https://cdnjs.cloudflare.com",
        "img-src 'self' data: blob: https:",
        "media-src 'self' blob:",
        "connect-src 'self'",
        "worker-src 'self' blob:",
    ];

    if (vv_is_production() && vv_is_https()) {
        $contentSecurityPolicy[] = 'upgrade-insecure-requests';
    }

    header('Content-Security-Policy: ' . implode('; ', $contentSecurityPolicy));

    if (vv_is_production() && vv_is_https()) {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
}

function vv_session_start(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    $secure = vv_is_https();
    $idleLimit = max(900, (int) vv_env('SESSION_IDLE_SECONDS', '7200'));
    $absoluteLimit = max($idleLimit, (int) vv_env('SESSION_ABSOLUTE_SECONDS', '43200'));

    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.lazy_write', '1');
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_samesite', 'Lax');
    ini_set('session.gc_maxlifetime', (string) $absoluteLimit);
    session_cache_limiter('nocache');

    // Older project copies used one global localhost cookie. Remove it before opening the scoped session.
    vv_clear_legacy_session_cookie();
    session_name(vv_session_cookie_name());
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => vv_cookie_path(),
        'domain' => '',
        'secure' => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    session_start();

    $now = time();

    if (isset($_SESSION['_created_at']) && $now - (int) $_SESSION['_created_at'] > $absoluteLimit) {
        vv_restart_session();
    } elseif (isset($_SESSION['_last_seen']) && $now - (int) $_SESSION['_last_seen'] > $idleLimit) {
        vv_restart_session();
    }

    if (!isset($_SESSION['_created_at'])) {
        $_SESSION['_created_at'] = $now;
        $_SESSION['_regenerated_at'] = $now;
    }

    if (!isset($_SESSION['_regenerated_at'])) {
        $_SESSION['_regenerated_at'] = $now;
    }

    $_SESSION['_last_seen'] = $now;
    if (isset($_SESSION['userID']) && !headers_sent()) {
        header('Cache-Control: private, no-store, max-age=0');
        header('Pragma: no-cache');
    }

}

function vv_restart_session(): void
{
    vv_destroy_session();
    session_start();
    session_regenerate_id(true);
}

function vv_destroy_session(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        return;
    }

    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires' => time() - 42000,
            'path' => $params['path'] ?: '/',
            'domain' => $params['domain'] ?? '',
            'secure' => (bool) ($params['secure'] ?? false),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }

    session_destroy();
    unset($_COOKIE[session_name()]);
    session_id('');
    $GLOBALS['vv_session_user_validated'] = false;
}

function vv_base64url_encode(string $value): string
{
    return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
}

function vv_base64url_decode(string $value): string|false
{
    $padding = strlen($value) % 4;
    if ($padding !== 0) {
        $value .= str_repeat('=', 4 - $padding);
    }

    return base64_decode(strtr($value, '-_', '+/'), true);
}

function vv_app_key(): ?string
{
    $key = vv_env('APP_KEY');
    return is_string($key) && strlen($key) >= 32 ? $key : null;
}

function vv_clear_remember_cookie(): void
{
    $cookiePath = vv_cookie_path();
    foreach ([vv_remember_cookie_name(), VV_LEGACY_SIGNED_REMEMBER_COOKIE, VV_LEGACY_REMEMBER_COOKIE] as $name) {
        vv_expire_cookie($name, $cookiePath);
        if ($cookiePath !== '/') {
            vv_expire_cookie($name, '/');
        }
    }
}

function vv_set_remember_cookie(int $userId, string $passwordHash): bool
{
    $key = vv_app_key();
    if ($key === null) {
        return false;
    }

    $now = time();
    $rememberDays = max(1, min(30, (int) vv_env('REMEMBER_ME_DAYS', '14')));
    $payload = [
        'uid' => $userId,
        'iat' => $now,
        'exp' => $now + $rememberDays * 86400,
        'ver' => substr(hash('sha256', $passwordHash), 0, 32),
        'nonce' => bin2hex(random_bytes(12)),
    ];

    $encoded = vv_base64url_encode(json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    $signature = vv_base64url_encode(hash_hmac('sha256', $encoded, $key, true));

    return setcookie(vv_remember_cookie_name(), $encoded . '.' . $signature, [
        'expires' => $payload['exp'],
        'path' => vv_cookie_path(),
        'secure' => vv_is_https(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

function vv_restore_remembered_user(PDO $pdo): bool
{
    if (isset($_SESSION['userID'])) {
        return true;
    }

    if (isset($_COOKIE[VV_LEGACY_REMEMBER_COOKIE]) || isset($_COOKIE[VV_LEGACY_SIGNED_REMEMBER_COOKIE])) {
        vv_clear_remember_cookie();
    }

    $cookieName = vv_remember_cookie_name();
    $cookie = $_COOKIE[$cookieName] ?? '';
    $key = vv_app_key();
    if (!is_string($cookie) || $cookie === '' || $key === null || !str_contains($cookie, '.')) {
        return false;
    }

    [$encoded, $providedSignature] = explode('.', $cookie, 2);
    $expectedSignature = vv_base64url_encode(hash_hmac('sha256', $encoded, $key, true));
    if (!hash_equals($expectedSignature, $providedSignature)) {
        vv_clear_remember_cookie();
        return false;
    }

    $decoded = vv_base64url_decode($encoded);
    $payload = $decoded === false ? null : json_decode($decoded, true);
    $now = time();
    $maxLifetime = max(1, min(30, (int) vv_env('REMEMBER_ME_DAYS', '14'))) * 86400;
    $validPayload = is_array($payload)
        && isset($payload['uid'], $payload['iat'], $payload['exp'], $payload['ver'])
        && filter_var($payload['uid'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) !== false
        && (int) $payload['iat'] <= $now + 60
        && (int) $payload['exp'] >= $now
        && (int) $payload['exp'] - (int) $payload['iat'] <= $maxLifetime;

    if (!$validPayload) {
        vv_clear_remember_cookie();
        return false;
    }

    $stmt = $pdo->prepare('SELECT userID, firstName, lastName, email, password, role, isActive FROM `user` WHERE userID = ? LIMIT 1');
    $stmt->execute([(int) $payload['uid']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user || (int) $user['isActive'] !== 1 || !in_array((string) $user['role'], ['customer', 'admin'], true)) {
        vv_clear_remember_cookie();
        return false;
    }

    $passwordVersion = substr(hash('sha256', (string) $user['password']), 0, 32);
    if (!hash_equals($passwordVersion, (string) $payload['ver'])) {
        vv_clear_remember_cookie();
        return false;
    }

    session_regenerate_id(true);
    $_SESSION['userID'] = (int) $user['userID'];
    $_SESSION['firstName'] = (string) $user['firstName'];
    $_SESSION['lastName'] = (string) ($user['lastName'] ?? '');
    $_SESSION['email'] = (string) ($user['email'] ?? '');
    $_SESSION['role'] = (string) $user['role'];
    $_SESSION['_regenerated_at'] = time();
    if (!headers_sent()) {
        header('Cache-Control: private, no-store, max-age=0');
        header('Pragma: no-cache');
    }

    $pdo->prepare('UPDATE `user` SET lastLogin = CURRENT_TIMESTAMP WHERE userID = ?')->execute([(int) $user['userID']]);
    $GLOBALS['vv_session_user_validated'] = true;
    return true;
}

function vv_sync_session_user(PDO $pdo): bool
{
    if (!isset($_SESSION['userID'])) {
        $GLOBALS['vv_session_user_validated'] = false;
        return false;
    }

    $stmt = $pdo->prepare('SELECT userID, firstName, lastName, email, role, isActive FROM `user` WHERE userID = ? LIMIT 1');
    $stmt->execute([(int) $_SESSION['userID']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user || (int) $user['isActive'] !== 1) {
        vv_clear_remember_cookie();
        vv_destroy_session();
        return false;
    }

    $_SESSION['userID'] = (int) $user['userID'];
    $_SESSION['firstName'] = (string) $user['firstName'];
    $_SESSION['lastName'] = (string) ($user['lastName'] ?? '');
    $_SESSION['email'] = (string) ($user['email'] ?? '');
    $_SESSION['role'] = (string) $user['role'];
    $GLOBALS['vv_session_user_validated'] = true;
    return true;
}

function vv_legacy_csrf_cookie_names(): array
{
    return [
        'vv_csrf_bind_v3_' . vv_cookie_scope(),
        'vv_csrf_seed_v2_' . vv_cookie_scope(),
        'vv_csrf_' . vv_cookie_scope(),
    ];
}

function vv_clear_legacy_csrf_cookies(): void
{
    static $checked = false;
    if ($checked) {
        return;
    }
    $checked = true;

    $paths = array_values(array_unique(['/', vv_cookie_path()]));
    $cookieHeader = (string) ($_SERVER['HTTP_COOKIE'] ?? '');

    foreach (vv_legacy_csrf_cookie_names() as $name) {
        if (!isset($_COOKIE[$name]) && !str_contains($cookieHeader, $name . '=')) {
            continue;
        }

        foreach ($paths as $path) {
            if (!headers_sent()) {
                setcookie($name, '', [
                    'expires' => time() - 42000,
                    'path' => $path,
                    'domain' => '',
                    'secure' => vv_is_https(),
                    'httponly' => true,
                    'samesite' => 'Lax',
                ]);
            }
        }
        unset($_COOKIE[$name]);
    }
}

function vv_csrf_signing_key(): string
{
    $key = vv_app_key();
    if ($key !== null) {
        return $key;
    }

    // Production preflight requires APP_KEY. This fallback only keeps an
    // incomplete local setup usable until the environment is corrected.
    if (session_status() !== PHP_SESSION_ACTIVE) {
        vv_session_start();
    }
    if (empty($_SESSION['_csrf_fallback_key']) || !is_string($_SESSION['_csrf_fallback_key'])) {
        $_SESSION['_csrf_fallback_key'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['_csrf_fallback_key'];
}

function vv_csrf_token_ttl(): int
{
    return max(900, min(21600, (int) vv_env('CSRF_TOKEN_TTL_SECONDS', '7200')));
}

function vv_csrf_token_for_values(int $issuedAt, string $nonce): string
{
    $signature = hash_hmac(
        'sha256',
        'velvet-vogue-request-v7|' . vv_cookie_scope() . '|' . $issuedAt . '|' . $nonce,
        vv_csrf_signing_key(),
    );

    return 'v7.' . $issuedAt . '.' . strtolower($nonce) . '.' . $signature;
}

function vv_parse_csrf_token(string $token): ?array
{
    if (!preg_match('/\Av7\.(\d{10})\.([a-f0-9]{32})\.([a-f0-9]{64})\z/i', trim($token), $matches)) {
        return null;
    }

    return [
        'issued_at' => (int) $matches[1],
        'nonce' => strtolower($matches[2]),
        'signature' => strtolower($matches[3]),
    ];
}

function vv_csrf_token_signature_is_valid(string $token): bool
{
    $parts = vv_parse_csrf_token($token);
    if ($parts === null) {
        return false;
    }

    $now = time();
    if ($parts['issued_at'] > $now + 300 || $parts['issued_at'] < $now - vv_csrf_token_ttl()) {
        return false;
    }

    $expected = vv_parse_csrf_token(vv_csrf_token_for_values($parts['issued_at'], $parts['nonce']));
    return is_array($expected) && hash_equals($expected['signature'], $parts['signature']);
}

function vv_csrf_token_is_valid(string $token): bool
{
    return vv_csrf_token_signature_is_valid($token);
}

function vv_issue_csrf_token(): string
{
    vv_clear_legacy_csrf_cookies();
    $token = vv_csrf_token_for_values(time(), bin2hex(random_bytes(16)));
    $GLOBALS['vv_csrf_token'] = $token;
    return $token;
}

function vv_csrf_token(): string
{
    $cached = $GLOBALS['vv_csrf_token'] ?? null;
    if (is_string($cached) && vv_csrf_token_signature_is_valid($cached)) {
        return $cached;
    }

    return vv_issue_csrf_token();
}

function vv_rotate_csrf_token(): string
{
    unset($GLOBALS['vv_csrf_token']);
    return vv_issue_csrf_token();
}

function vv_csrf_token_matches(string $provided): bool
{
    return vv_csrf_token_signature_is_valid($provided);
}

function vv_request_source_is_explicitly_same_origin(): bool
{
    $requestScheme = vv_is_https() ? 'https' : 'http';
    $requestAuthority = strtolower(trim((string) ($_SERVER['HTTP_HOST'] ?? '')));
    $requestParts = parse_url($requestScheme . '://' . $requestAuthority);

    if (!is_array($requestParts) || empty($requestParts['host'])) {
        return false;
    }

    $requestHost = strtolower((string) $requestParts['host']);
    $requestPort = isset($requestParts['port'])
        ? (int) $requestParts['port']
        : ($requestScheme === 'https' ? 443 : 80);

    foreach (['HTTP_ORIGIN', 'HTTP_REFERER'] as $serverKey) {
        $source = trim((string) ($_SERVER[$serverKey] ?? ''));
        if ($source === '') {
            continue;
        }

        $parts = parse_url($source);
        if (!is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) {
            return false;
        }

        $sourceScheme = strtolower((string) $parts['scheme']);
        if (!in_array($sourceScheme, ['http', 'https'], true)) {
            return false;
        }

        $sourceHost = strtolower((string) $parts['host']);
        $sourcePort = isset($parts['port'])
            ? (int) $parts['port']
            : ($sourceScheme === 'https' ? 443 : 80);

        return hash_equals($requestScheme, $sourceScheme)
            && hash_equals($requestHost, $sourceHost)
            && $requestPort === $sourcePort;
    }

    return false;
}

function vv_request_has_source_headers(): bool
{
    return trim((string) ($_SERVER['HTTP_ORIGIN'] ?? '')) !== ''
        || trim((string) ($_SERVER['HTTP_REFERER'] ?? '')) !== '';
}

function vv_fetch_metadata_is_cross_site(): bool
{
    $fetchSite = strtolower(trim((string) ($_SERVER['HTTP_SEC_FETCH_SITE'] ?? '')));
    return in_array($fetchSite, ['cross-site', 'none'], true);
}

function vv_invalidate_nav_counts(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        unset($_SESSION['_nav_counts']);
    }
}

function vv_request_is_json(): bool
{
    $accept = strtolower((string) ($_SERVER['HTTP_ACCEPT'] ?? ''));
    $requestedWith = strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? ''));
    $path = strtolower((string) ($_SERVER['SCRIPT_NAME'] ?? ''));

    return str_contains($accept, 'application/json')
        || $requestedWith === 'xmlhttprequest'
        || str_contains($path, '/actions/')
        || str_contains($path, '/adminactions/');
}

function vv_json_response(array $payload, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=UTF-8');
    header('Cache-Control: no-store, private');

    // Keep long-lived pages synchronized after login, password changes, or session renewal.
    if (session_status() === PHP_SESSION_ACTIVE && !headers_sent()) {
        header('X-CSRF-Token: ' . vv_csrf_token());
        header('X-VV-Request-Security: ' . VV_REQUEST_SECURITY_VERSION);
        header('Access-Control-Expose-Headers: X-CSRF-Token, X-VV-Request-Security');
    }

    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

function vv_fail_request(string $message, int $status = 400): never
{
    if (vv_request_is_json()) {
        vv_json_response(['status' => 'error', 'message' => $message], $status);
    }

    http_response_code($status);
    header('Content-Type: text/plain; charset=UTF-8');
    echo $message;
    exit;
}

function vv_is_trusted_same_origin_ajax_request(): bool
{
    $requestedWith = strtolower(trim((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')));
    return $requestedWith === 'xmlhttprequest'
        && !vv_fetch_metadata_is_cross_site()
        && vv_request_source_is_explicitly_same_origin();
}

function vv_origin_matches_host(): bool
{
    if (!vv_request_has_source_headers()) {
        return true;
    }

    return vv_request_source_is_explicitly_same_origin();
}

function vv_parse_ini_bytes(string $value): int
{
    $value = trim($value);
    if ($value === '') {
        return 0;
    }

    $unit = strtolower(substr($value, -1));
    $number = (float) $value;

    return match ($unit) {
        'g' => (int) ($number * 1024 * 1024 * 1024),
        'm' => (int) ($number * 1024 * 1024),
        'k' => (int) ($number * 1024),
        default => (int) $number,
    };
}

function vv_reject_unverified_write_request(): never
{
    if (!headers_sent()) {
        header('X-VV-Request-Security: ' . VV_REQUEST_SECURITY_VERSION);
    }

    if (vv_request_is_json()) {
        vv_json_response([
            'status' => 'error',
            'code' => 'request_verification_failed',
            'message' => 'The request could not be verified. Reload the page and try again.',
        ], 403);
    }

    vv_fail_request('The request could not be verified. Reload the page and try again.', 403);
}

function vv_verify_write_request(): void
{
    $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    if (!in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
        return;
    }

    $contentLength = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);
    $postMaxBytes = vv_parse_ini_bytes((string) ini_get('post_max_size'));
    if ($postMaxBytes > 0 && $contentLength > $postMaxBytes) {
        vv_fail_request('The submitted data is larger than the server allows.', 413);
    }

    // Fetch Metadata is supplied by modern browsers and cannot be set by a
    // cross-site HTML form. Reject an explicitly cross-site navigation before
    // considering any token fallback.
    if (vv_fetch_metadata_is_cross_site()) {
        vv_reject_unverified_write_request();
    }

    // Same-origin Origin or Referer evidence is the primary protection. It is
    // independent of PHP sessions and cookies, so login, logout, cart, checkout,
    // admin updates and restored tabs all follow one stable rule.
    if (vv_request_has_source_headers()) {
        if (vv_request_source_is_explicitly_same_origin()) {
            if (!headers_sent()) {
                header('X-VV-Request-Security: ' . VV_REQUEST_SECURITY_VERSION);
            }
            return;
        }

        vv_reject_unverified_write_request();
    }

    // Some privacy tools remove both source headers. In that uncommon case,
    // accept only a short-lived application-signed token rendered by this site.
    $provided = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_POST['_csrf'] ?? '');
    if (is_string($provided) && $provided !== '' && vv_csrf_token_matches($provided)) {
        if (!headers_sent()) {
            header('X-VV-Request-Security: ' . VV_REQUEST_SECURITY_VERSION);
        }
        return;
    }

    vv_reject_unverified_write_request();
}

function vv_verify_csrf_for_unsafe_request(): void
{
    // Backward-compatible alias for older page code. All protection is now
    // handled by the stable request-security v7 policy above.
    vv_verify_write_request();
}

function vv_client_ip(): string
{
    $candidates = [];
    if (strtolower((string) vv_env('TRUST_PROXY', 'false')) === 'true') {
        $candidates[] = trim((string) ($_SERVER['HTTP_CF_CONNECTING_IP'] ?? ''));
        $candidates[] = trim(explode(',', (string) ($_SERVER['HTTP_X_FORWARDED_FOR'] ?? ''))[0]);
    }
    $candidates[] = trim((string) ($_SERVER['REMOTE_ADDR'] ?? ''));

    foreach ($candidates as $candidate) {
        if ($candidate !== '' && filter_var($candidate, FILTER_VALIDATE_IP)) {
            return $candidate;
        }
    }

    return 'unknown';
}

function vv_rate_limit_in_session(string $bucket, int $limit, int $windowSeconds, string $identity): array
{
    vv_session_start();
    $now = time();
    $key = hash('sha256', $bucket . '|' . $identity);
    $timestamps = $_SESSION['_rate_limits'][$key] ?? [];
    if (!is_array($timestamps)) {
        $timestamps = [];
    }

    $timestamps = array_values(array_filter(
        $timestamps,
        static fn ($timestamp): bool => is_int($timestamp) && $timestamp > $now - $windowSeconds,
    ));

    if (count($timestamps) >= $limit) {
        $_SESSION['_rate_limits'][$key] = $timestamps;
        return [
            'allowed' => false,
            'retry_after' => max(1, $windowSeconds - ($now - min($timestamps))),
        ];
    }

    $timestamps[] = $now;
    $_SESSION['_rate_limits'][$key] = $timestamps;
    return ['allowed' => true, 'retry_after' => 0];
}

function vv_rate_limit(string $bucket, int $limit, int $windowSeconds, ?string $identity = null): array
{
    $identity = $identity ?? vv_client_ip();
    $directory = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'velvet-vogue-rate-limits';
    if (!is_dir($directory)) {
        @mkdir($directory, 0700, true);
        @chmod($directory, 0700);
    }

    // Clear abandoned limiter files occasionally without adding work to every request.
    try {
        if (is_dir($directory) && random_int(1, 100) === 1) {
            $staleBefore = time() - 172800;
            foreach (glob($directory . DIRECTORY_SEPARATOR . '*.json') ?: [] as $candidate) {
                if (is_file($candidate) && filemtime($candidate) < $staleBefore) {
                    @unlink($candidate);
                }
            }
        }
    } catch (Throwable) {
        // Rate limiting must continue even when maintenance cannot run.
    }

    $key = hash('sha256', $bucket . '|' . $identity);
    $path = $directory . DIRECTORY_SEPARATOR . $key . '.json';
    $handle = @fopen($path, 'c+');
    if ($handle === false) {
        return vv_rate_limit_in_session($bucket, $limit, $windowSeconds, $identity);
    }

    $now = time();
    $allowed = true;
    $retryAfter = 0;

    try {
        if (!flock($handle, LOCK_EX)) {
            return vv_rate_limit_in_session($bucket, $limit, $windowSeconds, $identity);
        }

        $contents = stream_get_contents($handle);
        $timestamps = $contents ? json_decode($contents, true) : [];
        if (!is_array($timestamps)) {
            $timestamps = [];
        }

        $timestamps = array_values(array_filter($timestamps, static fn ($timestamp): bool => is_int($timestamp) && $timestamp > $now - $windowSeconds));

        if (count($timestamps) >= $limit) {
            $allowed = false;
            $retryAfter = max(1, $windowSeconds - ($now - min($timestamps)));
        } else {
            $timestamps[] = $now;
        }

        ftruncate($handle, 0);
        rewind($handle);
        fwrite($handle, json_encode($timestamps));
        fflush($handle);
        @chmod($path, 0600);
        flock($handle, LOCK_UN);
    } finally {
        fclose($handle);
    }

    return ['allowed' => $allowed, 'retry_after' => $retryAfter];
}

function vv_enforce_rate_limit(string $bucket, int $limit, int $windowSeconds, ?string $identity = null): void
{
    $result = vv_rate_limit($bucket, $limit, $windowSeconds, $identity);
    if (!$result['allowed']) {
        header('Retry-After: ' . $result['retry_after']);
        vv_fail_request('Too many attempts. Please wait and try again.', 429);
    }
}

function vv_e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function vv_normalize_email(string $email): string
{
    return strtolower(trim($email));
}

function vv_valid_name(string $value, int $maxLength = 80): bool
{
    $length = function_exists('mb_strlen') ? mb_strlen($value) : strlen($value);
    return $length >= 1 && $length <= $maxLength && !preg_match('/[<>\x00-\x1F\x7F]/u', $value);
}

function vv_upload_error_message(int $error): string
{
    return match ($error) {
        UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'The image is larger than the server allows.',
        UPLOAD_ERR_PARTIAL => 'The image upload was interrupted.',
        UPLOAD_ERR_NO_FILE => 'No image was selected.',
        default => 'The image could not be uploaded.',
    };
}

function vv_store_uploaded_image(array $file, string $targetDirectory, string $publicPrefix, int $maxBytes = 5242880): string
{
    $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($error !== UPLOAD_ERR_OK) {
        throw new RuntimeException(vv_upload_error_message($error));
    }

    $temporaryPath = (string) ($file['tmp_name'] ?? '');
    $size = (int) ($file['size'] ?? 0);
    if ($temporaryPath === '' || !is_uploaded_file($temporaryPath) || $size <= 0 || $size > $maxBytes) {
        throw new RuntimeException('The image is invalid or exceeds the upload limit.');
    }

    if (!class_exists('finfo')) {
        throw new RuntimeException('The server is missing the file information extension required for safe uploads.');
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = (string) $finfo->file($temporaryPath);
    $extensions = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
    ];

    $imageInfo = @getimagesize($temporaryPath);
    if (!isset($extensions[$mime]) || !is_array($imageInfo)) {
        throw new RuntimeException('Only valid JPG, PNG, WebP, or GIF images are allowed.');
    }

    $width = (int) ($imageInfo[0] ?? 0);
    $height = (int) ($imageInfo[1] ?? 0);
    if ($width < 1 || $height < 1 || $width * $height > 25000000) {
        throw new RuntimeException('The image dimensions are not supported.');
    }

    if (!is_dir($targetDirectory) && !mkdir($targetDirectory, 0755, true) && !is_dir($targetDirectory)) {
        throw new RuntimeException('The upload directory is unavailable.');
    }

    $canEncodeWebp = $mime !== 'image/gif' && function_exists('imagewebp');
    $extension = $canEncodeWebp ? 'webp' : $extensions[$mime];
    $fileName = bin2hex(random_bytes(16)) . '.' . $extension;
    $destination = rtrim($targetDirectory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $fileName;

    $stored = false;
    $gdLoaders = [
        'image/jpeg' => 'imagecreatefromjpeg',
        'image/png' => 'imagecreatefrompng',
        'image/webp' => 'imagecreatefromwebp',
        'image/gif' => 'imagecreatefromgif',
    ];

    // Re-encoding strips metadata and limits oversized images when GD is available.
    if (isset($gdLoaders[$mime]) && function_exists($gdLoaders[$mime]) && function_exists('imagecreatetruecolor')) {
        $source = @$gdLoaders[$mime]($temporaryPath);
        if ($source !== false) {
            $maxDimension = 2400;
            $scale = min(1, $maxDimension / max($width, $height));
            $targetWidth = max(1, (int) round($width * $scale));
            $targetHeight = max(1, (int) round($height * $scale));
            $canvas = imagecreatetruecolor($targetWidth, $targetHeight);

            if ($canvas !== false) {
                if (in_array($mime, ['image/png', 'image/webp', 'image/gif'], true)) {
                    imagealphablending($canvas, false);
                    imagesavealpha($canvas, true);
                    $transparent = imagecolorallocatealpha($canvas, 0, 0, 0, 127);
                    imagefilledrectangle($canvas, 0, 0, $targetWidth, $targetHeight, $transparent);
                }

                if (imagecopyresampled($canvas, $source, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height)) {
                    if ($canEncodeWebp) {
                        $stored = imagewebp($canvas, $destination, 82);
                    } else {
                        $stored = match ($mime) {
                            'image/jpeg' => imagejpeg($canvas, $destination, 85),
                            'image/png' => imagepng($canvas, $destination, 8),
                            'image/webp' => imagewebp($canvas, $destination, 82),
                            'image/gif' => imagegif($canvas, $destination),
                            default => false,
                        };
                    }
                }

                imagedestroy($canvas);
            }
            imagedestroy($source);
        }
    }

    if (!$stored) {
        $stored = move_uploaded_file($temporaryPath, $destination);
    }

    if (!$stored) {
        @unlink($destination);
        throw new RuntimeException('The image could not be saved.');
    }

    @chmod($destination, 0644);
    return trim($publicPrefix, '/') . '/' . $fileName;
}

function vv_require_logged_in(): int
{
    if (!headers_sent()) {
        header('Cache-Control: private, no-store, max-age=0');
        header('Pragma: no-cache');
    }

    if (!isset($_SESSION['userID'])) {
        vv_fail_request('Please sign in to continue.', 401);
    }

    global $pdo;
    if (isset($pdo) && $pdo instanceof PDO && !($GLOBALS['vv_session_user_validated'] ?? false)) {
        vv_sync_session_user($pdo);
    }

    if (!isset($_SESSION['userID'])) {
        vv_fail_request('Your account is unavailable. Please sign in again.', 401);
    }

    return (int) $_SESSION['userID'];
}

vv_send_security_headers();
if (!defined('VV_SKIP_SESSION_START') || VV_SKIP_SESSION_START !== true) {
    vv_session_start();
}

function vv_delete_public_file(string $relativePath, string $allowedDirectory): bool
{
    $root = realpath(vv_project_root());
    $allowed = realpath($allowedDirectory);
    if ($root === false || $allowed === false) {
        return false;
    }

    $normalized = ltrim(str_replace('\\', '/', $relativePath), '/');
    $candidate = realpath($root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $normalized));
    if ($candidate === false || !str_starts_with($candidate, $allowed . DIRECTORY_SEPARATOR) || !is_file($candidate)) {
        return false;
    }

    return unlink($candidate);
}

function vv_sanitize_rich_text(string $html): string
{
    $html = trim($html);
    if ($html === '') {
        return '';
    }

    $allowedTags = ['p', 'br', 'strong', 'b', 'em', 'i', 'u', 'ul', 'ol', 'li', 'h2', 'h3', 'blockquote', 'a'];
    if (!class_exists('DOMDocument')) {
        return strip_tags($html, '<p><br><strong><b><em><i><u><ul><ol><li><h2><h3><blockquote>');
    }

    $document = new DOMDocument('1.0', 'UTF-8');
    $previousLibxmlState = libxml_use_internal_errors(true);
    $loaded = $document->loadHTML(
        '<?xml encoding="utf-8" ?><div id="vv-root">' . $html . '</div>',
        LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD,
    );
    libxml_clear_errors();
    libxml_use_internal_errors($previousLibxmlState);

    if (!$loaded) {
        return '';
    }

    $root = $document->getElementById('vv-root');
    if (!$root) {
        return '';
    }

    $sanitizeNode = function (DOMNode $node) use (&$sanitizeNode, $allowedTags): void {
        foreach (iterator_to_array($node->childNodes) as $child) {
            if (!$child instanceof DOMElement) {
                continue;
            }

            // Clean descendants before an unsupported wrapper is removed.
            $sanitizeNode($child);
            $tag = strtolower($child->tagName);

            if (!in_array($tag, $allowedTags, true)) {
                while ($child->firstChild) {
                    $node->insertBefore($child->firstChild, $child);
                }
                $node->removeChild($child);
                continue;
            }

            foreach (iterator_to_array($child->attributes) as $attribute) {
                $name = strtolower($attribute->name);
                $value = trim($attribute->value);
                $isAllowedLinkAttribute = $tag === 'a' && in_array($name, ['href', 'title'], true);
                $isSafeHref = $name !== 'href'
                    || preg_match('#^(https?://|/(?!/)|\#)#i', $value) === 1;

                if (!$isAllowedLinkAttribute || !$isSafeHref) {
                    $child->removeAttribute($attribute->name);
                }
            }

            if ($tag === 'a') {
                $child->setAttribute('rel', 'noopener noreferrer');
            }
        }
    };

    $sanitizeNode($root);

    $clean = '';
    foreach ($root->childNodes as $child) {
        $clean .= $document->saveHTML($child);
    }

    return trim($clean);
}

function vv_public_asset_url(?string $path, string $fallback = '../Assets/images/fallback.webp'): string
{
    $normalized = str_replace('\\', '/', trim((string) $path));
    if (str_contains($normalized, "\0")) {
        return $fallback;
    }

    while (str_starts_with($normalized, '../')) {
        $normalized = substr($normalized, 3);
    }
    $normalized = ltrim($normalized, '/');

    // Existing installations stored some upload paths with a lowercase admin prefix.
    if (str_starts_with(strtolower($normalized), 'admin/image/')) {
        $normalized = 'Admin/image/' . substr($normalized, strlen('admin/image/'));
    } elseif (str_starts_with(strtolower($normalized), 'assets/images/')) {
        $normalized = 'Assets/images/' . substr($normalized, strlen('assets/images/'));
    }

    $allowedPrefix = str_starts_with($normalized, 'Admin/image/')
        || str_starts_with($normalized, 'Assets/images/');
    $allowedExtension = preg_match('/\.(?:jpe?g|png|webp|gif|avif)$/i', $normalized) === 1;

    if (
        !$allowedPrefix
        || !$allowedExtension
        || preg_match('#(^|/)\.\.?(/|$)#', $normalized)
        || !preg_match('#^[A-Za-z0-9][A-Za-z0-9_ ./-]*$#', $normalized)
    ) {
        return $fallback;
    }

    if (!str_ends_with(strtolower($normalized), '.webp')) {
        $webpPath = preg_replace('/\.(?:jpe?g|png)$/i', '.webp', $normalized);
        if (is_string($webpPath) && $webpPath !== $normalized && is_file(vv_project_root() . '/' . $webpPath)) {
            $normalized = $webpPath;
        }
    }

    return '../' . $normalized;
}

function vv_admin_public_url(?string $path): string
{
    return vv_public_asset_url($path);
}
