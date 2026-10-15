<?php
declare(strict_types=1);

require APP_DIR . '/src/rotas_publicas.php';
require APP_DIR . '/src/rotas_painel.php';

function run_app(): void
{
    $path = request_path();
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

    try {
        if (str_starts_with($path, '/painel')) {
            send_security_headers(true);
            start_session();
            rotear_painel($path, $method);
            return;
        }
        send_security_headers();
        if (tem_cookie_sessao()) {
            start_session(); // para mostrar avisos (ex.: código não encontrado) sem criar sessão para todo visitante
        }
        if (!in_array($method, ['GET', 'HEAD'], true)) {
            abort(405);
        }
        rotear_publico($path);
    } catch (Throwable $e) {
        error_log('[' . date('c') . '] ' . $e->getMessage() . ' em ' . $e->getFile() . ':' . $e->getLine());
        http_response_code(500);
        if (config('debug')) {
            echo '<pre>' . e((string)$e) . '</pre>';
        } else {
            echo 'Ops, algo deu errado. Tente de novo em instantes.';
        }
    }
}
