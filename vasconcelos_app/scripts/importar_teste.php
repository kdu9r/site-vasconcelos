<?php
/**
 * Carrega os 15 imóveis de teste (database/imoveis-teste.json).
 * Uso:  php scripts/importar_teste.php
 * As fotos apontam para a Code49 (url_externa) até a migração de verdade.
 */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__ . '/../bootstrap.php';

$fotosLocais = in_array('--fotos-de-exemplo', $argv, true);
$lista = json_decode((string)file_get_contents(APP_DIR . '/database/imoveis-teste.json'), true);
$mapaTipo = ['apartamento' => 'apartamento', 'cobertura' => 'cobertura', 'sobrado' => 'sobrado', 'casa' => 'casa', 'terreno' => 'terreno'];
$n = 0;
foreach ($lista as $i => $t) {
    $locacao = $t['transacao'] === 'locacao';
    $d = [
        'codigo' => (string)$t['codigo'],
        'titulo' => mb_substr($t['titulo'], 0, 100),
        'slug' => slugify($t['titulo']),
        'descricao' => $t['descricao'],
        'tipo' => $mapaTipo[$t['tipo']] ?? 'casa',
        'finalidade' => 'residencial',
        'preco_venda' => $locacao ? null : (float)$t['preco'],
        'preco_locacao' => $locacao ? (float)$t['preco'] : null,
        'preco_temporada' => null,
        'locacao_pacote' => !empty($t['pacote']) ? 1 : 0,
        'condominio' => $t['condominio'] ?? null,
        'iptu' => $t['iptu'] ?? null,
        'iptu_periodo' => 'mensal',
        'quartos' => $t['quartos'], 'suites' => null, 'banheiros' => $t['banheiros'], 'vagas' => $t['vagas'],
        'area_util' => $t['tipo'] === 'terreno' ? null : $t['area_m2'],
        'area_total' => $t['tipo'] === 'terreno' ? $t['area_m2'] : null,
        'bairro' => $t['bairro'], 'cidade' => 'Bertioga', 'uf' => 'SP', 'cep' => '11250-000',
        'exibir_endereco' => 'bairro',
        'destaque_site' => $i < 6 ? 1 : 0,
        'status' => 'ativo',
        'url_antiga' => $t['url_origem'],
    ];
    if (preg_match('~vasconceloscorretor\.com/(\d+)/imoveis/~', $t['url_origem'], $m)) {
        $d['code49_id'] = (int)$m[1];
    }
    $existente = q('SELECT id FROM imoveis WHERE codigo = ?', [$d['codigo']])->fetchColumn();
    $id = salvar_imovel($d, $existente ? (int)$existente : null);
    foreach (fotos_do_imovel($id) as $f) apagar_arquivos_foto($f);
    q('DELETE FROM fotos WHERE imovel_id = ?', [$id]);

    if ($fotosLocais) {
        // Fotos de exemplo geradas localmente (só para testar o visual)
        $cores = [[127,169,170],[157,180,192],[200,183,154],[111,151,166],[169,191,164],[191,169,140]];
        $qtd = 6;
        for ($k = 0; $k < $qtd; $k++) {
            $emPe = ($k === 3);
            [$w, $h] = $emPe ? [900, 1200] : [1600, 1067];
            $img = imagecreatetruecolor($w, $h);
            $c = $cores[($i + $k) % count($cores)];
            for ($y = 0; $y < $h; $y++) {
                $f = $y / $h;
                $col = imagecolorallocate($img, (int)($c[0] * (1 - .25 * $f)), (int)($c[1] * (1 - .25 * $f)), (int)($c[2] * (1 - .15 * $f)));
                imageline($img, 0, $y, $w, $y, $col);
            }
            $branco = imagecolorallocatealpha($img, 255, 255, 255, 70);
            imagefilledrectangle($img, (int)($w * .3), (int)($h * .42), (int)($w * .7), (int)($h * .78), $branco);
            imagefilledpolygon($img, [(int)($w * .26), (int)($h * .44), (int)($w * .5), (int)($h * .22), (int)($w * .74), (int)($h * .44)], $branco);
            $areia = imagecolorallocate($img, 232, 221, 200);
            imagefilledrectangle($img, 0, (int)($h * .86), $w, $h, $areia);
            $tmp = tempnam(sys_get_temp_dir(), 'ex') . '.jpg';
            imagejpeg($img, $tmp, 85);
            $r = processar_imagem($id, $tmp);
            unlink($tmp);
            q('INSERT INTO fotos (imovel_id, arquivo, largura, altura, ordem) VALUES (?,?,?,?,?)', [$id, $r['nome'], $r['largura'], $r['altura'], $k]);
        }
    } else {
        foreach ($t['fotos'] as $k => $url) {
            q('INSERT INTO fotos (imovel_id, url_externa, ordem) VALUES (?,?,?)', [$id, $url, $k]);
        }
    }
    $n++;
}
echo "$n imóveis de teste carregados.\n";
