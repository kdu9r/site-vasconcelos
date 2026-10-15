<?php
declare(strict_types=1);

function config(string $key, mixed $default = null): mixed
{
    $value = $GLOBALS['__config'];
    foreach (explode('.', $key) as $part) {
        if (!is_array($value) || !array_key_exists($part, $value)) {
            return $default;
        }
        $value = $value[$part];
    }
    return $value;
}

/** Escapa texto para HTML. Use em TODA saída de dados. */
function e(mixed $value): string
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function url(string $path = '/', array $query = []): string
{
    $q = $query ? '?' . http_build_query(array_filter($query, fn($v) => $v !== null && $v !== '')) : '';
    return rtrim((string)config('site_url'), '/') . '/' . ltrim($path, '/') . ($q === '?' ? '' : $q);
}

function asset(string $path): string
{
    $file = rtrim((string)config('public_dir'), '/') . '/assets/' . ltrim($path, '/');
    $v = is_file($file) ? (string)filemtime($file) : '1';
    return '/assets/' . ltrim($path, '/') . '?v=' . $v;
}

function redirect(string $to, int $status = 302): never
{
    header('Location: ' . $to, true, $status);
    exit;
}

function abort(int $status = 404): never
{
    http_response_code($status);
    if ($status === 404) {
        view('404', ['titulo_pagina' => 'Página não encontrada']);
    } else {
        echo 'Erro ' . $status;
    }
    exit;
}

function csp_nonce(): string
{
    static $nonce = null;
    return $nonce ??= base64_encode(random_bytes(16));
}

function send_security_headers(bool $painel = false): void
{
    $nonce = csp_nonce();
    $imgHosts = "'self' data: blob: https://www.vasconceloscorretor.com https://*.tile.openstreetmap.org https://i.ytimg.com";
    // Google/Meta só entram na política quando há IDs configurados em Painel → Integrações (nunca no painel)
    $x = $painel ? [] : integracoes_csp();
    $csp = "default-src 'self'; "
        . "script-src 'self' 'nonce-{$nonce}'" . (isset($x['script']) ? ' ' . $x['script'] : '') . "; "
        . "style-src 'self' https://fonts.googleapis.com; "
        . "font-src 'self' https://fonts.gstatic.com; "
        . "img-src {$imgHosts}" . (isset($x['img']) ? ' ' . $x['img'] : '') . "; "
        . "frame-src https://www.openstreetmap.org https://www.youtube-nocookie.com" . (isset($x['frame']) ? ' ' . $x['frame'] : '') . "; "
        . "connect-src 'self'" . (isset($x['connect']) ? ' ' . $x['connect'] : '') . "; "
        . "form-action 'self' https://wa.me; "
        . "base-uri 'self'; object-src 'none'; frame-ancestors 'none'; upgrade-insecure-requests";
    header('Content-Security-Policy: ' . $csp);
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=()');
    header('Cross-Origin-Opener-Policy: same-origin');
    if (is_https()) {
        header('Strict-Transport-Security: max-age=31536000');
    }
    if (config('modo_teste')) {
        header('X-Robots-Tag: noindex, nofollow');
    }
    if ($painel) {
        header('Cache-Control: no-store, max-age=0');
        header('X-Robots-Tag: noindex, nofollow');
    }
}

function is_https(): bool
{
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
        || (($_SERVER['SERVER_PORT'] ?? '') === '443');
}

/** Renderiza uma view dentro do layout (público ou painel). */
function view(string $name, array $data = [], ?string $layout = 'layout'): void
{
    $file = APP_DIR . '/views/' . $name . '.php';
    if (!is_file($file)) {
        throw new RuntimeException("View não encontrada: {$name}");
    }
    extract($data, EXTR_SKIP);
    ob_start();
    require $file;
    $conteudo = ob_get_clean();
    if ($layout === null) {
        echo $conteudo;
        return;
    }
    require APP_DIR . '/views/' . $layout . '.php';
}

function partial(string $name, array $data = []): void
{
    extract($data, EXTR_SKIP);
    require APP_DIR . '/views/partials/' . $name . '.php';
}

/* ---------- Sessão, CSRF e mensagens ---------- */

function start_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    $secure = is_https();
    session_name($secure ? '__Host-vc_sess' : 'vc_sess');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => $secure,
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.gc_maxlifetime', '28800');
    session_start();
}

function csrf_token(): string
{
    start_session();
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}

function csrf_verify(): void
{
    start_session();
    $sent = $_POST['_csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if (!is_string($sent) || empty($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], $sent)) {
        http_response_code(419);
        exit('Sessão expirada. Volte e tente de novo.');
    }
    // Defesa extra: a requisição precisa vir do próprio site
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    $origemHost = $origin !== '' ? (string)parse_url($origin, PHP_URL_HOST) . (parse_url($origin, PHP_URL_PORT) ? ':' . parse_url($origin, PHP_URL_PORT) : '') : '';
    if ($origin !== '' && strtolower($origemHost) !== strtolower((string)($_SERVER['HTTP_HOST'] ?? ''))) {
        http_response_code(403);
        exit('Origem inválida.');
    }
}

function flash(string $tipo, string $mensagem): void
{
    start_session();
    $_SESSION['flash'][] = ['tipo' => $tipo, 'mensagem' => $mensagem];
}

function tem_cookie_sessao(): bool
{
    return isset($_COOKIE['__Host-vc_sess']) || isset($_COOKIE['vc_sess']);
}

function take_flashes(): array
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        return [];
    }
    $f = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $f;
}

/* ---------- Utilidades ---------- */

function client_ip_hash(): string
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    return hash_hmac('sha256', $ip, (string)config('app_secret'));
}

function slugify(string $text): string
{
    $text = mb_strtolower(trim($text));
    $map = ['á'=>'a','à'=>'a','â'=>'a','ã'=>'a','ä'=>'a','é'=>'e','è'=>'e','ê'=>'e','ë'=>'e','í'=>'i','ì'=>'i','î'=>'i','ï'=>'i',
            'ó'=>'o','ò'=>'o','ô'=>'o','õ'=>'o','ö'=>'o','ú'=>'u','ù'=>'u','û'=>'u','ü'=>'u','ç'=>'c','ñ'=>'n','²'=>'2','º'=>'','ª'=>''];
    $text = strtr($text, $map);
    $text = preg_replace('/[^a-z0-9]+/', '-', $text) ?? '';
    $text = trim($text, '-');
    return mb_substr($text, 0, 120) ?: 'imovel';
}

function money(float|string|null $value, bool $compact = false): string
{
    if ($value === null || $value === '') {
        return 'Sob consulta';
    }
    $v = (float)$value;
    if ($compact) {
        if ($v >= 1_000_000) {
            $m = $v / 1_000_000;
            $txt = rtrim(rtrim(number_format($m, 2, ',', '.'), '0'), ',');
            return 'R$ ' . $txt . ($m >= 2 ? ' milhões' : ' milhão');
        }
        if ($v >= 100_000) {
            $k = $v / 1000;
            return 'R$ ' . rtrim(rtrim(number_format($k, 1, ',', '.'), '0'), ',') . ' mil';
        }
    }
    return 'R$ ' . number_format($v, $v == floor($v) ? 0 : 2, ',', '.');
}

function num(float|string|null $value): string
{
    if ($value === null || $value === '') {
        return '';
    }
    $v = (float)$value;
    return number_format($v, $v == floor($v) ? 0 : 2, ',', '.');
}

/** Converte "1.234,56" ou "1234.56" em float; vazio vira null. */
function parse_decimal(mixed $value): ?float
{
    if ($value === null) {
        return null;
    }
    $s = trim((string)$value);
    if ($s === '') {
        return null;
    }
    $s = preg_replace('/[^\d,.\-]/', '', $s) ?? '';
    if (str_contains($s, ',')) {
        $s = str_replace('.', '', $s);
        $s = str_replace(',', '.', $s);
    } elseif (preg_match('/^\d{1,3}(\.\d{3})+$/', $s)) {
        // "810.000" = oitocentos e dez mil (ponto como separador de milhar)
        $s = str_replace('.', '', $s);
    }
    return is_numeric($s) ? (float)$s : null;
}

function parse_int(mixed $value): ?int
{
    $s = trim((string)($value ?? ''));
    return $s === '' || !ctype_digit($s) ? null : (int)$s;
}

function log_atividade(string $acao, ?string $detalhe = null): void
{
    try {
        q('INSERT INTO registro_atividades (usuario_id, acao, detalhe, ip_hash) VALUES (?,?,?,?)', [
            current_user()['id'] ?? null, $acao, $detalhe !== null ? mb_substr($detalhe, 0, 255) : null, client_ip_hash(),
        ]);
    } catch (Throwable $e) {
        error_log('log_atividade: ' . $e->getMessage());
    }
}

function request_path(): string
{
    $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    $path = rawurldecode($path);
    return $path === '/' ? '/' : rtrim($path, '/');
}

function json_response(array $data, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

/** Foto do corretor: assets/img/equipe/<primeiro nome>.jpg (ex.: fred.jpg). Null se não houver. */
function foto_corretor(array $c): ?string
{
    $arq = 'img/equipe/' . slugify(strtok((string)($c['nome'] ?? ''), ' ')) . '.jpg';
    return is_file(rtrim((string)config('public_dir'), '/') . '/assets/' . $arq) ? asset($arq) : null;
}
