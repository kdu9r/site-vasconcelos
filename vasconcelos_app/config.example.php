<?php
/**
 * Copie este arquivo para "config.php" (mesma pasta) e preencha.
 * NUNCA coloque o config.php dentro de public_html.
 */
return [
    // Endereço público do site, sem barra no final
    'site_url' => 'https://www.vasconceloscorretor.com',
    'site_nome' => 'Vasconcelos Corretor',

    // Banco MySQL (cPanel > Bancos de dados MySQL)
    'db' => [
        'host' => 'localhost',
        'nome' => 'usuario_vasconcelos',
        'usuario' => 'usuario_site',
        'senha' => 'TROQUE-POR-UMA-SENHA-FORTE',
    ],

    // Segredo aleatório (64 caracteres). Gere com: php -r "echo bin2hex(random_bytes(32));"
    'app_secret' => 'TROQUE-ESTE-VALOR',

    // Contato exibido no site e enviado ao CanalPro
    'contato' => [
        'email' => 'contato@vasconceloscorretor.com',
        'endereco' => 'Centro, Bertioga · SP',
        'cep_padrao' => '11250-000',
        'corretores' => [
            ['nome' => 'Fred Vasconcelos', 'creci' => 'CRECI 230912', 'whatsapp' => '5513982244361', 'iniciais' => 'FV'],
            ['nome' => 'Priscila Santos',  'creci' => 'CRECI 275997', 'whatsapp' => '5513996364922', 'iniciais' => 'PS'],
        ],
        // Número que recebe as mensagens dos botões do site
        'whatsapp_principal' => '5513982244361',
    ],

    // API da Code49 (somente para a migração; apague depois de migrar)
    'code49' => [
        'base_url' => 'https://www.vasconceloscorretor.com/crm/api/v2',
        'token' => '',
    ],

    // Caminho absoluto da pasta public_html (para salvar fotos)
    'public_dir' => dirname(__DIR__) . '/public_html',

    // Código para criar o primeiro administrador em /painel/primeiro-acesso?token=...
    // Gere um valor aleatório longo e APAGUE depois de criar o administrador.
    'setup_token' => '',

    // Coloque true só enquanto estiver testando
    'debug' => false,
    // Subdomínio de demonstração: true = Google não indexa e o feed do CanalPro fica desligado.
    // Na instalação definitiva (vasconceloscorretor.com), deixe false.
    'modo_teste' => false,
];
