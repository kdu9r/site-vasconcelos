<?php
declare(strict_types=1);

// O código do site fica FORA da pasta pública (../vasconcelos_app), por segurança.
require dirname(__DIR__) . '/vasconcelos_app/bootstrap.php';
require APP_DIR . '/src/app.php';

run_app();
