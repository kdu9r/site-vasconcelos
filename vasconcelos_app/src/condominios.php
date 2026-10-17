<?php
declare(strict_types=1);

/**
 * Condomínios: cadastrados uma vez e ligados aos imóveis.
 * O imóvel herda fotos das áreas comuns, itens de lazer e endereço.
 * Por padrão, nome e endereço ficam só no painel (nome_publico = 0).
 */

function condominio_por_id(?int $id): ?array
{
    if (!$id) return null;
    $c = q('SELECT * FROM condominios WHERE id = ?', [$id])->fetch();
    return $c ?: null;
}

/** Só condomínios com nome liberado têm página pública. */
function condominio_publico_por_slug(string $slug): ?array
{
    $c = q('SELECT * FROM condominios WHERE slug = ? AND nome_publico = 1', [$slug])->fetch();
    return $c ?: null;
}

function condominio_url(array $c): string
{
    return '/condominio/' . $c['slug'];
}

function condominios_todos(): array
{
    return q('SELECT c.*,
        (SELECT COUNT(*) FROM imoveis i WHERE i.condominio_id = c.id) AS qtd_imoveis,
        (SELECT COUNT(*) FROM condominio_fotos f WHERE f.condominio_id = c.id) AS qtd_fotos,
        (SELECT f.id FROM condominio_fotos f WHERE f.condominio_id = c.id ORDER BY f.ordem, f.id LIMIT 1) AS capa_id
        FROM condominios c ORDER BY c.nome')->fetchAll();
}

function fotos_condominio(int $id): array
{
    return q('SELECT * FROM condominio_fotos WHERE condominio_id = ? ORDER BY ordem, id', [$id])->fetchAll();
}

function lazer_lista(?array $c): array
{
    if (!$c) return [];
    $linhas = preg_split('/\r\n|\r|\n/', (string)($c['lazer'] ?? '')) ?: [];
    return array_values(array_filter(array_map('trim', $linhas), fn($l) => $l !== ''));
}

/** Fotos que aparecem no anúncio, na ordem escolhida (as do condomínio entram onde o corretor colocou). */
function fotos_completas(array $im): array
{
    $fotos = fotos_do_imovel((int)$im['id'], true);
    foreach ($fotos as &$f) {
        if (!empty($f['cond_arquivo'])) {
            $f['legenda'] = $f['legenda'] ?: ($f['cond_legenda'] ?: 'Área comum do condomínio');
        }
    }
    unset($f);
    return $fotos;
}

/** @return array{0: array, 1: array} */
function validar_condominio(array $in, ?int $id = null): array
{
    $erros = [];
    $d = [];
    $d['nome'] = mb_substr(trim(strip_tags((string)($in['nome'] ?? ''))), 0, 120);
    if (mb_strlen($d['nome']) < 2) {
        $erros['nome'] = 'Informe o nome do condomínio.';
    } elseif (q('SELECT id FROM condominios WHERE LOWER(nome) = LOWER(?) AND id <> ?', [$d['nome'], $id ?? 0])->fetchColumn()) {
        $erros['nome'] = 'Já existe um condomínio com esse nome.';
    }
    $slug = slugify('condominio ' . $d['nome'] . ' ' . ($in['bairro'] ?? ''));
    $base = $slug;
    $n = 2;
    while (q('SELECT id FROM condominios WHERE slug = ? AND id <> ?', [$slug, $id ?? 0])->fetchColumn()) {
        $slug = $base . '-' . $n++;
    }
    $d['slug'] = $slug;
    $d['nome_publico'] = !empty($in['nome_publico']) ? 1 : 0;
    $d['descricao'] = limpar_texto((string)($in['descricao'] ?? '')) ?: null;
    if (isset($in['lazer_itens']) || isset($in['lazer_outros'])) {
        $d['lazer'] = juntar_itens(array_map('strval', (array)($in['lazer_itens'] ?? [])), (string)($in['lazer_outros'] ?? ''), itens_do_condominio());
    } else {
        $d['lazer'] = trim(strip_tags((string)($in['lazer'] ?? ''))) ?: null;
    }
    foreach (['endereco' => 150, 'numero' => 20, 'bairro' => 80, 'cidade' => 80] as $k => $max) {
        $d[$k] = mb_substr(trim(strip_tags((string)($in[$k] ?? ''))), 0, $max) ?: null;
    }
    $d['cidade'] = $d['cidade'] ?: 'Bertioga';
    $d['uf'] = 'SP';
    $cep = preg_replace('/\D/', '', (string)($in['cep'] ?? '')) ?? '';
    $d['cep'] = strlen($cep) === 8 ? substr($cep, 0, 5) . '-' . substr($cep, 5) : null;
    $d['latitude'] = parse_decimal($in['latitude'] ?? null);
    $d['longitude'] = parse_decimal($in['longitude'] ?? null);
    return [$d, $erros];
}

function salvar_condominio(array $d, ?int $id = null): int
{
    $cols = array_keys($d);
    if ($id) {
        q('UPDATE condominios SET ' . implode(', ', array_map(fn($c) => "$c = ?", $cols)) . ' WHERE id = ?', [...array_values($d), $id]);
        return $id;
    }
    q('INSERT INTO condominios (' . implode(',', $cols) . ') VALUES (' . implode(',', array_fill(0, count($cols), '?')) . ')', array_values($d));
    return (int)db()->lastInsertId();
}

function adicionar_foto_condominio(int $id, array $file): array
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) {
        throw new RuntimeException('Falha no envio da foto.');
    }
    $r = processar_imagem($id, $file['tmp_name'], 'condominios');
    $ordem = (int)q('SELECT COALESCE(MAX(ordem), -1) + 1 FROM condominio_fotos WHERE condominio_id = ?', [$id])->fetchColumn();
    q('INSERT INTO condominio_fotos (condominio_id, arquivo, largura, altura, ordem) VALUES (?,?,?,?,?)', [$id, $r['nome'], $r['largura'], $r['altura'], $ordem]);
    $novo = ['id' => (int)db()->lastInsertId(), 'condominio_id' => $id, 'arquivo' => $r['nome'], 'largura' => $r['largura'], 'altura' => $r['altura']];
    sincronizar_fotos_do_condominio($id); // entra no fim da galeria de cada imóvel do condomínio
    return $novo;
}

function apagar_arquivos_foto_condominio(array $f): void
{
    $base = rtrim((string)config('public_dir'), '/') . '/uploads/condominios/' . (int)$f['condominio_id'] . '/' . basename((string)$f['arquivo']);
    @unlink($base . '-g.jpg');
    @unlink($base . '-p.jpg');
}

function excluir_foto_condominio(int $fotoId, int $id): bool
{
    $f = q('SELECT * FROM condominio_fotos WHERE id = ? AND condominio_id = ?', [$fotoId, $id])->fetch();
    if (!$f) return false;
    apagar_arquivos_foto_condominio($f);
    q('DELETE FROM condominio_fotos WHERE id = ?', [$fotoId]);
    return true;
}

function reordenar_fotos_condominio(int $id, array $ids): void
{
    $st = db()->prepare('UPDATE condominio_fotos SET ordem = ? WHERE id = ? AND condominio_id = ?');
    foreach (array_values($ids) as $ordem => $fid) {
        $st->execute([$ordem, (int)$fid, $id]);
    }
}

function excluir_condominio(int $id): void
{
    foreach (fotos_condominio($id) as $f) {
        apagar_arquivos_foto_condominio($f);
    }
    @rmdir(rtrim((string)config('public_dir'), '/') . '/uploads/condominios/' . $id);
    q('DELETE FROM condominios WHERE id = ?', [$id]); // imóveis ficam, só perdem o vínculo
}

/** Liga (ou cria) o condomínio vindo da Code49 pelo id de empreendimento. */
function condominio_da_code49(?array $building): ?int
{
    if (!$building || empty($building['id'])) return null;
    $id = q('SELECT id FROM condominios WHERE code49_id = ?', [(int)$building['id']])->fetchColumn();
    if ($id) return (int)$id;
    $nome = trim((string)($building['description'] ?? '')) ?: ('Empreendimento ' . ($building['code'] ?? $building['id']));
    [$d] = validar_condominio(['nome' => $nome]);
    $d['code49_id'] = (int)$building['id'];
    return salvar_condominio($d);
}

function anexar_capas_condominios(array $lista): array
{
    foreach ($lista as &$c) {
        $c['capa'] = !empty($c['capa_id']) ? (q('SELECT * FROM condominio_fotos WHERE id = ?', [(int)$c['capa_id']])->fetch() ?: null) : null;
    }
    unset($c);
    return $lista;
}
