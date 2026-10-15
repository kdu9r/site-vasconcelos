<?php
declare(strict_types=1);

function rotear_publico(string $path): void
{
    // Endereços antigos da Code49 → novos (301 mantém a posição no Google)
    if (preg_match('~^/(\d+)/imoveis(/|$)~', $path, $m)) {
        $im = q('SELECT codigo, slug FROM imoveis WHERE code49_id = ?', [(int)$m[1]])->fetch();
        redirect($im ? url_imovel($im) : '/imoveis', 301);
    }
    $antigos = [
        '/imobiliaria/imoveis' => '/imoveis', '/contato.php' => '/#contato', '/quem-somos.php' => '/quem-somos',
        '/servicos.php' => '/quem-somos', '/temporada' => '/alugar', '/equipe.php' => '/#contato', '/financiamento.php' => '/', '/trabalhe.php' => '/',
        '/parceiros.php' => '/', '/dicionario.php' => '/', '/links-uteis.php' => '/', '/noticia.php' => '/blog',
        '/privacy.php' => '/privacidade', '/equipe/fred-vasconcelos' => '/imoveis',
    ];
    if (isset($antigos[$path])) {
        redirect($antigos[$path], 301);
    }

    switch (true) {
        case $path === '/':
            pagina_home();
            return;
        case in_array($path, ['/imoveis', '/comprar', '/alugar'], true):
            pagina_lista($path);
            return;
        case (bool)preg_match('~^/imovel/([A-Za-z0-9]+)(?:-([a-z0-9\-]*))?$~', $path, $m):
            pagina_imovel($m[1], $m[2] ?? '');
            return;
        case $path === '/busca':
            $cod = preg_replace('/[^A-Za-z0-9\-]/', '', (string)($_GET['codigo'] ?? '')) ?? '';
            $im = $cod !== '' ? imovel_por_codigo(ltrim($cod, '#')) : null;
            if ($im) redirect(url_imovel($im));
            flash('aviso', 'Não encontramos o código ' . $cod . '.');
            redirect('/imoveis');
        case $path === '/quem-somos':
            view('quem_somos', ['titulo_pagina' => 'Quem somos', 'descricao_pagina' => 'Conheça o Vasconcelos Corretor: corretores de imóveis em Bertioga, SP.']);
            return;
        case (bool)preg_match('~^/condominio/([a-z0-9\-]+)$~', $path, $m):
            pagina_condominio($m[1]);
            return;
        case $path === '/blog':
            pagina_blog();
            return;
        case (bool)preg_match('~^/blog/([a-z0-9\-]+)$~', $path, $m):
            pagina_post($m[1]);
            return;
        case $path === '/anuncie':
            view('anuncie', ['titulo_pagina' => 'Anuncie seu imóvel', 'descricao_pagina' => 'Quer vender ou alugar seu imóvel em Bertioga? Fale com a gente pelo WhatsApp.']);
            return;
        case $path === '/privacidade':
            view('privacidade', ['titulo_pagina' => 'Política de privacidade']);
            return;
        case $path === '/whatsapp':
            redirecionar_whatsapp();
            return;
        case $path === '/feed/canalpro.xml':
            if (config('modo_teste')) abort(404); // demonstração: nada vai para os portais
            header('Content-Type: application/xml; charset=utf-8');
            header('Cache-Control: public, max-age=600');
            echo gerar_feed_vrsync();
            return;
        case $path === '/sitemap.xml':
            sitemap();
            return;
        case $path === '/robots.txt':
            header('Content-Type: text/plain; charset=utf-8');
            echo config('modo_teste') ? "User-agent: *\nDisallow: /\n" : "User-agent: *\nDisallow: /painel\nDisallow: /whatsapp\nSitemap: " . url('/sitemap.xml') . "\n";
            return;
        default:
            abort(404);
    }
}

function filtros_da_url(string $path): array
{
    $transacao = match ($path) {
        '/comprar' => 'venda',
        '/alugar' => 'locacao',
        default => in_array($_GET['transacao'] ?? '', ['venda', 'locacao'], true) ? $_GET['transacao'] : '',
    };
    return [
        'transacao' => $transacao,
        'bairro' => mb_substr(trim((string)($_GET['bairro'] ?? '')), 0, 80),
        'tipo' => (string)($_GET['tipo'] ?? ''),
        'quartos' => parse_int($_GET['quartos'] ?? null),
        'preco_min' => parse_decimal($_GET['preco_min'] ?? null),
        'preco_max' => parse_decimal($_GET['preco_max'] ?? null),
        'ordem' => in_array($_GET['ordem'] ?? '', ['recentes', 'menor_preco', 'maior_preco'], true) ? $_GET['ordem'] : 'recentes',
    ];
}

function pagina_home(): void
{
    $destaques = buscar_imoveis(['transacao' => 'venda'], 1, 6);
    $locacao = buscar_imoveis(['transacao' => 'locacao'], 1, 3);
    view('home', [
        'titulo_pagina' => 'Imóveis em Bertioga · SP',
        'descricao_pagina' => 'Casas, apartamentos e terrenos para comprar ou alugar em Bertioga, com atendimento direto do corretor pelo WhatsApp.',
        'destaques' => $destaques['itens'],
        'locacao' => $locacao['itens'],
        'bairros' => bairros_com_contagem(),
        'tipos' => tipos_em_uso(),
        'posts' => posts_publicados(3),
        'pagina_home' => true,
    ]);
}

function pagina_lista(string $path): void
{
    $f = filtros_da_url($path);
    $pagina = max(1, (int)($_GET['pagina'] ?? 1));
    $res = buscar_imoveis($f, $pagina, 12);
    $titulo = match ($f['transacao']) {
        'venda' => 'Imóveis à venda',
        'locacao' => 'Imóveis para alugar',
        default => 'Todos os imóveis',
    };
    if ($f['bairro']) $titulo .= ' em ' . $f['bairro'];
    view('lista', [
        'titulo_pagina' => $titulo . ' · Bertioga',
        'descricao_pagina' => $titulo . ' em Bertioga, SP. Veja fotos, valores e fale com o corretor pelo WhatsApp.',
        'titulo' => $titulo,
        'filtros' => $f,
        'resultado' => $res,
        'bairros' => bairros_com_contagem(),
        'tipos' => tipos_em_uso(),
        'path' => $path,
        'noindex' => $pagina > 1 || $f['tipo'] || $f['quartos'] || $f['preco_min'] || $f['preco_max'] || $f['ordem'] !== 'recentes',
        'canonical' => $path . ($f['bairro'] ? '?' . http_build_query(['bairro' => $f['bairro']]) : ''),
    ]);
}

function pagina_imovel(string $codigo, string $slug): void
{
    $im = imovel_por_codigo($codigo, false);
    if (!$im) {
        abort(404);
    }
    if ($slug !== $im['slug']) {
        redirect(url_imovel($im), 301);
    }
    $indisponivel = $im['status'] !== 'ativo';
    $fotos = fotos_completas($im);
    $resumo = mb_strimwidth(preg_replace('/\s+/', ' ', (string)$im['descricao']) ?? '', 0, 155, '…');
    view('imovel', [
        'titulo_pagina' => $im['titulo'] . ' · Cód. ' . $im['codigo'],
        'descricao_pagina' => $resumo ?: ($im['titulo'] . ' em ' . $im['bairro'] . ', Bertioga.'),
        'og_imagem' => $fotos ? foto_url($fotos[0], 'g') : null,
        'canonical' => url_imovel($im),
        'imovel' => $im,
        'fotos' => $fotos,
        'condominio' => condominio_por_id(isset($im['condominio_id']) ? (int)$im['condominio_id'] : null),
        'parecidos' => imoveis_parecidos($im),
        'indisponivel' => $indisponivel,
        'noindex' => $indisponivel,
        'whats_imovel' => $im,
    ]);
}

function redirecionar_whatsapp(): void
{
    $codigo = preg_replace('/[^A-Za-z0-9\-]/', '', is_string($_GET['c'] ?? null) ? $_GET['c'] : '') ?? '';
    $origem = preg_replace('/[^a-z_]/', '', is_string($_GET['o'] ?? null) ? $_GET['o'] : 'site') ?: 'site';
    $im = $codigo !== '' ? imovel_por_codigo($codigo, false) : null;
    $ua = strtolower((string)($_SERVER['HTTP_USER_AGENT'] ?? ''));
    $robo = $ua === '' || preg_match('/bot|crawl|spider|slurp|preview|facebookexternalhit|whatsapp/', $ua);
    if (!$robo) try {
        q('INSERT INTO cliques_whatsapp (imovel_id, origem) VALUES (?, ?)', [$im['id'] ?? null, mb_substr($origem, 0, 20)]);
    } catch (Throwable $e) {
        error_log('clique whatsapp: ' . $e->getMessage());
    }
    header('X-Robots-Tag: noindex');
    redirect(whatsapp_destino(is_string($_GET['n'] ?? null) ? $_GET['n'] : null, whatsapp_mensagem($im ?: null)));
}

function sitemap(): void
{
    header('Content-Type: application/xml; charset=utf-8');
    $x = new XMLWriter();
    $x->openMemory();
    $x->startDocument('1.0', 'UTF-8');
    $x->startElement('urlset');
    $x->writeAttribute('xmlns', 'http://www.sitemaps.org/schemas/sitemap/0.9');
    foreach (['/', '/comprar', '/alugar', '/imoveis', '/quem-somos', '/anuncie'] as $p) {
        $x->startElement('url'); $x->writeElement('loc', url($p)); $x->endElement();
    }
    $x->startElement('url'); $x->writeElement('loc', url('/blog')); $x->endElement();
    foreach (q("SELECT slug FROM condominios WHERE nome_publico = 1")->fetchAll() as $c) {
        $x->startElement('url'); $x->writeElement('loc', url(condominio_url($c))); $x->endElement();
    }
    foreach (q("SELECT slug, atualizado_em FROM posts WHERE status = 'publicado' AND publicado_em <= NOW()")->fetchAll() as $p) {
        $x->startElement('url');
        $x->writeElement('loc', url(post_url($p)));
        $x->writeElement('lastmod', date('Y-m-d', strtotime((string)$p['atualizado_em'])));
        $x->endElement();
    }
    foreach (q("SELECT codigo, slug, atualizado_em FROM imoveis WHERE status = 'ativo'")->fetchAll() as $im) {
        $x->startElement('url');
        $x->writeElement('loc', url(url_imovel($im)));
        $x->writeElement('lastmod', date('Y-m-d', strtotime((string)$im['atualizado_em'])));
        $x->endElement();
    }
    $x->endElement();
    echo $x->outputMemory();
}

function pagina_blog(): void
{
    $porPagina = 9;
    $total = total_posts_publicados();
    $paginas = max(1, (int)ceil($total / $porPagina));
    $pagina = max(1, min($paginas, (int)($_GET['pagina'] ?? 1)));
    view('blog', [
        'titulo_pagina' => 'Blog: morar e investir em Bertioga',
        'descricao_pagina' => 'Dicas sobre comprar, alugar e morar na praia em Bertioga, com informações sobre bairros, valores e o mercado de imóveis do litoral.',
        'posts' => posts_publicados($porPagina, ($pagina - 1) * $porPagina),
        'pagina' => $pagina,
        'paginas' => $paginas,
        'noindex' => $pagina > 1,
    ]);
}

function pagina_post(string $slug): void
{
    $p = post_por_slug($slug);
    if (!$p) {
        abort(404);
    }
    $imoveis = [];
    if ($p['bairro']) {
        $imoveis = buscar_imoveis(['bairro' => $p['bairro']], 1, 3)['itens'];
    }
    if (!$imoveis) {
        $imoveis = buscar_imoveis([], 1, 3)['itens'];
    }
    $capa = capa_post_url($p);
    view('post', [
        'titulo_pagina' => $p['titulo'],
        'descricao_pagina' => $p['resumo'] ?: mb_strimwidth(preg_replace('/\s+/', ' ', strip_tags((string)$p['conteudo'])) ?? '', 0, 155, '…'),
        'og_imagem' => $capa,
        'canonical' => post_url($p),
        'post' => $p,
        'imoveis' => $imoveis,
        'outros' => array_values(array_filter(posts_publicados(4), fn($o) => (int)$o['id'] !== (int)$p['id'])),
    ]);
}

function pagina_condominio(string $slug): void
{
    $c = condominio_publico_por_slug($slug);
    if (!$c) {
        abort(404);
    }
    $itens = q("SELECT " . IMOVEL_COLUNAS_LISTA . " FROM imoveis i WHERE i.status = 'ativo' AND i.condominio_id = ? ORDER BY i.destaque_site DESC, i.criado_em DESC", [$c['id']])->fetchAll();
    view('condominio', [
        'titulo_pagina' => 'Condomínio ' . $c['nome'] . ($c['bairro'] ? ' · ' . $c['bairro'] : '') . ' · Bertioga',
        'descricao_pagina' => 'Imóveis à venda e para alugar no Condomínio ' . $c['nome'] . ', em Bertioga. Fotos das áreas comuns e itens de lazer.',
        'og_imagem' => ($f = fotos_condominio((int)$c['id'])) ? foto_url($f[0], 'g') : null,
        'condominio' => $c,
        'fotos' => fotos_condominio((int)$c['id']),
        'imoveis' => anexar_capas($itens),
    ]);
}
