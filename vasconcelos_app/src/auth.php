<?php
declare(strict_types=1);

const LOGIN_MAX_FALHAS_EMAIL = 5;     // por e-mail em 15 min
const LOGIN_MAX_FALHAS_IP = 10;       // por IP em 15 min
const SESSAO_INATIVIDADE = 7200;      // 2 h sem uso → sai
const SESSAO_MAXIMA = 43200;          // 12 h no máximo
const SENHA_MINIMA = 10;

function password_algo(): string|int
{
    return defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_BCRYPT;
}

function hash_senha(string $senha): string
{
    return password_hash($senha, password_algo());
}

function senha_valida(string $senha): ?string
{
    if (mb_strlen($senha) < SENHA_MINIMA) {
        return 'A senha precisa ter pelo menos ' . SENHA_MINIMA . ' caracteres.';
    }
    if (!preg_match('/[A-Za-z]/', $senha) || !preg_match('/\d/', $senha)) {
        return 'Use letras e números na senha.';
    }
    return null;
}

function login_bloqueado(string $email): bool
{
    $ip = client_ip_hash();
    $porIp = (int)q("SELECT COUNT(*) FROM tentativas_login WHERE ip_hash = ? AND sucesso = 0 AND criado_em > (NOW() - INTERVAL 15 MINUTE)", [$ip])->fetchColumn();
    $porEmail = (int)q("SELECT COUNT(*) FROM tentativas_login WHERE email = ? AND sucesso = 0 AND criado_em > (NOW() - INTERVAL 15 MINUTE)", [$email])->fetchColumn();
    return $porIp >= LOGIN_MAX_FALHAS_IP || $porEmail >= LOGIN_MAX_FALHAS_EMAIL;
}

function registrar_tentativa(string $email, bool $sucesso): void
{
    q('INSERT INTO tentativas_login (ip_hash, email, sucesso) VALUES (?,?,?)', [client_ip_hash(), $email, $sucesso ? 1 : 0]);
    // limpeza ocasional
    if (random_int(1, 50) === 1) {
        q("DELETE FROM tentativas_login WHERE criado_em < (NOW() - INTERVAL 30 DAY)");
    }
}

/**
 * Primeira etapa do login. Retorna 'ok', '2fa', ou uma mensagem de erro.
 */
function tentar_login(string $email, string $senha): string
{
    $email = mb_strtolower(trim($email));
    if ($email === '' || $senha === '') {
        return 'Informe e-mail e senha.';
    }
    if (login_bloqueado($email)) {
        return 'Muitas tentativas. Aguarde 15 minutos e tente de novo.';
    }

    $user = q('SELECT * FROM usuarios WHERE email = ? AND ativo = 1', [$email])->fetch();
    // Compara mesmo quando o usuário não existe, para não revelar quais e-mails existem
    $hash = $user['senha_hash'] ?? '$2y$10$usesomesillystringfore7hnbRJHxXVLeakoG8K30oukPsA.ztMG';
    $ok = password_verify($senha, $hash) && $user;

    if (!$ok) {
        registrar_tentativa($email, false);
        usleep(random_int(200_000, 500_000));
        return 'E-mail ou senha incorretos.';
    }

    if (password_needs_rehash($user['senha_hash'], password_algo())) {
        q('UPDATE usuarios SET senha_hash = ? WHERE id = ?', [hash_senha($senha), $user['id']]);
    }

    start_session();
    session_regenerate_id(true);
    if ((int)$user['totp_ativo'] === 1 && !empty($user['totp_secret'])) {
        $_SESSION['pending_uid'] = (int)$user['id'];
        $_SESSION['pending_at'] = time();
        return '2fa';
    }
    concluir_login((int)$user['id']);
    registrar_tentativa($email, true);
    return 'ok';
}

function verificar_2fa(string $codigo): string
{
    start_session();
    $uid = (int)($_SESSION['pending_uid'] ?? 0);
    if (!$uid || time() - (int)($_SESSION['pending_at'] ?? 0) > 300) {
        unset($_SESSION['pending_uid'], $_SESSION['pending_at']);
        return 'expirado';
    }
    $user = q('SELECT id, email, totp_secret FROM usuarios WHERE id = ? AND ativo = 1', [$uid])->fetch();
    if (!$user) {
        return 'expirado';
    }
    if (login_bloqueado($user['email'])) {
        return 'Muitas tentativas. Aguarde 15 minutos.';
    }
    if (!totp_verify((string)$user['totp_secret'], $codigo)) {
        registrar_tentativa($user['email'], false);
        return 'Código incorreto.';
    }
    unset($_SESSION['pending_uid'], $_SESSION['pending_at']);
    concluir_login($uid);
    registrar_tentativa($user['email'], true);
    return 'ok';
}

function concluir_login(int $uid): void
{
    start_session();
    session_regenerate_id(true);
    $_SESSION['uid'] = $uid;
    $_SESSION['login_at'] = time();
    $_SESSION['last_seen'] = time();
    $_SESSION['ua'] = hash('sha256', $_SERVER['HTTP_USER_AGENT'] ?? '');
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
    q('UPDATE usuarios SET ultimo_login = NOW() WHERE id = ?', [$uid]);
    $GLOBALS['__user'] = null;
    log_atividade('login');
}

function logout(): void
{
    start_session();
    log_atividade('logout');
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', ['expires' => time() - 42000, 'path' => $p['path'], 'secure' => $p['secure'], 'httponly' => true, 'samesite' => 'Strict']);
    }
    session_destroy();
}

function current_user(): ?array
{
    if (array_key_exists('__user', $GLOBALS) && $GLOBALS['__user'] !== null) {
        return $GLOBALS['__user'];
    }
    start_session();
    $uid = (int)($_SESSION['uid'] ?? 0);
    if (!$uid) {
        return null;
    }
    $agora = time();
    $expirou = $agora - (int)($_SESSION['last_seen'] ?? 0) > SESSAO_INATIVIDADE
        || $agora - (int)($_SESSION['login_at'] ?? 0) > SESSAO_MAXIMA
        || !hash_equals((string)($_SESSION['ua'] ?? ''), hash('sha256', $_SERVER['HTTP_USER_AGENT'] ?? ''));
    if ($expirou) {
        $_SESSION = [];
        session_regenerate_id(true);
        return null;
    }
    $user = q('SELECT id, nome, email, papel, creci, whatsapp, totp_ativo FROM usuarios WHERE id = ? AND ativo = 1', [$uid])->fetch();
    if (!$user) {
        $_SESSION = [];
        return null;
    }
    $_SESSION['last_seen'] = $agora;
    return $GLOBALS['__user'] = $user;
}

function require_login(): array
{
    $user = current_user();
    if (!$user) {
        flash('aviso', 'Faça login para continuar.');
        redirect('/painel/entrar');
    }
    return $user;
}

function require_admin(): array
{
    $user = require_login();
    if ($user['papel'] !== 'admin') {
        abort(403);
    }
    return $user;
}
