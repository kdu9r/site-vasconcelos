<?php
declare(strict_types=1);

function post_url(array $p): string
{
    return '/blog/' . $p['slug'];
}

function capa_post_url(array $p, string $tamanho = 'g'): ?string
{
    if (empty($p['capa'])) {
        return null;
    }
    return '/uploads/blog/' . (int)$p['id'] . '/' . basename($p['capa']) . ($tamanho === 'p' ? '-p.jpg' : '-g.jpg');
}

function data_extenso(?string $data): string
{
    if (!$data) return '';
    $meses = ['janeiro', 'fevereiro', 'março', 'abril', 'maio', 'junho', 'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro'];
    $t = strtotime($data);
    return date('j', $t) . ' de ' . $meses[(int)date('n', $t) - 1] . ' de ' . date('Y', $t);
}

/** Tempo de leitura em minutos (200 palavras por minuto). */
function tempo_leitura(string $texto): int
{
    return max(1, (int)round(str_word_count(strip_tags($texto)) / 200));
}

/**
 * Formatação simples e segura para os posts:
 *   linha em branco = novo parágrafo · "## " = subtítulo · "- " = item de lista
 *   **negrito** · [texto](https://link) ou [texto](/imovel/123)
 * Todo o texto é escapado antes; nenhum HTML digitado passa.
 */
function render_texto(string $texto): string
{
    $linhas = preg_split('/\r\n|\r|\n/', trim($texto)) ?: [];
    $html = '';
    $paragrafo = [];
    $lista = [];
    $inline = function (string $t): string {
        $t = e($t);
        $t = preg_replace('/\*\*(.+?)\*\*/u', '<strong>$1</strong>', $t) ?? $t;
        $t = preg_replace_callback('/\[([^\]]+)\]\(((?:https?:\/\/|\/(?!\/))[^\s)]+)\)/u', function ($m) {
            $url = html_entity_decode($m[2], ENT_QUOTES);
            $externo = str_starts_with($url, 'http') && !str_starts_with($url, (string)config('site_url'));
            return '<a href="' . e($url) . '"' . ($externo ? ' target="_blank" rel="noopener"' : '') . '>' . $m[1] . '</a>';
        }, $t) ?? $t;
        return $t;
    };
    $fechar = function () use (&$html, &$paragrafo, &$lista, $inline) {
        if ($paragrafo) {
            $html .= '<p>' . $inline(implode(' ', $paragrafo)) . "</p>\n";
            $paragrafo = [];
        }
        if ($lista) {
            $html .= "<ul>\n" . implode('', array_map(fn($i) => '<li>' . $inline($i) . "</li>\n", $lista)) . "</ul>\n";
            $lista = [];
        }
    };
    foreach ($linhas as $l) {
        $l = trim($l);
        if ($l === '') { $fechar(); continue; }
        if (str_starts_with($l, '## ')) { $fechar(); $html .= '<h2>' . $inline(substr($l, 3)) . "</h2>\n"; continue; }
        if (str_starts_with($l, '- ')) {
            if ($paragrafo) {
                $html .= '<p>' . $inline(implode(' ', $paragrafo)) . "</p>\n";
                $paragrafo = [];
            }
            $lista[] = substr($l, 2);
            continue;
        }
        if ($lista) $fechar();
        $paragrafo[] = $l;
    }
    $fechar();
    return $html;
}

function posts_publicados(int $limite = 12, int $offset = 0): array
{
    $limite = max(1, min(50, $limite));
    $offset = max(0, $offset);
    return q("SELECT * FROM posts WHERE status = 'publicado' AND publicado_em <= NOW()
              ORDER BY publicado_em DESC LIMIT $limite OFFSET $offset")->fetchAll();
}

function total_posts_publicados(): int
{
    return (int)q("SELECT COUNT(*) FROM posts WHERE status = 'publicado' AND publicado_em <= NOW()")->fetchColumn();
}

function post_por_slug(string $slug): ?array
{
    $p = q("SELECT p.*, u.nome AS autor_nome FROM posts p LEFT JOIN usuarios u ON u.id = p.autor_id
            WHERE p.slug = ? AND p.status = 'publicado' AND p.publicado_em <= NOW()", [$slug])->fetch();
    return $p ?: null;
}

function post_por_id(int $id): ?array
{
    $p = q('SELECT * FROM posts WHERE id = ?', [$id])->fetch();
    return $p ?: null;
}

/** @return array{0: array, 1: array} */
function validar_post(array $in, ?int $id = null): array
{
    $erros = [];
    $d = [];
    $d['titulo'] = trim(strip_tags((string)($in['titulo'] ?? '')));
    if (mb_strlen($d['titulo']) < 10 || mb_strlen($d['titulo']) > 150) {
        $erros['titulo'] = 'O título precisa ter entre 10 e 150 caracteres.';
    }
    $slug = slugify(trim((string)($in['slug'] ?? '')) ?: $d['titulo']);
    $base = $slug;
    $n = 2;
    while (q('SELECT id FROM posts WHERE slug = ? AND id <> ?', [$slug, $id ?? 0])->fetchColumn()) {
        $slug = $base . '-' . $n++;
    }
    $d['slug'] = $slug;
    $d['resumo'] = mb_substr(trim(strip_tags((string)($in['resumo'] ?? ''))), 0, 220) ?: null;
    $d['conteudo'] = trim(strip_tags((string)($in['conteudo'] ?? ''))) ?: null;
    $d['status'] = ($in['status'] ?? '') === 'publicado' ? 'publicado' : 'rascunho';
    if ($d['status'] === 'publicado' && mb_strlen((string)$d['conteudo']) < 300) {
        $erros['conteudo'] = 'Para publicar, escreva pelo menos 300 caracteres. Textos curtos não ajudam no Google.';
    }
    $data = trim((string)($in['publicado_em'] ?? ''));
    $d['publicado_em'] = $data !== '' && strtotime($data) ? date('Y-m-d H:i:s', strtotime($data)) : ($d['status'] === 'publicado' ? date('Y-m-d H:i:s') : null);
    $d['bairro'] = mb_substr(trim(strip_tags((string)($in['bairro'] ?? ''))), 0, 80) ?: null;
    return [$d, $erros];
}

function salvar_post(array $d, ?int $id = null): int
{
    $cols = array_keys($d);
    if ($id) {
        q('UPDATE posts SET ' . implode(', ', array_map(fn($c) => "$c = ?", $cols)) . ' WHERE id = ?', [...array_values($d), $id]);
        return $id;
    }
    q('INSERT INTO posts (' . implode(',', $cols) . ') VALUES (' . implode(',', array_fill(0, count($cols), '?')) . ')', array_values($d));
    return (int)db()->lastInsertId();
}

function salvar_capa_post(int $id, array $file): void
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return;
    }
    if ($file['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) {
        throw new RuntimeException('Falha no envio da imagem.');
    }
    $antigo = post_por_id($id)['capa'] ?? null;
    $r = processar_imagem($id, $file['tmp_name'], 'blog');
    q('UPDATE posts SET capa = ? WHERE id = ?', [$r['nome'], $id]);
    if ($antigo) {
        $base = rtrim((string)config('public_dir'), '/') . '/uploads/blog/' . $id . '/' . basename($antigo);
        @unlink($base . '-g.jpg');
        @unlink($base . '-p.jpg');
    }
}

function excluir_post(int $id): void
{
    $dir = rtrim((string)config('public_dir'), '/') . '/uploads/blog/' . $id;
    foreach (glob($dir . '/*.jpg') ?: [] as $f) {
        @unlink($f);
    }
    @rmdir($dir);
    q('DELETE FROM posts WHERE id = ?', [$id]);
}
