<?php
declare(strict_types=1);

/*
 * "Preencher com texto": lê o anúncio pronto (formato da "Tarefa de criar descrição")
 * ou uma anotação solta ("Apto Enseada, 2 dorms sendo 1 suíte, 64m², venda 380mil")
 * e devolve os campos do cadastro. Segue as regras da skill de cadastro do Kadu:
 *   - banheiro = 1 quando o texto não diz (menos em terreno);
 *   - um número de área só → área útil (terreno → área total);
 *   - IPTU mensal, a não ser que o texto diga "ano";
 *   - primeira linha em destaque = título, sem repetir na descrição;
 *   - descrição como veio, sem o rodapé de contato, sem valores e sem o resumo do carrossel.
 * Nada é salvo: o painel só preenche o formulário para o corretor conferir.
 */

/** CEP de referência por bairro (tabela da skill de cadastro). O CanalPro exige CEP. */
function ceps_bairros(): array
{
    return [
        'Centro' => '11250-039', 'Maitinga' => '11251-175', 'Vista Linda' => '11258-428', 'Indaiá' => '11260-054',
        'Rio da Praia' => '11256-105', 'Jardim Raphael' => '11258-045', 'São Lourenço' => '11263-155',
    ];
}

function bairros_bertioga(): array
{
    return ['Centro', 'Maitinga', 'Vista Linda', 'Indaiá', 'Rio da Praia', 'Jardim Raphael', 'São Lourenço',
        'Riviera de São Lourenço', 'Guaratuba', 'Boracéia', 'Costa do Sol', 'Jardim Lido', 'Enseada', 'Albatroz',
        'Vicente de Carvalho', 'Chácaras', 'Morada da Praia', 'Hanga Roa', 'Itaguaré', 'Buriqui', 'Caiubura',
        'Jardim Rio da Praia', 'Mirim', 'Costa Nativa', 'São Rafael'];
}

/** "R$ 1 milhão e 190 mil", "1,2 milhão", "650 mil", "380mil", "R$ 4.500,00", "4,5 mil" → número. */
function valor_por_extenso(string $s): ?float
{
    $s = mb_strtolower(str_replace(["\u{00A0}", 'r$'], [' ', ''], $s));
    $num = fn(string $n) => (float)str_replace(',', '.', str_replace('.', '', $n));
    $total = 0.0;
    $achou = false;
    if (preg_match('/(\d+(?:[.,]\d+)?)\s*(milh[õoã]o|milh[õo]es|mi\b)/u', $s, $m)) {
        $total += $num($m[1]) * 1_000_000;
        $achou = true;
        $s = str_replace($m[0], ' ', $s);
    }
    if (preg_match('/(\d+(?:[.,]\d+)?)\s*(mil\b|k\b)/u', $s, $m)) {
        $total += $num($m[1]) * 1000;
        $achou = true;
    } elseif (!$achou && preg_match('/\d{1,3}(?:\.\d{3})+(?:,\d{1,2})?|\d+(?:,\d{1,2})?/', $s, $m)) {
        $total = (float)str_replace(',', '.', str_replace('.', '', $m[0]));
        $achou = true;
    }
    return $achou && $total > 0 ? $total : null;
}

function numero_por_palavra(string $s): ?int
{
    $s = mb_strtolower(trim($s));
    if (ctype_digit($s)) return (int)$s;
    return ['um' => 1, 'uma' => 1, 'dois' => 2, 'duas' => 2, 'três' => 3, 'tres' => 3, 'quatro' => 4, 'cinco' => 5, 'seis' => 6][$s] ?? null;
}

/** @return array{campos: array, avisos: string[]} */
function extrair_imovel_texto(string $texto, array $condominios = []): array
{
    $texto = trim(preg_replace('/^```\w*\s*|```\s*$/m', '', $texto) ?? $texto);
    $linhas = array_map('trim', preg_split('/\r\n|\r|\n/', $texto) ?: []);
    $baixo = mb_strtolower($texto);
    $c = [];
    $avisos = [];
    $NUM = '(\d+|um|uma|dois|duas|tr[êe]s|quatro|cinco|seis)';

    /* ---------- Partes do anúncio ---------- */
    $fimCorpo = count($linhas);
    foreach ($linhas as $i => $l) {
        if (preg_match('/^contato:?$/iu', $l) || preg_match('/creci\s*\d/i', $l) || str_starts_with($l, '⚠️')) {
            $fimCorpo = $i;
            break;
        }
    }
    $corpo = array_slice($linhas, 0, $fimCorpo);
    while ($corpo && end($corpo) === '') array_pop($corpo);

    // título: primeira linha curta e em destaque, seguida de mais texto
    $naoVazias = array_values(array_filter($corpo, fn($l) => $l !== ''));
    $bairroNota = null;
    if (count($naoVazias) > 2 && str_word_count($naoVazias[0]) <= 3) {
        foreach (array_merge(bairros_bertioga(), array_keys(ceps_bairros())) as $b) {
            if (normalizar_item($naoVazias[0]) === normalizar_item($b)) {
                $bairroNota = $b;
                $pos = array_search($naoVazias[0], $corpo, true);
                $corpo = array_slice($corpo, $pos + 1);
                array_shift($naoVazias);
                break;
            }
        }
    }
    $primeira = $naoVazias[0] ?? '';
    $ehTitulo = count($naoVazias) > 1 && mb_strlen($primeira) <= 100 && !str_starts_with($primeira, '•')
        && (preg_match('/^[^\p{Ll}]*$/u', $primeira) || !preg_match('/[.!?]$/u', $primeira));
    if ($ehTitulo) {
        $c['titulo'] = mb_substr(titulo_sem_caixa_alta(trim($primeira, " *")), 0, 100);
        $pos = array_search($primeira, $corpo, true);
        $corpo = array_slice($corpo, $pos + 1);
    }

    // seções: imóvel × condomínio; linhas de valores ficam fora da descrição
    $secao = 'imovel';
    $textoImovel = '';
    $textoCond = '';
    $descricao = [];
    $linhasValor = [];
    foreach ($corpo as $l) {
        $lb = mb_strtolower($l);
        if (preg_match('/^(o\s+)?condom[íi]nio\s+(possui|oferece|tem|conta com)|^lazer( do condom[íi]nio)?:?$|^[áa]rea de lazer:?$/u', $lb)) {
            $secao = 'condominio';
            $descricao[] = $l;
            continue;
        }
        $ehValor = preg_match('/r\$\s*\d|💰|^\s*(valor|venda|loca[çc][ãa]o|aluguel|condom[íi]nio|iptu)\b[^\n]*\d/u', $lb);
        if ($ehValor) {
            $linhasValor[] = $l;
            continue;
        }
        if ($secao === 'condominio' && $l === '' && $textoCond !== '') $secao = 'imovel_fim';
        if ($secao === 'condominio') $textoCond .= $l . "\n";
        else $textoImovel .= $l . "\n";
        $descricao[] = $l;
    }
    $desc = trim(preg_replace("/\n{3,}/", "\n\n", implode("\n", $descricao)) ?? '');
    if (mb_strlen($desc) >= 20) {
        $c['descricao'] = mb_substr($desc, 0, 3000);
    }
    // anotação solta, sem formato de anúncio: tudo vale como texto do imóvel
    $tudo = $baixo;
    $semSecoes = $textoCond === '';
    $txtImovel = mb_strtolower($semSecoes ? $texto : $textoImovel . ($c['titulo'] ?? ''));

    /* ---------- Tipo ---------- */
    $tipos = [
        'cobertura' => '/cobertura(?! coletiva)/u', 'villagio' => '/vil+agg?io/u', 'kitnet' => '/kitnet|kitinete|\bstudio\b|\bestúdio\b/u',
        'casa_condominio' => '/casa (em|de) condom[íi]nio/u', 'sobrado' => '/sobrado/u', 'flat' => '/\bflat\b/u',
        'chacara' => '/ch[áa]cara|s[íi]tio\b/u', 'galpao' => '/galp[ãa]o/u', 'sala' => '/sala comercial|conjunto comercial/u',
        'loja' => '/\bloja\b|ponto comercial/u', 'terreno' => '/terreno|\blote\b/u', 'apartamento' => '/apartamento|\bapto\b|\bap\b/u',
        'casa' => '/\bcasa\b/u',
    ];
    // o título manda ("terreno ... com projeto para sobrado" é terreno); senão, o texto todo
    foreach ([mb_strtolower($c['titulo'] ?? $primeira), $baixo] as $alvoTipo) {
        foreach ($tipos as $k => $re) {
            if (preg_match($re, $alvoTipo)) { $c['tipo'] = $k; break 2; }
        }
    }
    $terreno = in_array($c['tipo'] ?? '', ['terreno', 'chacara', 'terreno_comercial'], true);

    /* ---------- Negócio e valores ---------- */
    $venda = (bool)preg_match('/\bvenda\b|vende-se|\bvendo\b|à venda|a venda/u', $tudo);
    $locacao = (bool)preg_match('/loca[çc][ãa]o|aluga|aluguel|alugar/u', $tudo);
    $juntas = [];
    foreach ($linhasValor as $l) {
        $soValor = preg_match('/^\s*(r\$\s*)?\d/iu', $l) && !preg_match('/\p{L}{4,}/u', $l);
        if ($soValor && $juntas && !preg_match('/\d/', end($juntas))) {
            $juntas[count($juntas) - 1] .= ' ' . $l;
        } else {
            $juntas[] = $l;
        }
    }
    foreach ($juntas ?: preg_split('/[,;\n]/', $texto) as $l) {
        $lb = mb_strtolower($l);
        $v = valor_por_extenso(preg_replace('/^[^\d]*?(?=r\$|\d)/u', '', $lb) ?? $lb);
        if ($v === null) continue;
        // a palavra que aparece primeiro na linha decide ("Locação: R$ 4.500 (pacote com condomínio e IPTU)")
        $tipos = ['iptu' => '/iptu/u', 'condominio' => '/condom[íi]nio/u', 'locacao' => '/loca[çc][ãa]o|aluguel|aluga|\/m[êe]s|mensal/u',
                  'venda' => '/venda|vendo/u'];
        $qual = null;
        $menor = PHP_INT_MAX;
        foreach ($tipos as $k => $re) {
            if (preg_match($re, $lb, $mt, PREG_OFFSET_CAPTURE) && $mt[0][1] < $menor) { $menor = $mt[0][1]; $qual = $k; }
        }
        if ($qual === 'iptu') {
            $c['iptu'] = $v;
            $c['iptu_periodo'] = preg_match('/ano|anual/u', $lb) ? 'anual' : 'mensal';
        } elseif ($qual === 'condominio') {
            $c['condominio'] = $v;
        } elseif ($qual === 'locacao') {
            $c['preco_locacao'] = $v;
            if (preg_match('/pacote|inclu/u', $lb)) $c['locacao_pacote'] = 1;
        } elseif ($qual === 'venda' || preg_match('/valor|pre[çc]o|r\$|mil|milh|\d/u', $lb)) {
            // "Valor de investimento: R$ 300 mil": decide pelo resto do anúncio
            if ($qual !== 'venda' && $locacao && !$venda) {
                $c['preco_locacao'] = $v;
                if (preg_match('/pacote|inclu/u', $lb)) $c['locacao_pacote'] = 1;
            } else {
                $c['preco_venda'] = $v;
            }
        }
    }
    $c['negocio'] = array_values(array_filter([
        isset($c['preco_venda']) || ($venda && !isset($c['preco_locacao'])) ? 'venda' : null,
        isset($c['preco_locacao']) || ($locacao && !isset($c['preco_venda'])) ? 'locacao' : null,
    ]));
    if (!$c['negocio']) unset($c['negocio']);

    /* ---------- Cômodos ---------- */
    if (preg_match("/$NUM\s*(dormit[óo]rios?|dorms?\.?|quartos?)/u", $txtImovel, $m)) {
        $c['quartos'] = numero_por_palavra($m[1]);
    }
    if (preg_match("/$NUM\s*su[íi]tes?/u", $txtImovel, $m)) {
        $c['suites'] = numero_por_palavra($m[1]);
    } elseif (preg_match('/(?<!\d\s)\bsu[íi]te\b/u', $txtImovel)) {
        $c['suites'] = 1;
    }
    if (preg_match("/$NUM\s*banheiros?/u", $txtImovel, $m)) {
        $c['banheiros'] = numero_por_palavra($m[1]);
    } elseif (!$terreno) {
        $social = preg_match('/banheiro|lavabo social|wc/u', $txtImovel) ? 1 : 0;
        $c['banheiros'] = max(1, (int)($c['suites'] ?? 0) + $social);
        $avisos[] = 'Banheiros não estavam no texto: coloquei ' . $c['banheiros'] . '.';
    }
    if (preg_match("/$NUM\s*vagas?/u", $txtImovel, $m)) {
        $c['vagas'] = numero_por_palavra($m[1]);
    } elseif (preg_match('/sem vaga|n[ãa]o (tem|possui) vaga/u', $txtImovel)) {
        $c['vagas'] = 0;
    } elseif (preg_match('/\bvaga\b|garagem/u', $txtImovel)) {
        $c['vagas'] = 1;
    }
    if ($terreno) {
        unset($c['quartos'], $c['suites'], $c['banheiros'], $c['vagas']);
    }

    /* ---------- Áreas ---------- */
    $areas = [];
    if (preg_match_all('/(\d{1,3}(?:\.\d{3})*(?:,\d+)?|\d+(?:[.,]\d+)?)\s*(m²|m2|metros quadrados|mts|metros\b(?!\s+da))/u', $txtImovel, $mm, PREG_OFFSET_CAPTURE)) {
        foreach ($mm[1] as $i => [$n, $pos]) {
            $antes = mb_substr(substr($txtImovel, max(0, $pos - 40), min(40, $pos)), -40);
            $v = (float)str_replace(',', '.', str_replace('.', '', $n));
            if ($v < 10 || $v > 100000) continue;
            if (preg_match('/m da praia|metros da praia/u', substr($txtImovel, $pos, 40))) continue;
            $tipoArea = preg_match('/total|terreno|lote/u', $antes) ? 'area_total' : (preg_match('/[úu]til|privativa|constru/u', $antes) ? 'area_util' : null);
            $areas[] = [$tipoArea, $v];
        }
    }
    foreach ($areas as [$k, $v]) {
        if ($k && !isset($c[$k])) $c[$k] = $v;
    }
    $semTipo = array_values(array_filter($areas, fn($a) => $a[0] === null));
    if ($semTipo) {
        $alvo = $terreno ? 'area_total' : 'area_util';
        if (!isset($c[$alvo])) $c[$alvo] = $semTipo[0][1];
        elseif (!isset($c['area_total']) && isset($semTipo[1])) $c['area_total'] = $semTipo[1][1];
    }

    /* ---------- Bairro, CEP ---------- */
    $conhecidos = array_unique(array_merge(bairros_bertioga(), array_keys(ceps_bairros())));
    usort($conhecidos, fn($a, $b) => mb_strlen($b) <=> mb_strlen($a));
    $norm = fn(string $s) => normalizar_item($s);
    $alvoBairro = ' ' . $norm(str_replace(['Jd.', 'jd.', 'Jd '], ['Jardim', 'jardim', 'Jardim '], ($c['titulo'] ?? '') . ' ' . $texto)) . ' ';
    foreach ($conhecidos as $b) {
        if (preg_match('/(?<!\p{L})' . preg_quote($norm($b), '/') . '(?!\p{L})/u', $alvoBairro)) {
            $c['bairro'] = $b;
            break;
        }
    }
    if ($bairroNota) {
        $c['bairro'] = $bairroNota;
    }
    if (isset($c['bairro'], ceps_bairros()[$c['bairro']])) {
        $c['cep'] = ceps_bairros()[$c['bairro']];
    }

    /* ---------- Itens (estilo CanalPro) ---------- */
    $achar = function (string $txt, array $catalogo, array $sinonimos) use ($norm): array {
        $t = ' ' . $norm($txt) . ' ';
        $out = [];
        foreach ($sinonimos as $re => $item) {
            if (preg_match($re, $t)) { $out[$item] = true; $t = preg_replace($re, ' ', $t) ?? $t; }
        }
        $lista = $catalogo;
        usort($lista, fn($a, $b) => mb_strlen($b) <=> mb_strlen($a));
        foreach ($lista as $item) {
            $re = '/(?<!\p{L})' . preg_quote($norm($item), '/') . '(?!\p{L})/u';
            if (preg_match($re, $t)) { $out[$item] = true; $t = preg_replace($re, ' ', $t) ?? $t; }
        }
        return array_keys($out);
    };
    $sinImovel = [
        '/moveis planejados|armarios? planejados?|planejad[oa]s/u' => 'Armários planejados', '/ar condicionado/u' => 'Ar-condicionado',
        '/mobiliad[oa]/u' => 'Mobiliado', '/vista (para o |pro )?mar/u' => 'Vista para o mar', '/aceita (pet|animais|cachorro)|pet friendly/u' => 'Aceita pet',
        '/pe na areia/u' => 'Pé na areia', '/piscina (privativa|propria)|com piscina|terraco privativo com piscina/u' => 'Piscina privativa',
        '/escritorio|home office/u' => 'Escritório', '/aquecimento solar|aquecedor solar/u' => 'Aquecimento solar',
    ];
    $sinCond = [
        '/portaria 24|portaria 24h/u' => 'Portaria 24 horas', '/seguranca 24|vigilancia 24/u' => 'Segurança 24 horas',
        '/cameras?|monitoramento|cftv/u' => 'Câmeras de segurança', '/quadra (poli)?esportiva/u' => 'Quadra poliesportiva',
        '/piscina infantil|piscina adulto e infantil/u' => 'Piscina infantil', '/espaco pet|pet place/u' => 'Pet place',
    ];
    $itensImovel = $achar($semSecoes ? $texto : $textoImovel . ($c['titulo'] ?? ''), itens_do_imovel(), $sinImovel);
    if ($semSecoes) {
        // anotação solta: o que é típico de condomínio vai para o condomínio
        $c['itens'] = array_values(array_diff($itensImovel, ['Piscina']));
    } else {
        $c['itens'] = $itensImovel;
        $condItens = $achar($textoCond, itens_do_condominio(), $sinCond);
        if (in_array('Piscina infantil', $condItens, true) && preg_match('/adulto/u', $norm($textoCond))) $condItens[] = 'Piscina';
        if ($condItens) $c['cond_itens'] = array_values(array_unique($condItens));
    }
    if (!$c['itens']) unset($c['itens']);

    /* ---------- Condomínio ---------- */
    foreach ($condominios as $cond) {
        if (mb_strlen($cond['nome']) >= 4 && str_contains($norm($texto), $norm($cond['nome']))) {
            $c['condominio_id'] = (int)$cond['id'];
            break;
        }
    }
    if (isset($c['condominio_id']) || isset($c['cond_itens'])) {
        $c['tem_condominio'] = 1;
        if (!isset($c['condominio_id'])) {
            $avisos[] = 'O texto fala do condomínio, mas não achei qual é: selecione na lista ou cadastre um novo.';
        }
    }

    /* ---------- Vídeo ---------- */
    if (preg_match('~https?://(?:www\.)?(?:youtube\.com|youtu\.be)/\S+~', $texto, $m) && youtube_id($m[0])) {
        $c['video_url'] = $m[0];
    }

    // avisos úteis
    if (count(array_filter($areas, fn($a) => $a[0] === null)) === 1 && !$terreno && isset($c['area_util'])) {
        $avisos[] = 'Só havia uma área no texto: coloquei como área útil.';
    }
    if ($linhasValor && isset($c['descricao'])) {
        $avisos[] = 'Valores, contatos e o resumo do carrossel ficaram fora do texto do anúncio (o site mostra os valores atualizados).';
    }
    return ['campos' => $c, 'avisos' => $avisos];
}
