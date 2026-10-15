<?php
declare(strict_types=1);

/*
 * Integrações de marketing (Google Tag Manager, Analytics, Ads, Search Console, Pixel da Meta).
 * O painel guarda só os IDs, validados por formato. Nenhum código colado é executado,
 * então um ID errado ou malicioso não consegue injetar script no site.
 */

function integracoes_campos(): array
{
    return [
        'gtm_id' => [
            'rotulo' => 'Google Tag Manager',
            'exemplo' => 'GTM-ABC1234',
            'regex' => '/^GTM-[A-Z0-9]{4,12}$/',
            'ajuda' => 'tagmanager.google.com → no topo, ao lado do nome do contêiner.',
        ],
        'ga4_id' => [
            'rotulo' => 'Google Analytics 4',
            'exemplo' => 'G-AB12CD34EF',
            'regex' => '/^G-[A-Z0-9]{6,14}$/',
            'ajuda' => 'analytics.google.com → Administrador → Fluxos de dados → ID da métrica.',
        ],
        'ads_id' => [
            'rotulo' => 'Google Ads (ID da conta)',
            'exemplo' => 'AW-123456789',
            'regex' => '/^AW-\d{6,14}$/',
            'ajuda' => 'Google Ads → Metas → Conversões → Tag do Google.',
        ],
        'ads_rotulo_whatsapp' => [
            'rotulo' => 'Google Ads (rótulo da conversão WhatsApp)',
            'exemplo' => 'AbC-D_efGhIjK',
            'regex' => '/^[A-Za-z0-9_-]{6,40}$/',
            'ajuda' => 'Crie uma conversão "Contato" e copie o que vem depois da barra em send_to: AW-123/<strong>este trecho</strong>.',
        ],
        'meta_pixel' => [
            'rotulo' => 'Pixel da Meta (Instagram e Facebook)',
            'exemplo' => '123456789012345',
            'regex' => '/^\d{10,20}$/',
            'ajuda' => 'business.facebook.com → Gerenciador de Eventos → Fontes de dados → ID do pixel.',
        ],
        'meta_dominio' => [
            'rotulo' => 'Verificação de domínio da Meta',
            'exemplo' => 'abc123def456ghi789jkl012mno345',
            'regex' => '/^[a-z0-9]{20,40}$/',
            'ajuda' => 'Configurações do negócio → Segurança da marca → Domínios → "Marcação de meta-tag". Copie só o valor de content.',
        ],
        'gsc_verificacao' => [
            'rotulo' => 'Verificação do Google Search Console',
            'exemplo' => 'aBcD1234_efGh5678-ijKL...',
            'regex' => '/^[A-Za-z0-9_-]{20,100}$/',
            'ajuda' => 'search.google.com/search-console → Adicionar propriedade → Prefixo do URL → Tag HTML. Copie só o valor de content.',
        ],
    ];
}

/** Lê uma configuração salva no banco. Nunca derruba o site se a tabela não existir. */
function config_site(?string $chave = null): mixed
{
    static $cache = null;
    if ($cache === null) {
        try {
            $cache = q('SELECT chave, valor FROM configuracoes')->fetchAll(PDO::FETCH_KEY_PAIR) ?: [];
        } catch (Throwable $e) {
            $cache = [];
        }
    }
    return $chave === null ? $cache : ($cache[$chave] ?? null);
}

function salvar_config_site(array $valores): void
{
    foreach ($valores as $chave => $valor) {
        if ($valor === null || $valor === '') {
            q('DELETE FROM configuracoes WHERE chave = ?', [$chave]);
        } else {
            q('INSERT INTO configuracoes (chave, valor) VALUES (?, ?) ON DUPLICATE KEY UPDATE valor = VALUES(valor)', [$chave, (string)$valor]);
        }
    }
}

/** @return array{0: array, 1: array} */
function validar_integracoes(array $in): array
{
    $d = [];
    $erros = [];
    foreach (integracoes_campos() as $chave => $c) {
        $v = trim((string)($in[$chave] ?? ''));
        // aceita colar a meta-tag inteira e extrai só o valor de content
        if (preg_match('/content=["\']([^"\']+)["\']/', $v, $m)) {
            $v = $m[1];
        }
        if (in_array($chave, ['gtm_id', 'ga4_id', 'ads_id'], true)) {
            $v = strtoupper($v);
        }
        if ($v !== '' && !preg_match($c['regex'], $v)) {
            $erros[$chave] = 'Formato não reconhecido. Exemplo: ' . $c['exemplo'];
        }
        $d[$chave] = $v;
    }
    if ($d['ads_rotulo_whatsapp'] !== '' && $d['ads_id'] === '') {
        $erros['ads_id'] = 'Preencha o ID da conta do Google Ads para usar o rótulo de conversão.';
    }
    $d['pedir_consentimento'] = !empty($in['pedir_consentimento']) ? '1' : '0';
    return [$d, $erros];
}

/** IDs de rastreamento ativos (só os que carregam script). */
function rastreadores_ativos(): array
{
    $ativos = [];
    foreach (['gtm_id', 'ga4_id', 'ads_id', 'meta_pixel'] as $k) {
        $v = config_site($k);
        if ($v && preg_match(integracoes_campos()[$k]['regex'], (string)$v)) {
            $ativos[$k] = (string)$v;
        }
    }
    return $ativos;
}

function usa_rastreamento(): bool
{
    static $r = null;
    return $r ??= (bool)rastreadores_ativos();
}

/** Visitas do Fred, da Priscila e de quem estiver logado não entram nas estatísticas. */
function visitante_e_corretor(): bool
{
    return tem_cookie_sessao() && current_user() !== null;
}

/** Domínios extras liberados na CSP quando há rastreamento configurado. */
function integracoes_csp(): array
{
    if (!usa_rastreamento()) {
        return [];
    }
    $google = 'https://www.googletagmanager.com https://*.googletagmanager.com https://*.google-analytics.com https://*.analytics.google.com https://*.g.doubleclick.net https://www.googleadservices.com https://www.google.com https://www.google.com.br';
    $meta = 'https://connect.facebook.net https://www.facebook.com';
    return [
        'script' => "'strict-dynamic' $google $meta",
        'img' => "$google $meta",
        'connect' => "$google $meta",
        'frame' => 'https://www.googletagmanager.com https://td.doubleclick.net https://www.facebook.com',
    ];
}

/** Metatags de verificação (não dependem de consentimento). */
function integracoes_meta_tags(): string
{
    $html = '';
    if ($v = config_site('gsc_verificacao')) {
        $html .= '<meta name="google-site-verification" content="' . e((string)$v) . '">' . "\n";
    }
    if ($v = config_site('meta_dominio')) {
        $html .= '<meta name="facebook-domain-verification" content="' . e((string)$v) . '">' . "\n";
    }
    return $html;
}

/** Configuração lida pelo site.js, que só carrega os scripts depois do aceite (quando exigido). */
function integracoes_config_json(?array $imovel = null): string
{
    $ativos = rastreadores_ativos();
    if (!$ativos || visitante_e_corretor()) {
        return '';
    }
    $cfg = $ativos + [
        'consentimento' => config_site('pedir_consentimento') !== '0',
    ];
    if ($r = config_site('ads_rotulo_whatsapp')) {
        $cfg['ads_rotulo_whatsapp'] = (string)$r;
    }
    if ($imovel) {
        $cfg['imovel'] = ['codigo' => (string)$imovel['codigo'], 'valor' => (float)($imovel['preco_venda'] ?: $imovel['preco_locacao'] ?: 0)];
    }
    return '<script type="application/json" id="rastreio-config" nonce="' . e(csp_nonce()) . '">'
        . json_encode($cfg, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) . '</script>' . "\n";
}
