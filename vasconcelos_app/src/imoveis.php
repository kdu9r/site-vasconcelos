<?php
declare(strict_types=1);

/** Tipos de imóvel: rótulo no site + correspondência no padrão VRSync do CanalPro. */
function tipos_imovel(): array
{
    return [
        'apartamento'     => ['rotulo' => 'Apartamento',          'vrsync' => 'Residential / Apartment',    'uso' => 'Residential', 'terreno' => false],
        'cobertura'       => ['rotulo' => 'Cobertura',            'vrsync' => 'Residential / Penthouse',    'uso' => 'Residential', 'terreno' => false],
        'casa'            => ['rotulo' => 'Casa',                 'vrsync' => 'Residential / Home',         'uso' => 'Residential', 'terreno' => false],
        'sobrado'         => ['rotulo' => 'Sobrado',              'vrsync' => 'Residential / Sobrado',      'uso' => 'Residential', 'terreno' => false],
        'villagio'        => ['rotulo' => 'Casa em villagio',     'vrsync' => 'Residential / Village House','uso' => 'Residential', 'terreno' => false],
        'casa_condominio' => ['rotulo' => 'Casa em condomínio',   'vrsync' => 'Residential / Condo',        'uso' => 'Residential', 'terreno' => false],
        'kitnet'          => ['rotulo' => 'Kitnet',               'vrsync' => 'Residential / Kitnet',       'uso' => 'Residential', 'terreno' => false],
        'flat'            => ['rotulo' => 'Flat',                 'vrsync' => 'Residential / Flat',         'uso' => 'Residential', 'terreno' => false],
        'terreno'         => ['rotulo' => 'Terreno',              'vrsync' => 'Residential / Land Lot',     'uso' => 'Residential', 'terreno' => true],
        'chacara'         => ['rotulo' => 'Chácara / Sítio',      'vrsync' => 'Residential / Agricultural', 'uso' => 'Residential', 'terreno' => true],
        'sala'            => ['rotulo' => 'Sala comercial',       'vrsync' => 'Commercial / Office',        'uso' => 'Commercial',  'terreno' => false],
        'loja'            => ['rotulo' => 'Loja / Ponto',         'vrsync' => 'Commercial / Business',      'uso' => 'Commercial',  'terreno' => false],
        'galpao'          => ['rotulo' => 'Galpão',               'vrsync' => 'Commercial / Industrial',    'uso' => 'Commercial',  'terreno' => true],
        'terreno_comercial' => ['rotulo' => 'Terreno comercial',  'vrsync' => 'Commercial / Land Lot',      'uso' => 'Commercial',  'terreno' => true],
    ];
}

function tipo_rotulo(string $tipo): string
{
    return tipos_imovel()[$tipo]['rotulo'] ?? ucfirst($tipo);
}

function eh_terreno(array $im): bool
{
    return (bool)(tipos_imovel()[$im['tipo']]['terreno'] ?? false);
}

function url_imovel(array $im): string
{
    return '/imovel/' . rawurlencode((string)$im['codigo']) . '-' . $im['slug'];
}

function foto_url(?array $foto, string $tamanho = 'g'): string
{
    if (!$foto) {
        return asset('img/sem-foto.svg');
    }
    $sufixo = $tamanho === 'p' ? '-p.jpg' : '-g.jpg';
    if (!empty($foto['cond_arquivo'])) {
        // foto do condomínio posicionada na galeria do imóvel
        return '/uploads/condominios/' . (int)$foto['cond_id'] . '/' . basename($foto['cond_arquivo']) . $sufixo;
    }
    if (!empty($foto['arquivo'])) {
        if (!empty($foto['condominio_id']) && empty($foto['imovel_id'])) {
            return '/uploads/condominios/' . (int)$foto['condominio_id'] . '/' . basename($foto['arquivo']) . $sufixo;
        }
        return '/uploads/imoveis/' . (int)$foto['imovel_id'] . '/' . basename($foto['arquivo']) . $sufixo;
    }
    return (string)($foto['url_externa'] ?? asset('img/sem-foto.svg'));
}

/** Lista de transações disponíveis para o imóvel. */
function transacoes(array $im): array
{
    $t = [];
    if ($im['preco_venda'] !== null) $t[] = 'venda';
    if ($im['preco_locacao'] !== null) $t[] = 'locacao';
    if ($im['preco_temporada'] !== null) $t[] = 'temporada';
    return $t;
}

function transacao_rotulo(array $im): string
{
    $map = ['venda' => 'Venda', 'locacao' => 'Locação', 'temporada' => 'Temporada'];
    return implode(' e ', array_map(fn($t) => $map[$t], transacoes($im))) ?: 'Consulte';
}

/**
 * Preço de destaque do card. $contexto 'locacao' força o valor de locação.
 * @return array{valor:string, sufixo:string, nota:string}
 */
function preco_principal(array $im, ?string $contexto = null): array
{
    if (($contexto === 'locacao' || $im['preco_venda'] === null) && $im['preco_locacao'] !== null) {
        return ['valor' => money($im['preco_locacao']), 'sufixo' => '/mês', 'nota' => $im['locacao_pacote'] ? 'Pacote com condomínio e IPTU' : ''];
    }
    if (($contexto === 'temporada' || ($im['preco_venda'] === null && $im['preco_locacao'] === null)) && $im['preco_temporada'] !== null) {
        return ['valor' => money($im['preco_temporada']), 'sufixo' => '/diária', 'nota' => ''];
    }
    return ['valor' => money($im['preco_venda'], true), 'sufixo' => '', 'nota' => ''];
}

function specs_curtas(array $im): string
{
    $p = [];
    if ($im['area_util'] !== null) $p[] = num($im['area_util']) . ' m²';
    elseif ($im['area_total'] !== null) $p[] = num($im['area_total']) . ' m²';
    if ($im['quartos']) {
        $q = $im['quartos'] . ' dorm.';
        if ($im['suites']) $q .= ' (' . $im['suites'] . ($im['suites'] > 1 ? ' suítes)' : ' suíte)');
        $p[] = $q;
    }
    if ($im['vagas']) $p[] = $im['vagas'] . ($im['vagas'] > 1 ? ' vagas' : ' vaga');
    return implode(' · ', $p);
}

function endereco_publico(array $im): string
{
    $partes = [];
    if ($im['exibir_endereco'] !== 'bairro' && $im['endereco']) {
        $rua = $im['endereco'];
        if ($im['exibir_endereco'] === 'completo' && $im['numero']) $rua .= ', ' . $im['numero'];
        $partes[] = $rua;
    }
    $partes[] = $im['bairro'];
    $partes[] = $im['cidade'] . ' · ' . $im['uf'];
    return implode(', ', $partes);
}

function caracteristicas_lista(array $im): array
{
    $linhas = preg_split('/\r\n|\r|\n/', (string)($im['caracteristicas'] ?? '')) ?: [];
    return array_values(array_filter(array_map('trim', $linhas), fn($l) => $l !== ''));
}

/* ---------- WhatsApp ---------- */

function whatsapp_mensagem(?array $im): string
{
    if (!$im) {
        return 'Olá! Vim pelo site e gostaria de mais informações.';
    }
    return sprintf('Olá! Vi o imóvel Cód. %s (%s, %s) no site e quero mais informações.',
        $im['codigo'], mb_strimwidth($im['titulo'], 0, 60, '…'), $im['bairro']);
}

/** Link interno que registra o clique e redireciona para o WhatsApp. */
function whatsapp_link(?array $im, string $origem, ?string $numero = null): string
{
    return '/whatsapp?' . http_build_query(array_filter([
        'c' => $im['codigo'] ?? null,
        'o' => $origem,
        'n' => $numero,
    ]));
}

function whatsapp_destino(?string $numero, string $mensagem): string
{
    $permitidos = array_column((array)config('contato.corretores', []), 'whatsapp');
    $num = in_array($numero, $permitidos, true) ? $numero : whatsapp_padrao();
    return 'https://wa.me/' . $num . '?text=' . rawurlencode($mensagem);
}

/**
 * Para quem vão os botões de WhatsApp que não escolhem um corretor
 * (Quero saber mais, Agendar visita, botão flutuante etc.).
 * Painel → Configurações → Compartilhar. Padrão: aleatório entre os corretores.
 */
function whatsapp_padrao(): string
{
    $permitidos = array_values(array_filter(array_column((array)config('contato.corretores', []), 'whatsapp')));
    $escolha = (string)(config_site('whatsapp_destino') ?? 'aleatorio');
    if (in_array($escolha, $permitidos, true)) {
        return $escolha;
    }
    if ($permitidos) {
        return $permitidos[random_int(0, count($permitidos) - 1)];
    }
    return (string)config('contato.whatsapp_principal');
}

/** Texto do botão de WhatsApp do topo: o número da pessoa fixa, ou "WhatsApp" quando é aleatório. */
function whatsapp_rotulo_menu(): string
{
    $permitidos = array_column((array)config('contato.corretores', []), 'whatsapp');
    $escolha = (string)(config_site('whatsapp_destino') ?? 'aleatorio');
    return in_array($escolha, $permitidos, true) ? formata_telefone($escolha) : 'Fale no WhatsApp';
}

function formata_telefone(string $numero): string
{
    $d = preg_replace('/\D/', '', $numero) ?? '';
    if (str_starts_with($d, '55')) $d = substr($d, 2);
    if (strlen($d) === 11) return sprintf('(%s) %s-%s', substr($d, 0, 2), substr($d, 2, 5), substr($d, 7));
    if (strlen($d) === 10) return sprintf('(%s) %s-%s', substr($d, 0, 2), substr($d, 2, 4), substr($d, 6));
    return $numero;
}

/* ---------- Consultas ---------- */

const IMOVEL_COLUNAS_LISTA = 'i.id, i.codigo, i.slug, i.titulo, i.tipo, i.preco_venda, i.preco_locacao, i.preco_temporada, i.locacao_pacote,
    i.quartos, i.suites, i.banheiros, i.vagas, i.area_util, i.area_total, i.bairro, i.cidade, i.uf, i.status, i.destaque_site, i.atualizado_em, i.condominio_id,
    (SELECT f.id FROM fotos f WHERE f.imovel_id = i.id AND f.oculta = 0 ORDER BY f.ordem, f.id LIMIT 1) AS capa_id';

function anexar_capas(array $lista): array
{
    $ids = array_filter(array_column($lista, 'capa_id'));
    $capas = [];
    if ($ids) {
        $in = implode(',', array_fill(0, count($ids), '?'));
        foreach (q(FOTOS_SELECT . " WHERE f.id IN ($in)", array_values($ids))->fetchAll() as $f) {
            $capas[$f['id']] = $f;
        }
    }
    // Sem foto própria: usa a primeira foto do condomínio
    $semCapa = array_unique(array_filter(array_map(fn($im) => empty($im['capa_id']) ? ($im['condominio_id'] ?? null) : null, $lista)));
    $capasCond = [];
    foreach ($semCapa as $cid) {
        $f = q('SELECT * FROM condominio_fotos WHERE condominio_id = ? ORDER BY ordem, id LIMIT 1', [(int)$cid])->fetch();
        if ($f) $capasCond[(int)$cid] = $f;
    }
    foreach ($lista as &$im) {
        $im['capa'] = $capas[$im['capa_id']] ?? ($capasCond[(int)($im['condominio_id'] ?? 0)] ?? null);
    }
    unset($im);
    return $lista;
}

/**
 * Busca pública. Filtros: transacao (venda|locacao|temporada), bairro, tipo, quartos, preco_min, preco_max, ordem.
 * @return array{itens: array, total: int, paginas: int}
 */
function buscar_imoveis(array $f, int $pagina = 1, int $porPagina = 12, bool $soAtivos = true): array
{
    $where = [];
    $p = [];
    if ($soAtivos) {
        $where[] = "i.status = 'ativo'";
    }
    $colPreco = match ($f['transacao'] ?? '') {
        'locacao' => 'i.preco_locacao',
        'temporada' => 'i.preco_temporada',
        'venda' => 'i.preco_venda',
        default => null,
    };
    if ($colPreco) {
        $where[] = "$colPreco IS NOT NULL";
    }
    if (!empty($f['bairro'])) {
        $where[] = 'i.bairro = ?';
        $p[] = $f['bairro'];
    }
    if (!empty($f['tipo']) && isset(tipos_imovel()[$f['tipo']])) {
        $where[] = 'i.tipo = ?';
        $p[] = $f['tipo'];
    }
    if (!empty($f['quartos'])) {
        $where[] = 'i.quartos >= ?';
        $p[] = (int)$f['quartos'];
    }
    $col = $colPreco ?? 'COALESCE(i.preco_venda, i.preco_locacao, i.preco_temporada)';
    if (!empty($f['preco_min'])) {
        $where[] = "$col >= ?";
        $p[] = (float)$f['preco_min'];
    }
    if (!empty($f['preco_max'])) {
        $where[] = "$col <= ?";
        $p[] = (float)$f['preco_max'];
    }
    if (!empty($f['destaque'])) {
        $where[] = 'i.destaque_site = 1';
    }
    $orderBy = match ($f['ordem'] ?? '') {
        'menor_preco' => "$col IS NULL, $col ASC",
        'maior_preco' => "$col DESC",
        default => 'i.destaque_site DESC, i.criado_em DESC, i.id DESC',
    };
    $sqlWhere = $where ? 'WHERE ' . implode(' AND ', $where) : '';
    $total = (int)q("SELECT COUNT(*) FROM imoveis i $sqlWhere", $p)->fetchColumn();
    $porPagina = max(1, min(48, $porPagina));
    $paginas = max(1, (int)ceil($total / $porPagina));
    $pagina = max(1, min($pagina, $paginas));
    $offset = ($pagina - 1) * $porPagina;
    $itens = q("SELECT " . IMOVEL_COLUNAS_LISTA . " FROM imoveis i $sqlWhere ORDER BY $orderBy LIMIT $porPagina OFFSET $offset", $p)->fetchAll();
    return ['itens' => anexar_capas($itens), 'total' => $total, 'paginas' => $paginas, 'pagina' => $pagina];
}

function imovel_por_codigo(string $codigo, bool $soAtivo = true): ?array
{
    $sql = 'SELECT * FROM imoveis WHERE codigo = ?' . ($soAtivo ? " AND status = 'ativo'" : '');
    $im = q($sql, [$codigo])->fetch();
    return $im ?: null;
}

function imovel_por_id(int $id): ?array
{
    $im = q('SELECT * FROM imoveis WHERE id = ?', [$id])->fetch();
    return $im ?: null;
}

const FOTOS_SELECT = 'SELECT f.*, cf.arquivo AS cond_arquivo, cf.condominio_id AS cond_id, cf.legenda AS cond_legenda
    FROM fotos f LEFT JOIN condominio_fotos cf ON cf.id = f.condominio_foto_id';

/** Todas as fotos do imóvel na ordem escolhida, incluindo as do condomínio. $visiveis = só as que aparecem no anúncio. */
function fotos_do_imovel(int $imovelId, bool $visiveis = false): array
{
    return q(FOTOS_SELECT . ' WHERE f.imovel_id = ?' . ($visiveis ? ' AND f.oculta = 0' : '') . ' ORDER BY f.ordem, f.id', [$imovelId])->fetchAll();
}

/**
 * Coloca as fotos do condomínio na galeria do imóvel (no fim, como ponto de partida).
 * Depois o corretor muda a ordem e esconde as que quiser; isso fica salvo só neste imóvel.
 * Ao trocar de condomínio, as fotos do anterior saem.
 */
function sincronizar_fotos_condominio(int $imovelId): void
{
    $condId = (int)q('SELECT condominio_id FROM imoveis WHERE id = ?', [$imovelId])->fetchColumn();
    q('DELETE f FROM fotos f JOIN condominio_fotos cf ON cf.id = f.condominio_foto_id
       WHERE f.imovel_id = ? AND cf.condominio_id <> ?', [$imovelId, $condId]);
    if (!$condId) {
        return;
    }
    $faltando = q('SELECT cf.* FROM condominio_fotos cf
        WHERE cf.condominio_id = ? AND NOT EXISTS (SELECT 1 FROM fotos f WHERE f.imovel_id = ? AND f.condominio_foto_id = cf.id)
        ORDER BY cf.ordem, cf.id', [$condId, $imovelId])->fetchAll();
    foreach ($faltando as $cf) {
        q('INSERT INTO fotos (imovel_id, condominio_foto_id, largura, altura, ordem) VALUES (?,?,?,?,?)',
            [$imovelId, $cf['id'], $cf['largura'], $cf['altura'], proxima_ordem_foto($imovelId)]);
    }
}

function sincronizar_fotos_do_condominio(int $condId): void
{
    foreach (q('SELECT id FROM imoveis WHERE condominio_id = ?', [$condId])->fetchAll(PDO::FETCH_COLUMN) as $id) {
        sincronizar_fotos_condominio((int)$id);
    }
}

function imoveis_parecidos(array $im, int $limite = 3): array
{
    $col = $im['preco_venda'] !== null ? 'preco_venda' : 'preco_locacao';
    $valor = (float)($im[$col] ?? 0);
    $itens = q("SELECT " . IMOVEL_COLUNAS_LISTA . " FROM imoveis i
        WHERE i.status = 'ativo' AND i.id <> ? AND i.$col IS NOT NULL
        ORDER BY (i.bairro = ?) DESC, (i.tipo = ?) DESC, ABS(i.$col - ?) ASC LIMIT $limite",
        [$im['id'], $im['bairro'], $im['tipo'], $valor])->fetchAll();
    return anexar_capas($itens);
}

function bairros_com_contagem(): array
{
    return q("SELECT bairro, COUNT(*) AS total FROM imoveis WHERE status = 'ativo' GROUP BY bairro ORDER BY total DESC, bairro")->fetchAll();
}

function tipos_em_uso(): array
{
    $usados = q("SELECT DISTINCT tipo FROM imoveis WHERE status = 'ativo'")->fetchAll(PDO::FETCH_COLUMN);
    return array_intersect_key(tipos_imovel(), array_flip($usados));
}

function proximo_codigo(): string
{
    $max = (int)q("SELECT COALESCE(MAX(CAST(codigo AS UNSIGNED)), 0) FROM imoveis")->fetchColumn();
    return (string)($max + 1);
}

/* ---------- Gravação (painel) ---------- */

/**
 * Valida e normaliza os dados do formulário do painel.
 * @return array{0: array, 1: array} [dados, erros]
 */
function validar_imovel(array $in, ?int $id = null): array
{
    $erros = [];
    $d = [];
    $d['codigo'] = trim((string)($in['codigo'] ?? ''));
    if ($d['codigo'] === '' || !preg_match('/^[A-Za-z0-9]{1,20}$/', $d['codigo'])) {
        $erros['codigo'] = 'Use só letras e números (até 20), sem espaços.';
    } else {
        $dup = q('SELECT id FROM imoveis WHERE codigo = ? AND id <> ?', [$d['codigo'], $id ?? 0])->fetchColumn();
        if ($dup) $erros['codigo'] = 'Já existe um imóvel com esse código.';
    }
    $d['titulo'] = trim(strip_tags((string)($in['titulo'] ?? '')));
    if (mb_strlen($d['titulo']) < 10 || mb_strlen($d['titulo']) > 100) {
        $erros['titulo'] = 'O título precisa ter entre 10 e 100 caracteres (exigência do CanalPro).';
    }
    $d['descricao'] = trim(strip_tags((string)($in['descricao'] ?? '')));
    if (mb_strlen($d['descricao']) > 3000) {
        $erros['descricao'] = 'Máximo de 3.000 caracteres.';
    }
    $d['tipo'] = (string)($in['tipo'] ?? '');
    if (!isset(tipos_imovel()[$d['tipo']])) {
        $erros['tipo'] = 'Escolha o tipo.';
    }
    $d['finalidade'] = tipos_imovel()[$d['tipo']]['uso'] ?? 'Residential';
    $d['finalidade'] = $d['finalidade'] === 'Commercial' ? 'comercial' : 'residencial';

    $negocio = (array)($in['negocio'] ?? []);
    $d['preco_venda'] = in_array('venda', $negocio, true) ? parse_decimal($in['preco_venda'] ?? null) : null;
    $d['preco_locacao'] = in_array('locacao', $negocio, true) ? parse_decimal($in['preco_locacao'] ?? null) : null;
    $d['preco_temporada'] = null; // ele não trabalha com temporada
    if (!$negocio) {
        $erros['negocio'] = 'Marque venda ou locação.';
    }
    foreach (['venda', 'locacao'] as $n) {
        if (in_array($n, $negocio, true) && $d['preco_' . $n] === null) {
            $erros['preco_' . $n] = 'Informe o valor.';
        }
    }
    $d['locacao_pacote'] = !empty($in['locacao_pacote']) ? 1 : 0;
    $d['condominio'] = parse_decimal($in['condominio'] ?? null);
    $d['iptu'] = parse_decimal($in['iptu'] ?? null);
    $d['iptu_periodo'] = ($in['iptu_periodo'] ?? '') === 'anual' ? 'anual' : 'mensal';
    $d['video_url'] = null;
    $video = trim((string)($in['video_url'] ?? ''));
    if ($video !== '') {
        if (youtube_id($video)) {
            $d['video_url'] = mb_substr($video, 0, 255);
        } else {
            $erros['video_url'] = 'Cole um link do YouTube (vídeo ou Shorts).';
        }
    }
    foreach (['quartos', 'suites', 'banheiros', 'vagas'] as $k) {
        // botões 0–5; "6+" abre um campo numérico
        $v = parse_int(($in[$k] ?? null) === 'mais' ? ($in[$k . '_mais'] ?? null) : ($in[$k] ?? null));
        $d[$k] = $v !== null ? min($v, 99) : null;
    }
    $d['area_util'] = parse_decimal($in['area_util'] ?? null);
    $d['area_total'] = parse_decimal($in['area_total'] ?? null);
    if (($in['bairro'] ?? '') === '__outro') {
        $in['bairro'] = $in['bairro_outro'] ?? '';
    }
    foreach (['endereco' => 150, 'numero' => 20, 'complemento' => 80, 'bairro' => 80, 'cidade' => 80] as $k => $max) {
        $d[$k] = mb_substr(trim(strip_tags((string)($in[$k] ?? ''))), 0, $max) ?: null;
    }
    if (!$d['bairro']) $erros['bairro'] = 'Informe o bairro.';
    $d['cidade'] = $d['cidade'] ?: 'Bertioga';
    $d['uf'] = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', (string)($in['uf'] ?? 'SP')) ?: 'SP', 0, 2));
    $cep = preg_replace('/\D/', '', (string)($in['cep'] ?? '')) ?? '';
    $d['cep'] = strlen($cep) === 8 ? substr($cep, 0, 5) . '-' . substr($cep, 5) : null;
    $d['latitude'] = parse_decimal($in['latitude'] ?? null);
    $d['longitude'] = parse_decimal($in['longitude'] ?? null);
    $d['exibir_endereco'] = in_array($in['exibir_endereco'] ?? '', ['bairro', 'rua', 'completo'], true) ? $in['exibir_endereco'] : 'bairro';
    if (isset($in['itens']) || isset($in['caracteristicas_outros'])) {
        $d['caracteristicas'] = juntar_itens(array_map('strval', (array)($in['itens'] ?? [])), (string)($in['caracteristicas_outros'] ?? ''), itens_do_imovel());
    } else {
        $d['caracteristicas'] = trim(strip_tags((string)($in['caracteristicas'] ?? ''))) ?: null;
    }
    $d['destaque_site'] = !empty($in['destaque_site']) ? 1 : 0;
    $d['destaque_portal'] = in_array($in['destaque_portal'] ?? '', ['padrao', 'destaque', 'super_destaque'], true) ? $in['destaque_portal'] : 'padrao';
    $d['publicar_portais'] = !empty($in['publicar_portais']) ? 1 : 0;
    $d['status'] = in_array($in['status'] ?? '', ['ativo', 'pausado', 'vendido', 'alugado'], true) ? $in['status'] : 'ativo';
    $cond = parse_int($in['condominio_id'] ?? null);
    $d['condominio_id'] = $cond && condominio_por_id($cond) ? $cond : null;
    $corretor = parse_int($in['corretor_id'] ?? null);
    $d['corretor_id'] = $corretor && q('SELECT id FROM usuarios WHERE id = ?', [$corretor])->fetchColumn() ? $corretor : null;
    $d['slug'] = slugify($d['titulo'] ?: ($d['tipo'] . ' ' . ($d['bairro'] ?? '')));
    return [$d, $erros];
}

function salvar_imovel(array $d, ?int $id = null): int
{
    $cols = array_keys($d);
    if ($id) {
        $set = implode(', ', array_map(fn($c) => "$c = ?", $cols));
        q("UPDATE imoveis SET $set WHERE id = ?", [...array_values($d), $id]);
        return $id;
    }
    $ph = implode(',', array_fill(0, count($cols), '?'));
    q('INSERT INTO imoveis (' . implode(',', $cols) . ") VALUES ($ph)", array_values($d));
    return (int)db()->lastInsertId();
}

/** Avisos para o CanalPro (não impedem salvar). */
function pendencias_portal(array $im, int $qtdFotos): array
{
    $p = [];
    if ($qtdFotos < 5) $p[] = 'O CanalPro exige pelo menos 5 fotos (tem ' . $qtdFotos . ').';
    if (mb_strlen((string)$im['descricao']) < 50) $p[] = 'A descrição precisa ter pelo menos 50 caracteres.';
    if (empty($im['cep'])) $p[] = 'Informe o CEP.';
    if (!eh_terreno($im) && empty($im['area_util'])) $p[] = 'Informe a área útil.';
    if (eh_terreno($im) && empty($im['area_total'])) $p[] = 'Informe a área do terreno.';
    if (!eh_terreno($im) && ($im['banheiros'] === null || $im['quartos'] === null)) $p[] = 'Informe quartos e banheiros.';
    return $p;
}

/** Extrai o ID de um link do YouTube (normal, youtu.be ou Shorts). */
function youtube_id(?string $url): ?string
{
    if (!$url) return null;
    if (preg_match('~(?:youtube\.com/(?:watch\?(?:.*&)?v=|shorts/|embed/|live/)|youtu\.be/)([A-Za-z0-9_-]{11})~', $url, $m)) {
        return $m[1];
    }
    return null;
}

function youtube_vertical(?string $url): bool
{
    return (bool)($url && str_contains($url, '/shorts/'));
}

function foto_em_pe(array $foto): bool
{
    return !empty($foto['largura']) && !empty($foto['altura']) && (int)$foto['altura'] > (int)$foto['largura'];
}
