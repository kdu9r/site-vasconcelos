<?php
declare(strict_types=1);

/**
 * Palavras das características → valores oficiais do VRSync (developers.grupozap.com, elemento Features).
 * Os itens do catálogo do painel (caracteristicas.php) estão todos aqui; o resto é texto livre que casa por palavra.
 */
function mapa_features(): array
{
    return [
        // imóvel
        'ar-condicionado' => 'Cooling', 'ar condicionado' => 'Cooling', 'armários planejados' => 'Planned Furniture', 'planejad' => 'Planned Furniture',
        'armário embutido' => 'Builtin Wardrobe', 'área de serviço' => "Maid's Quarters", 'aquecimento solar' => 'Heating', 'energia solar' => 'Solar Energy',
        'churrasqueira' => 'BBQ', 'closet' => 'Closet', 'cozinha americana' => 'American Kitchen', 'cozinha gourmet' => 'Gourmet Kitchen',
        'despensa' => 'Pantry', 'edícula' => 'Edicule', 'escritório' => 'Home Office', 'hidromassagem' => 'Whirlpool', 'ofurô' => 'Hot Tub',
        'jardim' => 'Garden Area', 'lareira' => 'Fireplace', 'lavabo' => 'Lavabo', 'mobiliad' => 'Furnished', 'piscina privativa' => 'Private Pool',
        'quintal' => 'Backyard', 'sacada' => 'Balcony', 'varanda gourmet' => 'Gourmet Balcony', 'varanda' => 'Balcony',
        'vista para o mar' => 'Ocean View', 'vista mar' => 'Ocean View', 'vista panorâmica' => 'Panoramic View',
        'aceita pet' => 'Pets Allowed', 'aceita animais' => 'Pets Allowed', 'pé direito alto' => 'High Ceiling Height', 'deck' => 'Deck',
        // condomínio
        'piscina aquecida' => 'Heated Pool', 'piscina infantil' => 'Childrens Pool', 'piscina coberta' => 'Covered Pool', 'piscina' => 'Pool',
        'portão eletrônico' => 'Electronic Gate', 'portaria 24' => 'Concierge 24h', 'segurança 24' => 'Patrol', 'vigia' => 'Controlled Access',
        'câmera' => 'Security Camera', 'monitoramento' => 'Security Camera', 'interfone' => 'Intercom', 'elevador' => 'Elevator',
        'academia' => 'Gym', 'salão de festas' => 'Party Room', 'salão de jogos' => 'Game room', 'espaço gourmet' => 'Gourmet Area',
        'playground' => 'Playground', 'brinquedoteca' => 'Toys Place', 'quadra poliesportiva' => 'Sports Court', 'quadra esportiva' => 'Sports Court',
        'quadra de tênis' => 'Tennis court', 'quadra de areia' => 'Sand Pit', 'quadra de futebol' => 'Indoor Soccer', 'sauna' => 'Sauna', 'spa' => 'Spa',
        'cinema' => 'Media Room', 'bicicletário' => 'Bicycles Place', 'pet place' => 'Pet Space', 'espaço pet' => 'Pet Space',
        'área verde' => 'Green space / Park', 'lavanderia' => 'Laundry', 'zelador' => 'Caretaker', 'condomínio fechado' => 'Fenced Yard',
        'gerador' => 'Generator', 'coworking' => 'Coworking', 'solarium' => 'Solarium', 'redário' => 'Redario',
    ];
}

function features_vrsync(array $im): array
{
    $texto = mb_strtolower((string)$im['caracteristicas'] . "\n" . (string)$im['descricao']);
    $mapa = mapa_features();
    // o termo mais longo primeiro e depois apagado: "piscina privativa" não vira também "Pool"
    uksort($mapa, fn($a, $b) => mb_strlen($b) <=> mb_strlen($a));
    $out = [];
    foreach ($mapa as $chave => $feature) {
        // só casa no começo de palavra ("espaço" não vira "spa")
        $re = '/(?<!\p{L})' . preg_quote($chave, '/') . '/u';
        if (preg_match($re, $texto)) {
            $out[$feature] = true;
            $texto = preg_replace($re, ' ', $texto) ?? $texto;
        }
    }
    return array_keys($out);
}

function imoveis_para_portal(): array
{
    $lista = q("SELECT * FROM imoveis WHERE status = 'ativo' AND publicar_portais = 1 ORDER BY id")->fetchAll();
    $saida = [];
    foreach ($lista as $im) {
        $fotos = fotos_completas($im);
        $cond = condominio_por_id(isset($im['condominio_id']) ? (int)$im['condominio_id'] : null);
        if ($cond) {
            // endereço do condomínio quando o imóvel não tem o próprio
            foreach (['endereco', 'numero', 'cep', 'latitude', 'longitude'] as $k) {
                if (empty($im[$k]) && !empty($cond[$k])) $im[$k] = $cond[$k];
            }
            $im['caracteristicas'] = trim((string)$im['caracteristicas'] . "\n" . (string)$cond['lazer']);
        }
        if (!pendencias_portal($im, count($fotos))) {
            $im['fotos'] = $fotos;
            $saida[] = $im;
        }
    }
    return $saida;
}

function gerar_feed_vrsync(): string
{
    $site = rtrim((string)config('site_url'), '/');
    $x = new XMLWriter();
    $x->openMemory();
    $x->setIndent(true);
    $x->startDocument('1.0', 'UTF-8');
    $x->startElement('ListingDataFeed');
    $x->writeAttribute('xmlns', 'http://www.vivareal.com/schemas/1.0/VRSync');
    $x->writeAttribute('xmlns:xsi', 'http://www.w3.org/2001/XMLSchema-instance');
    $x->writeAttribute('xsi:schemaLocation', 'http://www.vivareal.com/schemas/1.0/VRSync  http://xml.vivareal.com/vrsync.xsd');

    $corretorPrincipal = config('contato.corretores')[0] ?? ['nome' => config('site_nome'), 'whatsapp' => config('contato.whatsapp_principal')];
    $x->startElement('Header');
    $x->writeElement('Provider', (string)config('site_nome'));
    $x->writeElement('Email', (string)config('contato.email'));
    $x->writeElement('ContactName', (string)$corretorPrincipal['nome']);
    $x->writeElement('PublishDate', date('Y-m-d\TH:i:s'));
    $x->writeElement('Telephone', formata_telefone((string)$corretorPrincipal['whatsapp']));
    $x->endElement();

    $x->startElement('Listings');
    foreach (imoveis_para_portal() as $im) {
        $tipo = tipos_imovel()[$im['tipo']];
        $x->startElement('Listing');
        $x->writeElement('ListingID', (string)$im['codigo']);
        $x->startElement('Title'); $x->writeCdata(mb_substr($im['titulo'], 0, 100)); $x->endElement();

        $venda = $im['preco_venda'] !== null;
        $aluguel = $im['preco_locacao'] !== null || $im['preco_temporada'] !== null;
        $x->writeElement('TransactionType', $venda && $aluguel ? 'Sale/Rent' : ($venda ? 'For Sale' : 'For Rent'));
        $x->writeElement('PublicationType', ['padrao' => 'STANDARD', 'destaque' => 'PREMIUM', 'super_destaque' => 'SUPER_PREMIUM'][$im['destaque_portal']]);
        $x->writeElement('DetailViewUrl', $site . url_imovel($im));

        $x->startElement('Media');
        if ($yt = youtube_id($im['video_url'])) {
            $x->startElement('Item'); $x->writeAttribute('medium', 'video'); $x->text('https://www.youtube.com/watch?v=' . $yt); $x->endElement();
        }
        foreach ($im['fotos'] as $i => $f) {
            $src = foto_url($f, 'g');
            if (str_starts_with($src, '/')) $src = $site . $src;
            $x->startElement('Item');
            $x->writeAttribute('medium', 'image');
            if ($i === 0) $x->writeAttribute('primary', 'true');
            if (!empty($f['legenda'])) $x->writeAttribute('caption', $f['legenda']);
            $x->text($src);
            $x->endElement();
        }
        $x->endElement();

        $x->startElement('Details');
        $x->writeElement('UsageType', $tipo['uso']);
        $x->writeElement('PropertyType', $tipo['vrsync']);
        $x->startElement('Description'); $x->writeCdata(mb_substr(strip_tags((string)$im['descricao']), 0, 3000)); $x->endElement();
        if ($venda) {
            $x->startElement('ListPrice'); $x->writeAttribute('currency', 'BRL'); $x->text((string)(int)round((float)$im['preco_venda'])); $x->endElement();
        }
        if ($aluguel) {
            $temporadaSo = $im['preco_locacao'] === null;
            $x->startElement('RentalPrice');
            $x->writeAttribute('currency', 'BRL');
            $x->writeAttribute('period', $temporadaSo ? 'Daily' : 'Monthly');
            $x->text((string)(int)round((float)($temporadaSo ? $im['preco_temporada'] : $im['preco_locacao'])));
            $x->endElement();
        }
        if ($im['condominio'] !== null && !$im['locacao_pacote']) {
            $x->startElement('PropertyAdministrationFee'); $x->writeAttribute('currency', 'BRL'); $x->text((string)(int)round((float)$im['condominio'])); $x->endElement();
        }
        if ($im['iptu'] !== null && !$im['locacao_pacote']) {
            $x->startElement('Iptu'); $x->writeAttribute('currency', 'BRL'); $x->writeAttribute('period', $im['iptu_periodo'] === 'anual' ? 'Yearly' : 'Monthly'); $x->text((string)(int)round((float)$im['iptu'])); $x->endElement();
        }
        if (eh_terreno($im)) {
            $x->startElement('LotArea'); $x->writeAttribute('unit', 'square metres'); $x->text((string)(int)round((float)$im['area_total'])); $x->endElement();
        } else {
            $x->startElement('LivingArea'); $x->writeAttribute('unit', 'square metres'); $x->text((string)(int)round((float)$im['area_util'])); $x->endElement();
            if ($im['area_total'] !== null) {
                $x->startElement('LotArea'); $x->writeAttribute('unit', 'square metres'); $x->text((string)(int)round((float)$im['area_total'])); $x->endElement();
            }
            $x->writeElement('Bedrooms', (string)(int)$im['quartos']);
            $x->writeElement('Bathrooms', (string)(int)$im['banheiros']);
            if ($im['suites']) $x->writeElement('Suites', (string)(int)$im['suites']);
            if ($im['vagas']) {
                $x->startElement('Garage'); $x->writeAttribute('type', 'Parking Space'); $x->text((string)(int)$im['vagas']); $x->endElement();
            }
        }
        $features = features_vrsync($im);
        if ($features) {
            $x->startElement('Features');
            foreach ($features as $feat) $x->writeElement('Feature', $feat);
            $x->endElement();
        }
        $x->endElement(); // Details

        $x->startElement('Location');
        $x->writeAttribute('displayAddress', ['bairro' => 'Neighborhood', 'rua' => 'Street', 'completo' => 'All'][$im['exibir_endereco']]);
        $x->startElement('Country'); $x->writeAttribute('abbreviation', 'BR'); $x->text('Brasil'); $x->endElement();
        $x->startElement('State'); $x->writeAttribute('abbreviation', $im['uf']); $x->text($im['uf'] === 'SP' ? 'São Paulo' : $im['uf']); $x->endElement();
        $x->startElement('City'); $x->writeCdata($im['cidade']); $x->endElement();
        $x->startElement('Neighborhood'); $x->writeCdata($im['bairro']); $x->endElement();
        if ($im['endereco']) { $x->startElement('Address'); $x->writeCdata($im['endereco']); $x->endElement(); }
        if ($im['numero']) $x->writeElement('StreetNumber', $im['numero']);
        if ($im['complemento']) { $x->startElement('Complement'); $x->writeCdata($im['complemento']); $x->endElement(); }
        $x->writeElement('PostalCode', (string)$im['cep']);
        if ($im['latitude'] !== null && $im['longitude'] !== null) {
            $x->writeElement('Latitude', (string)$im['latitude']);
            $x->writeElement('Longitude', (string)$im['longitude']);
        }
        $x->endElement(); // Location

        $x->startElement('ContactInfo');
        $x->writeElement('Name', (string)config('site_nome'));
        $x->writeElement('Email', (string)config('contato.email'));
        $x->writeElement('Website', $site);
        $x->writeElement('OfficeName', (string)config('site_nome'));
        $x->writeElement('Telephone', formata_telefone((string)config('contato.whatsapp_principal')));
        $x->endElement();

        $x->endElement(); // Listing
    }
    $x->endElement(); // Listings
    $x->endElement(); // ListingDataFeed
    $x->endDocument();
    return $x->outputMemory();
}
