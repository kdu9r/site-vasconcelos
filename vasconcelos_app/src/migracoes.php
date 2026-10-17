<?php
declare(strict_types=1);

/*
 * Atualizações do banco que acompanham o código.
 * O schema.sql só cria tabelas que ainda não existem; colunas novas em bancos que já estão
 * no ar entram por aqui, sozinhas, na primeira visita depois da publicação.
 * Regras: nunca mude uma migração já publicada; só acrescente a próxima no fim da lista.
 * Cada uma confere antes se já foi aplicada (instalação nova pelo schema.sql já vem com tudo).
 */

function migracoes(): array
{
    return [
        1 => function (): void {
            if (!coluna_existe('usuarios', 'sessao_versao')) {
                q("ALTER TABLE usuarios ADD COLUMN sessao_versao INT UNSIGNED NOT NULL DEFAULT 0 AFTER totp_ativo");
            }
        },
    ];
}

function coluna_existe(string $tabela, string $coluna): bool
{
    return (bool)q('SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?', [$tabela, $coluna])->fetchColumn();
}

function migrar_banco(): void
{
    $lista = migracoes();
    $atual = (int)(config_site('db_versao') ?? 0);
    $ultima = max(array_keys($lista));
    if ($atual >= $ultima) {
        return; // caso normal: uma comparação, sem consulta extra
    }
    foreach ($lista as $n => $migracao) {
        if ($n <= $atual) {
            continue;
        }
        try {
            $migracao();
        } catch (Throwable $e) {
            // não derruba o site: tenta de novo na próxima visita e deixa registrado
            error_log('[' . date('c') . "] migração $n falhou: " . $e->getMessage());
            return;
        }
        salvar_config_site(['db_versao' => (string)$n]);
        error_log('[' . date('c') . "] banco atualizado para a versão $n");
    }
}
