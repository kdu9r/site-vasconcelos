<?php
/**
 * Migração completa pela linha de comando (alternativa à tela "Migração" do painel).
 * Uso:  php scripts/importar_code49.php
 */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__ . '/../bootstrap.php';

$estado = code49_estado_fila();
if (!$estado['pendentes']) {
    echo "Buscando lista de imóveis na Code49...\n";
    echo code49_montar_fila() . " imóveis na fila.\n";
}
while (true) {
    try {
        $r = code49_processar_proximo();
    } catch (Code49LimiteException $e) {
        echo $e->getMessage() . "\n";
        if ($e->esperarSegundos > 120) exit(1);
        sleep($e->esperarSegundos);
        continue;
    }
    if ($r['situacao'] === 'fim') break;
    printf("[%d/%d] Cód. %s: %s %s\n", $r['feitos'], $r['total'], $r['codigo'] ?? '?', $r['situacao'], $r['obs'] ? "({$r['obs']})" : '');
    if ($r['restantes'] === 0) break;
    usleep(1_100_000);
}
echo "Concluído. Relatório: storage/relatorio_migracao.csv\n";
