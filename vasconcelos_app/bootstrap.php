<?php
declare(strict_types=1);

define('APP_DIR', __DIR__);

$configFile = APP_DIR . '/config.php';
if (!is_file($configFile)) {
    http_response_code(500);
    exit('Configuração ausente: copie config.example.php para config.php.');
}
$GLOBALS['__config'] = require $configFile;

date_default_timezone_set('America/Sao_Paulo');
mb_internal_encoding('UTF-8');

$debug = (bool)($GLOBALS['__config']['debug'] ?? false);
ini_set('display_errors', $debug ? '1' : '0');
ini_set('log_errors', '1');
ini_set('error_log', APP_DIR . '/storage/erros.log');
error_reporting(E_ALL);

require APP_DIR . '/src/helpers.php';
require APP_DIR . '/src/db.php';
require APP_DIR . '/src/totp.php';
require APP_DIR . '/src/auth.php';
require APP_DIR . '/src/imoveis.php';
require APP_DIR . '/src/fotos.php';
require APP_DIR . '/src/caracteristicas.php';
require APP_DIR . '/src/feed.php';
require APP_DIR . '/src/blog.php';
require APP_DIR . '/src/condominios.php';
require APP_DIR . '/src/code49.php';
require APP_DIR . '/src/integracoes.php';
require APP_DIR . '/src/preencher.php';
