<?php
declare(strict_types=1);

/**
 * Migração da Code49 (API externa v2).
 * Documentação: https://www.code49.com/docs/api_externa.html?version=v2
 * Limites: 60 requisições/minuto e 1.000/dia. Cada imóvel usa 1 requisição de detalhe.
 */

final class Code49LimiteException extends RuntimeException
{
    public function __construct(public readonly int $esperarSegundos, string $msg)
    {
        parent::__construct($msg);
    }
}

function code49_fila_arquivo(): string
{
    return APP_DIR . '/storage/code49_fila.json';
}

function code49_relatorio_arquivo(): string
{
    return APP_DIR . '/storage/relatorio_migracao.csv';
}

function code49_get(string $caminho, array $query = []): array
{
    $token = (string)config('code49.token');
    if ($token === '') {
        throw new RuntimeException('Token da Code49 não configurado no config.php.');
    }
    $url = rtrim((string)config('code49.base_url'), '/') . '/' . ltrim($caminho, '/');
    if ($query) {
        $url .= '?' . http_build_query($query);
    }
    $headers = [];
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $token, 'Accept: application/json'],
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HEADERFUNCTION => function ($ch, $linha) use (&$headers) {
            $partes = explode(':', $linha, 2);
            if (count($partes) === 2) {
                $headers[strtolower(trim($partes[0]))] = trim($partes[1]);
            }
            return strlen($linha);
        },
    ]);
    $corpo = curl_exec($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $erro = curl_error($ch);
    curl_close($ch);

    if ($corpo === false) {
        throw new RuntimeException('Sem conexão com a Code49: ' . $erro);
    }
    if ($status === 429) {
        $espera = (int)($headers['retry-after'] ?? 60);
        throw new Code49LimiteException($espera, $espera > 120
            ? 'Limite diário da API da Code49 atingido. Continue amanhã: o que já foi importado fica salvo.'
            : 'Limite por minuto atingido. Aguardando…');
    }
    if ($status === 401) {
        throw new RuntimeException('Token da Code49 inválido ou expirado.');
    }
    if ($status >= 400) {
        throw new RuntimeException("Code49 respondeu HTTP $status em $caminho.");
    }
    $json = json_decode((string)$corpo, true);
    if (!is_array($json)) {
        throw new RuntimeException('Resposta inválida da Code49.');
    }
    return $json;
}

/** Busca a lista de todos os imóveis (máx. 100 por página) e grava a fila. */
function code49_montar_fila(): int
{
    $fila = [];
    $pagina = 1;
    do {
        $r = code49_get('properties', ['page' => $pagina, 'per_page' => 100]);
        foreach ((array)($r['data'] ?? []) as $item) {
            if (isset($item['id'])) {
                $fila[] = (int)$item['id'];
            }
        }
        $total = (int)($r['pagination']['total_pages'] ?? 1);
        $pagina++;
    } while ($pagina <= $total);

    $fila = array_values(array_unique($fila));
    file_put_contents(code49_fila_arquivo(), json_encode(['pendentes' => $fila, 'total' => count($fila), 'feitos' => 0]), LOCK_EX);
    if (!is_file(code49_relatorio_arquivo())) {
        file_put_contents(code49_relatorio_arquivo(), "data;id_code49;codigo;situacao;observacao\n");
    }
    return count($fila);
}

function code49_estado_fila(): array
{
    $f = code49_fila_arquivo();
    if (!is_file($f)) {
        return ['pendentes' => [], 'total' => 0, 'feitos' => 0];
    }
    return json_decode((string)file_get_contents($f), true) ?: ['pendentes' => [], 'total' => 0, 'feitos' => 0];
}

/**
 * Importa o próximo imóvel da fila.
 * @return array{restantes:int, total:int, feitos:int, codigo:?string, situacao:string, obs:string}
 */
function code49_processar_proximo(): array
{
    $estado = code49_estado_fila();
    if (!$estado['pendentes']) {
        return ['restantes' => 0, 'total' => $estado['total'], 'feitos' => $estado['feitos'], 'codigo' => null, 'situacao' => 'fim', 'obs' => ''];
    }
    $id = (int)$estado['pendentes'][0];
    $res = code49_importar_imovel($id); // lança Code49LimiteException sem tirar da fila

    array_shift($estado['pendentes']);
    $estado['feitos']++;
    file_put_contents(code49_fila_arquivo(), json_encode($estado), LOCK_EX);
    file_put_contents(code49_relatorio_arquivo(), implode(';', [
        date('Y-m-d H:i'), $id, $res['codigo'] ?? '', $res['situacao'], str_replace([';', "\n"], [',', ' '], $res['obs']),
    ]) . "\n", FILE_APPEND | LOCK_EX);

    return ['restantes' => count($estado['pendentes']), 'total' => $estado['total'], 'feitos' => $estado['feitos']] + $res;
}

function code49_tipo(string $tipoCrm): string
{
    $t = mb_strtolower($tipoCrm);
    return match (true) {
        str_contains($t, 'cobertura') || str_contains($t, 'penthouse') => 'cobertura',
        str_contains($t, 'villagio') || str_contains($t, 'villágio') || str_contains($t, 'vila') => 'villagio',
        str_contains($t, 'sobrado') => 'sobrado',
        str_contains($t, 'condom') => 'casa_condominio',
        str_contains($t, 'kitnet') || str_contains($t, 'kitinete') => 'kitnet',
        str_contains($t, 'flat') => 'flat',
        str_contains($t, 'apart') || str_contains($t, 'duplex') || str_contains($t, 'apartment') => 'apartamento',
        str_contains($t, 'sala') || str_contains($t, 'office') || str_contains($t, 'escrit') => 'sala',
        str_contains($t, 'loja') || str_contains($t, 'ponto') || str_contains($t, 'comercial') => 'loja',
        str_contains($t, 'galp') => 'galpao',
        str_contains($t, 'chácara') || str_contains($t, 'chacara') || str_contains($t, 'sítio') || str_contains($t, 'sitio') || str_contains($t, 'rural') || str_contains($t, 'fazenda') => 'chacara',
        str_contains($t, 'terreno') || str_contains($t, 'lote') || str_contains($t, 'área') || str_contains($t, 'area') || str_contains($t, 'land') => 'terreno',
        default => 'casa',
    };
}

function code49_status(string $s, bool $exibir): string
{
    if (!$exibir) return 'pausado';
    return match ($s) {
        'Active' => 'ativo',
        'Sold' => 'vendido',
        'Rented' => 'alugado',
        default => 'pausado',
    };
}

function code49_texto(string $html): string
{
    $t = preg_replace('~<br\s*/?>|</p>|</li>|</div>~i', "\n", $html) ?? $html;
    $t = html_entity_decode(strip_tags($t), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $t = preg_replace("/[ \t]+/", ' ', $t) ?? $t;
    $t = preg_replace("/\n{3,}/", "\n\n", $t) ?? $t;
    return trim($t);
}

/** Converte o JSON de detalhe da Code49 para as colunas do nosso banco. */
function code49_mapear(array $p): array
{
    $obs = [];
    $loc = (array)($p['location'] ?? []);
    $descricao = code49_texto((string)($p['description'] ?? ''));
    $titulo = trim(code49_texto((string)($p['title'] ?? '')));
    $titulo = titulo_sem_caixa_alta($titulo);
    if (mb_strlen($titulo) > 100) {
        $titulo = mb_substr($titulo, 0, 97) . '...';
        $obs[] = 'título encurtado';
    }
    $tipo = code49_tipo((string)($p['property_type'] ?? $p['listing_type'] ?? ''));
    if (mb_strlen($titulo) < 10) {
        $titulo = tipo_rotulo($tipo) . ' em ' . ($loc['neighborhood'] ?? 'Bertioga');
    }

    $precos = ['venda' => null, 'locacao' => null, 'temporada' => null, 'condominio' => null, 'iptu' => null];
    foreach ((array)($p['prices'] ?? []) as $pr) {
        $tipoPreco = mb_strtolower((string)($pr['type'] ?? ''));
        $valor = is_numeric($pr['value'] ?? null) ? (float)$pr['value'] : null;
        if ($valor === null || $valor <= 0) continue;
        $chave = match (true) {
            str_contains($tipoPreco, 'condom') => 'condominio',
            str_contains($tipoPreco, 'iptu') => 'iptu',
            str_contains($tipoPreco, 'temporada') || str_contains($tipoPreco, 'vacation') || str_contains($tipoPreco, 'diária') => 'temporada',
            str_contains($tipoPreco, 'loca') || str_contains($tipoPreco, 'rent') || str_contains($tipoPreco, 'aluguel') => 'locacao',
            str_contains($tipoPreco, 'venda') || str_contains($tipoPreco, 'sale') || str_contains($tipoPreco, 'lançamento') || str_contains($tipoPreco, 'launch') => 'venda',
            default => null,
        };
        if ($chave === null) {
            $obs[] = 'preço não reconhecido: ' . ($pr['type'] ?? '?');
            continue;
        }
        $precos[$chave] ??= $valor;
    }
    $transacoes = (array)($p['transactions'] ?? []);
    foreach (['Sale' => 'venda', 'Rent' => 'locacao', 'Vacation' => 'temporada'] as $api => $nosso) {
        if (in_array($api, $transacoes, true) && $precos[$nosso] === null) {
            $obs[] = "sem valor de $nosso (ficará fora dessa busca)";
        }
    }
    $pacote = $precos['locacao'] !== null && preg_match('/pacote|incluso|inclui/i', $descricao) ? 1 : 0;

    $cep = preg_replace('/\D/', '', (string)($loc['zip_code'] ?? '')) ?? '';
    $lat = is_numeric($loc['latitude'] ?? null) ? (float)$loc['latitude'] : null;
    $lng = is_numeric($loc['longitude'] ?? null) ? (float)$loc['longitude'] : null;
    if ($lat !== null && (abs($lat) < 0.001 || abs($lat) > 90)) { $lat = $lng = null; }

    $features = array_filter(array_map(fn($f) => trim((string)$f), (array)($p['features'] ?? [])));
    $uf = strtoupper((string)($loc['state_code'] ?? 'SP')) ?: 'SP';

    return [
        'dados' => [
            'titulo' => $titulo,
            'slug' => slugify($titulo),
            'descricao' => mb_substr($descricao, 0, 3000),
            'tipo' => $tipo,
            'finalidade' => in_array('Commercial', (array)($p['purposes'] ?? []), true) ? 'comercial' : 'residencial',
            'preco_venda' => $precos['venda'],
            'preco_locacao' => $precos['locacao'],
            'preco_temporada' => $precos['temporada'],
            'locacao_pacote' => $pacote,
            'condominio' => $precos['condominio'],
            'iptu' => $precos['iptu'],
            'iptu_periodo' => 'mensal',
            'quartos' => isset($p['bedrooms']) ? (int)$p['bedrooms'] : null,
            'suites' => isset($p['suites']) ? (int)$p['suites'] : null,
            'banheiros' => isset($p['bathrooms']) ? (int)$p['bathrooms'] : null,
            'vagas' => isset($p['parking_spaces']) ? (int)$p['parking_spaces'] : (isset($p['garage']) ? (int)$p['garage'] : null),
            'area_util' => is_numeric($p['useful_area'] ?? null) && (float)$p['useful_area'] > 0 ? (float)$p['useful_area'] : (is_numeric($p['built_area'] ?? null) && (float)$p['built_area'] > 0 ? (float)$p['built_area'] : null),
            'area_total' => is_numeric($p['total_area'] ?? null) && (float)$p['total_area'] > 0 ? (float)$p['total_area'] : null,
            'endereco' => ($loc['address'] ?? null) ?: null,
            'numero' => ($loc['number'] ?? null) ?: null,
            'complemento' => ($loc['complement'] ?? null) ?: null,
            'bairro' => ($loc['neighborhood'] ?? null) ?: 'Bertioga',
            'cidade' => ($loc['city'] ?? null) ?: 'Bertioga',
            'uf' => substr($uf, 0, 2),
            'cep' => strlen($cep) === 8 ? substr($cep, 0, 5) . '-' . substr($cep, 5) : (string)config('contato.cep_padrao'),
            'latitude' => $lat,
            'longitude' => $lng,
            'exibir_endereco' => !empty($p['display_address']) ? 'rua' : 'bairro',
            'caracteristicas' => $features ? implode("\n", $features) : null,
            'status' => code49_status((string)($p['status'] ?? ''), (bool)($p['display_website'] ?? true)),
            'code49_id' => (int)$p['id'],
            'code49_atualizado' => (string)($p['updated_at'] ?? ''),
            'url_antiga' => ($p['property_url'] ?? null) ?: null,
        ],
        'fotos' => (array)($p['photos'] ?? []),
        'obs' => $obs,
    ];
}

/** Importa (ou atualiza) um imóvel. Pode ser executado várias vezes sem duplicar. */
function code49_importar_imovel(int $code49Id): array
{
    $r = code49_get('properties/' . $code49Id);
    $p = isset($r['data']) && is_array($r['data']) ? $r['data'] : $r;
    if (empty($p['id'])) {
        return ['codigo' => null, 'situacao' => 'erro', 'obs' => 'resposta sem dados'];
    }
    $m = code49_mapear($p);
    $d = $m['dados'];
    $d['condominio_id'] = condominio_da_code49(is_array($p['building'] ?? null) ? $p['building'] : null);
    $codigo = preg_replace('/[^A-Za-z0-9]/', '', (string)($p['code'] ?? '')) ?: (string)$p['id'];

    $existente = q('SELECT id, code49_atualizado FROM imoveis WHERE code49_id = ? OR codigo = ? LIMIT 1', [$d['code49_id'], $codigo])->fetch();
    // Já importado por completo (code49_atualizado só é gravado no fim): não mexe,
    // para não apagar fotos ou edições feitas depois no painel novo.
    if ($existente && $existente['code49_atualizado'] !== null && $existente['code49_atualizado'] !== '') {
        return ['codigo' => $codigo, 'situacao' => 'já importado', 'obs' => ''];
    }
    $atualizadoCode49 = $d['code49_atualizado'];
    $d['code49_atualizado'] = null;

    $d['codigo'] = $codigo;
    if ($existente) {
        // Importação anterior ficou pela metade: refaz
        unset($d['codigo']);
        $imovelId = salvar_imovel($d, (int)$existente['id']);
        foreach (fotos_do_imovel($imovelId) as $f) {
            apagar_arquivos_foto($f);
        }
        q('DELETE FROM fotos WHERE imovel_id = ?', [$imovelId]);
        $situacao = 'atualizado';
    } else {
        $imovelId = salvar_imovel($d);
        $situacao = 'importado';
    }

    $fotos = $m['fotos'];
    usort($fotos, fn($a, $b) => (int)($a['order'] ?? 0) <=> (int)($b['order'] ?? 0));
    $falhas = 0;
    foreach ($fotos as $f) {
        $src = is_array($f) ? ($f['fullSize'] ?? $f['mini'] ?? null) : $f;
        if (!$src) continue;
        try {
            adicionar_foto_remota($imovelId, (string)$src, is_array($f) ? ($f['description'] ?? null) : null);
        } catch (Throwable $e) {
            $falhas++;
        }
    }
    sincronizar_fotos_condominio($imovelId);
    q('UPDATE imoveis SET code49_atualizado = ? WHERE id = ?', [$atualizadoCode49 ?: date('Y-m-d H:i:s'), $imovelId]);
    $obs = $m['obs'];
    $obs[] = count($fotos) . ' fotos' . ($falhas ? " ($falhas falharam)" : '');
    return ['codigo' => $codigo, 'situacao' => $situacao, 'obs' => implode('; ', $obs)];
}

/** "APARTAMENTO PÉ NA AREIA EM BERTIOGA" → "Apartamento pé na areia em Bertioga" */
function titulo_sem_caixa_alta(string $t): string
{
    $letras = preg_replace('/[^\p{L}]/u', '', $t) ?? '';
    $maiusculas = preg_match_all('/\p{Lu}/u', $letras);
    if ($letras === '' || $maiusculas / mb_strlen($letras) < 0.8) {
        return $t; // já está em letras normais: mantém como o corretor escreveu
    }
    $t = mb_strtolower($t);
    $t = mb_strtoupper(mb_substr($t, 0, 1)) . mb_substr($t, 1);
    $nomes = ['Bertioga', 'Maitinga', 'Riviera', 'Indaiá', 'Guaratuba', 'Vista Linda', 'São Lourenço', 'São Rafael',
              'Rio da Praia', 'Albatroz', 'Enseada', 'Sesc', 'Rio-Santos', 'Jardim', 'Jd.', 'Boracéia', 'Itaguaré', 'São Paulo', 'SP'];
    foreach ($nomes as $n) {
        $t = preg_replace('/(?<!\p{L})' . preg_quote(mb_strtolower($n), '/') . '(?!\p{L})/u', $n, $t) ?? $t;
    }
    return $t;
}
