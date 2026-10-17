<?php
/**
 * Backup do site: banco de dados + fotos enviadas pelo painel.
 *
 * Uso (terminal ou "Trabalhos Cron" do cPanel, 1 vez por semana):
 *   php /home/USUARIO/vasconcelos_app/scripts/backup.php
 *
 * Gera em vasconcelos_app/storage/backups/ (pasta bloqueada para a internet):
 *   banco-AAAA-MM-DD.sql.gz   cópia completa do banco (guarda as 8 últimas = 2 meses)
 *   fotos.zip                 todas as fotos de imóveis, condomínios e blog (só a mais recente)
 *   ultimo.txt                resumo do último backup
 *
 * Opções:  --sem-fotos   só o banco (mais rápido)
 *
 * Restaurar o banco: cPanel → phpMyAdmin → banco → Importar → envie o .sql.gz.
 * Restaurar as fotos: descompacte o fotos.zip dentro de public_html/uploads/.
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit; // só roda pelo terminal / cron
}
require __DIR__ . '/../bootstrap.php';

const BACKUP_MANTER = 8;

@set_time_limit(0);
ini_set('memory_limit', '256M');

$semFotos = in_array('--sem-fotos', $argv, true);
$dir = APP_DIR . '/storage/backups';
if (!is_dir($dir) && !mkdir($dir, 0700, true) && !is_dir($dir)) {
    fwrite(STDERR, "Não consegui criar a pasta $dir\n");
    exit(1);
}

$inicio = microtime(true);
$resumo = [];

try {
    /* ---------- Banco ---------- */
    $arqBanco = $dir . '/banco-' . date('Y-m-d') . '.sql.gz';
    $tmp = $arqBanco . '.tmp';
    [$tabelas, $linhas] = backup_banco($tmp);
    rename($tmp, $arqBanco);
    @chmod($arqBanco, 0600);
    $resumo[] = sprintf('Banco: %s (%d tabelas, %d linhas, %s)', basename($arqBanco), $tabelas, $linhas, tamanho(filesize($arqBanco)));

    // mantém só os mais recentes
    $antigos = glob($dir . '/banco-*.sql.gz') ?: [];
    rsort($antigos);
    foreach (array_slice($antigos, BACKUP_MANTER) as $velho) {
        @unlink($velho);
    }

    /* ---------- Fotos ---------- */
    if (!$semFotos) {
        $uploads = rtrim((string)config('public_dir'), '/') . '/uploads';
        $arqFotos = $dir . '/fotos.zip';
        [$qtd, $bytes] = backup_fotos($uploads, $arqFotos . '.tmp');
        rename($arqFotos . '.tmp', $arqFotos);
        @chmod($arqFotos, 0600);
        $resumo[] = sprintf('Fotos: fotos.zip (%d arquivos, %s)', $qtd, tamanho($bytes));
    }

    $resumo[] = sprintf('Concluído em %s, em %.0f s.', date('d/m/Y H:i'), microtime(true) - $inicio);
    file_put_contents($dir . '/ultimo.txt', implode("\n", $resumo) . "\n");
    echo implode("\n", $resumo), "\n";
} catch (Throwable $e) {
    @unlink($dir . '/banco-' . date('Y-m-d') . '.sql.gz.tmp');
    @unlink($dir . '/fotos.zip.tmp');
    $msg = '[' . date('c') . '] BACKUP FALHOU: ' . $e->getMessage();
    error_log($msg);
    file_put_contents($dir . '/ultimo.txt', $msg . "\n");
    fwrite(STDERR, $msg . "\n");
    exit(1); // o cron do cPanel manda e-mail quando há saída de erro
}

/**
 * Exporta todas as tabelas em SQL (estrutura + dados), comprimido.
 * Feito em PHP puro porque o mysqldump nem sempre pode ser chamado na hospedagem compartilhada.
 * @return array{0:int,1:int} [tabelas, linhas]
 */
function backup_banco(string $destino): array
{
    $gz = gzopen($destino, 'wb6');
    if (!$gz) {
        throw new RuntimeException("Não consegui gravar $destino");
    }
    $w = function (string $s) use ($gz): void {
        if (gzwrite($gz, $s) === false) {
            throw new RuntimeException('Falha ao gravar o backup (disco cheio?)');
        }
    };
    $pdo = db();
    $w("-- Backup do site " . config('site_nome') . " em " . date('Y-m-d H:i:s') . "\n");
    $w("SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS = 0;\nSET time_zone = '-03:00';\n\n");

    $tabelas = $pdo->query("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'")->fetchAll(PDO::FETCH_COLUMN);
    $total = 0;
    foreach ($tabelas as $t) {
        $criar = $pdo->query('SHOW CREATE TABLE `' . str_replace('`', '``', $t) . '`')->fetch(PDO::FETCH_NUM)[1];
        $w("DROP TABLE IF EXISTS `$t`;\n$criar;\n\n");
        // leitura sem buffer: não carrega a tabela inteira na memória
        $pdo->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, false);
        $st = $pdo->query('SELECT * FROM `' . str_replace('`', '``', $t) . '`');
        $lote = [];
        $colunas = null;
        while ($linha = $st->fetch(PDO::FETCH_ASSOC)) {
            $colunas ??= '(`' . implode('`,`', array_keys($linha)) . '`)';
            $lote[] = '(' . implode(',', array_map(fn($v) => $v === null ? 'NULL' : $pdo->quote((string)$v), $linha)) . ')';
            $total++;
            if (count($lote) >= 200) {
                $w("INSERT INTO `$t` $colunas VALUES\n" . implode(",\n", $lote) . ";\n");
                $lote = [];
            }
        }
        if ($lote) {
            $w("INSERT INTO `$t` $colunas VALUES\n" . implode(",\n", $lote) . ";\n");
        }
        $st->closeCursor();
        $pdo->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, true);
        $w("\n");
    }
    $w("SET FOREIGN_KEY_CHECKS = 1;\n");
    gzclose($gz);
    return [count($tabelas), $total];
}

/**
 * Junta as fotos num .zip sem recomprimir (JPEG já é comprimido: assim é rápido e leve para o servidor).
 * @return array{0:int,1:int} [arquivos, bytes]
 */
function backup_fotos(string $origem, string $destino): array
{
    if (!class_exists(ZipArchive::class)) {
        throw new RuntimeException('A extensão zip do PHP não está ativa (cPanel → Selecionar versão do PHP → marque "zip").');
    }
    @unlink($destino);
    $zip = new ZipArchive();
    if ($zip->open($destino, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        throw new RuntimeException("Não consegui criar $destino");
    }
    $qtd = 0;
    $bytes = 0;
    if (is_dir($origem)) {
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($origem, FilesystemIterator::SKIP_DOTS));
        foreach ($it as $f) {
            /** @var SplFileInfo $f */
            if (!$f->isFile() || !preg_match('/\.(jpe?g|png|webp)$/i', $f->getFilename())) {
                continue;
            }
            $nome = substr($f->getPathname(), strlen($origem) + 1);
            $zip->addFile($f->getPathname(), $nome);
            $zip->setCompressionName($nome, ZipArchive::CM_STORE);
            $qtd++;
            $bytes += $f->getSize();
        }
    }
    if (!$zip->close()) {
        throw new RuntimeException('Falha ao fechar o fotos.zip (disco cheio?)');
    }
    return [$qtd, $bytes];
}

function tamanho(int|false $b): string
{
    $b = (int)$b;
    return $b >= 1_048_576 ? number_format($b / 1_048_576, 1, ',', '.') . ' MB' : number_format($b / 1024, 0, ',', '.') . ' KB';
}
