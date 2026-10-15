<?php
// Roteador para testar no computador: php -S localhost:8080 -t public_html vasconcelos_app/scripts/servidor_local.php
$arquivo = realpath(__DIR__ . '/../../public_html' . parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
if ($arquivo && is_file($arquivo) && !str_ends_with($arquivo, '.php')) {
    return false;
}
require __DIR__ . '/../../public_html/index.php';
