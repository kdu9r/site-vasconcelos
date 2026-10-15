<?php
declare(strict_types=1);

/*
 * Itens para marcar no cadastro, no estilo do CanalPro.
 * Ficam gravados como texto, um por linha, nas colunas imoveis.caracteristicas e condominios.lazer.
 * Assim continuam compatíveis com o que vem da Code49 e com o feed (mapa_features em feed.php).
 */

function itens_do_imovel(): array
{
    return [
        'Ar-condicionado', 'Armários planejados', 'Área de serviço', 'Aquecimento solar', 'Churrasqueira',
        'Closet', 'Cozinha americana', 'Despensa', 'Edícula', 'Escritório', 'Hidromassagem', 'Jardim',
        'Lareira', 'Lavabo', 'Mobiliado', 'Piscina privativa', 'Quintal', 'Sacada', 'Varanda gourmet',
        'Vista para o mar', 'Pé na areia', 'Aceita pet',
    ];
}

function itens_do_condominio(): array
{
    return [
        'Piscina', 'Piscina aquecida', 'Piscina infantil', 'Portão eletrônico', 'Portaria 24 horas',
        'Segurança 24 horas', 'Câmeras de segurança', 'Elevador', 'Academia', 'Salão de festas',
        'Salão de jogos', 'Espaço gourmet', 'Churrasqueira', 'Playground', 'Brinquedoteca',
        'Quadra poliesportiva', 'Quadra de tênis', 'Quadra de areia', 'Sauna', 'Spa', 'Cinema',
        'Bicicletário', 'Pet place', 'Área verde', 'Lavanderia', 'Zelador', 'Serviço de praia', 'Pé na areia',
    ];
}

function normalizar_item(string $s): string
{
    $s = mb_strtolower(trim($s));
    return strtr($s, ['á' => 'a', 'à' => 'a', 'â' => 'a', 'ã' => 'a', 'é' => 'e', 'ê' => 'e', 'í' => 'i', 'ó' => 'o', 'ô' => 'o', 'õ' => 'o', 'ú' => 'u', 'ç' => 'c', '-' => ' ']);
}

/**
 * Separa o texto salvo em itens do catálogo (para marcar) e o resto (campo "outros").
 * @return array{0: string[], 1: string}
 */
function separar_itens(?string $texto, array $catalogo): array
{
    $mapa = [];
    foreach ($catalogo as $item) $mapa[normalizar_item($item)] = $item;
    $marcados = [];
    $outros = [];
    foreach (preg_split('/\r\n|\r|\n/', (string)$texto) ?: [] as $linha) {
        $linha = trim($linha);
        if ($linha === '') continue;
        $n = normalizar_item($linha);
        // sinônimos comuns vindos da Code49
        $n = ['portaria 24h' => 'portaria 24 horas', 'seguranca 24h' => 'seguranca 24 horas', 'ar condicionado' => 'ar condicionado',
              'cameras' => 'cameras de seguranca', 'monitoramento' => 'cameras de seguranca', 'mobiliada' => 'mobiliado',
              'vista mar' => 'vista para o mar', 'aceita animais' => 'aceita pet', 'pet friendly' => 'aceita pet'][$n] ?? $n;
        if (isset($mapa[$n])) {
            $marcados[$mapa[$n]] = true;
        } else {
            $outros[] = $linha;
        }
    }
    return [array_keys($marcados), implode("\n", $outros)];
}

/** Junta os itens marcados (só os do catálogo) com o campo "outros". */
function juntar_itens(array $marcados, string $outros, array $catalogo): ?string
{
    $validos = array_values(array_filter($catalogo, fn($i) => in_array($i, $marcados, true)));
    $extras = array_filter(array_map(fn($l) => mb_substr(trim(strip_tags($l)), 0, 80), preg_split('/\r\n|\r|\n/', $outros) ?: []), fn($l) => $l !== '');
    $todos = array_merge($validos, array_values($extras));
    return $todos ? implode("\n", array_unique($todos)) : null;
}
