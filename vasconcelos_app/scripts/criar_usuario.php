<?php
/**
 * Cria um usuário pela linha de comando.
 * Uso:  php scripts/criar_usuario.php "Nome" email@exemplo.com admin|corretor
 * A senha é pedida sem aparecer na tela.
 */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__ . '/../bootstrap.php';

[$_, $nome, $email, $papel] = array_pad($argv, 4, null);
if (!$nome || !filter_var((string)$email, FILTER_VALIDATE_EMAIL)) {
    exit("Uso: php scripts/criar_usuario.php \"Nome\" email@exemplo.com admin|corretor\n");
}
$papel = $papel === 'admin' ? 'admin' : 'corretor';
echo 'Senha: ';
system('stty -echo 2>/dev/null');
$senha = trim((string)fgets(STDIN));
system('stty echo 2>/dev/null');
echo "\n";
if ($erro = senha_valida($senha)) exit($erro . "\n");
q('INSERT INTO usuarios (nome, email, senha_hash, papel) VALUES (?,?,?,?)', [$nome, mb_strtolower($email), hash_senha($senha), $papel]);
echo "Usuário criado: $email ($papel)\n";
