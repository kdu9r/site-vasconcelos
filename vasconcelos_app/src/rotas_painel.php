<?php
declare(strict_types=1);

function rotear_painel(string $path, string $method): void
{
    $post = $method === 'POST';
    if ($post) {
        csrf_verify();
    }

    // Rotas sem login
    if ($path === '/painel/entrar') {
        painel_entrar($post);
        return;
    }
    if ($path === '/painel/verificar') {
        painel_verificar($post);
        return;
    }
    if ($path === '/painel/primeiro-acesso') {
        painel_primeiro_acesso($post);
        return;
    }

    $user = require_login();

    switch (true) {
        case $path === '/painel':
            painel_inicio($user);
            return;
        case $path === '/painel/sair' && $post:
            logout();
            redirect('/painel/entrar');
        case $path === '/painel/imovel/novo':
            painel_form_imovel($user, null, $post);
            return;
        case (bool)preg_match('~^/painel/imovel/(\d+)$~', $path, $m):
            painel_form_imovel($user, (int)$m[1], $post);
            return;
        case $post && (bool)preg_match('~^/painel/imovel/(\d+)/fotos/(\d+)/ocultar$~', $path, $m):
            $oculta = alternar_foto_oculta((int)$m[2], (int)$m[1]);
            if ($oculta === null) json_response(['erro' => 'Foto não encontrada.'], 404);
            log_atividade($oculta ? 'escondeu foto do condomínio' : 'mostrou foto do condomínio', 'imóvel ' . $m[1]);
            json_response(['ok' => true, 'oculta' => $oculta]);
        case $post && (bool)preg_match('~^/painel/imovel/(\d+)/fotos$~', $path, $m):
            painel_upload_foto((int)$m[1]);
            return;
        case $post && (bool)preg_match('~^/painel/imovel/(\d+)/fotos/ordem$~', $path, $m):
            painel_ordem_fotos((int)$m[1]);
            return;
        case $post && (bool)preg_match('~^/painel/imovel/(\d+)/fotos/(\d+)/excluir$~', $path, $m):
            painel_excluir_foto((int)$m[1], (int)$m[2]);
            return;
        case $post && (bool)preg_match('~^/painel/imovel/(\d+)/status$~', $path, $m):
            painel_status((int)$m[1]);
            return;
        case $post && (bool)preg_match('~^/painel/imovel/(\d+)/excluir$~', $path, $m):
            painel_excluir_imovel((int)$m[1]);
            return;
        case $path === '/painel/condominios':
            painel_view('condominios_lista', ['titulo_pagina' => 'Condomínios', 'condominios' => condominios_todos()]);
            return;
        case $path === '/painel/condominios/novo':
            painel_condominio_form(null, $post);
            return;
        case (bool)preg_match('~^/painel/condominios/(\d+)$~', $path, $m):
            painel_condominio_form((int)$m[1], $post);
            return;
        case $post && (bool)preg_match('~^/painel/condominios/(\d+)/fotos$~', $path, $m):
            painel_condominio_foto((int)$m[1]);
            return;
        case $post && (bool)preg_match('~^/painel/condominios/(\d+)/fotos/ordem$~', $path, $m):
            reordenar_fotos_condominio((int)$m[1], array_map('intval', (array)($_POST['ids'] ?? [])));
            json_response(['ok' => true]);
        case $post && (bool)preg_match('~^/painel/condominios/(\d+)/fotos/(\d+)/excluir$~', $path, $m):
            json_response(['ok' => excluir_foto_condominio((int)$m[2], (int)$m[1])]);
        case $post && (bool)preg_match('~^/painel/condominios/(\d+)/excluir$~', $path, $m):
            $c = condominio_por_id((int)$m[1]);
            if (!$c) abort(404);
            excluir_condominio((int)$m[1]);
            log_atividade('excluiu condomínio', $c['nome']);
            flash('ok', 'Condomínio excluído. Os imóveis continuam, só sem o vínculo.');
            redirect('/painel/condominios');
        case $path === '/painel/blog':
            painel_blog_lista();
            return;
        case $path === '/painel/blog/novo':
            painel_blog_form($user, null, $post);
            return;
        case (bool)preg_match('~^/painel/blog/(\d+)$~', $path, $m):
            painel_blog_form($user, (int)$m[1], $post);
            return;
        case $post && (bool)preg_match('~^/painel/blog/(\d+)/excluir$~', $path, $m):
            painel_blog_excluir((int)$m[1]);
            return;
        case $path === '/painel/conta':
            painel_conta($user, $post);
            return;
        case $path === '/painel/usuarios':
            painel_usuarios(require_admin(), $post);
            return;
        case $post && $path === '/painel/preencher':
            $texto = mb_substr((string)($_POST['texto'] ?? ''), 0, 12000);
            if (mb_strlen(trim($texto)) < 10) {
                json_response(['erro' => 'Cole o texto do imóvel primeiro.'], 422);
            }
            $conds = q('SELECT id, nome FROM condominios')->fetchAll();
            json_response(['ok' => true] + extrair_imovel_texto($texto, $conds));
        case $path === '/painel/integracoes':
            painel_integracoes(require_admin(), $post);
            return;
        case $path === '/painel/importar':
            painel_importar(require_admin(), $post);
            return;
        default:
            abort(404);
    }
}

function painel_view(string $nome, array $dados = []): void
{
    $dados['usuario'] = current_user();
    view('painel/' . $nome, $dados, 'painel/layout');
}

/* ---------- Login ---------- */

function painel_entrar(bool $post): void
{
    if (current_user()) {
        redirect('/painel');
    }
    $erro = null;
    $email = '';
    if ($post) {
        $email = (string)($_POST['email'] ?? '');
        $r = tentar_login($email, (string)($_POST['senha'] ?? ''));
        if ($r === 'ok') redirect('/painel');
        if ($r === '2fa') redirect('/painel/verificar');
        $erro = $r;
    }
    view('painel/entrar', ['erro' => $erro, 'email' => $email, 'titulo_pagina' => 'Entrar no painel'], 'painel/layout_simples');
}

function painel_verificar(bool $post): void
{
    start_session();
    if (empty($_SESSION['pending_uid'])) {
        redirect('/painel/entrar');
    }
    $erro = null;
    if ($post) {
        $r = verificar_2fa((string)($_POST['codigo'] ?? ''));
        if ($r === 'ok') redirect('/painel');
        if ($r === 'expirado') {
            flash('aviso', 'Tempo esgotado. Entre de novo.');
            redirect('/painel/entrar');
        }
        $erro = $r;
    }
    view('painel/verificar', ['erro' => $erro, 'titulo_pagina' => 'Verificação em duas etapas'], 'painel/layout_simples');
}

/**
 * Cria o primeiro administrador. Só funciona enquanto não existe nenhum usuário
 * E com o código "setup_token" do config.php no endereço (?token=...).
 */
function painel_primeiro_acesso(bool $post): void
{
    $token = (string)config('setup_token', '');
    $enviado = (string)($_GET['token'] ?? $_POST['token'] ?? '');
    $existe = (int)q('SELECT COUNT(*) FROM usuarios')->fetchColumn() > 0;
    if ($existe || strlen($token) < 20 || !hash_equals($token, $enviado)) {
        abort(404);
    }
    $erro = null;
    if ($post) {
        $nome = trim(strip_tags((string)($_POST['nome'] ?? '')));
        $email = mb_strtolower(trim((string)($_POST['email'] ?? '')));
        $senha = (string)($_POST['senha'] ?? '');
        $erro = ($nome === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) ? 'Informe nome e e-mail válidos.' : senha_valida($senha);
        if (!$erro) {
            q('INSERT INTO usuarios (nome, email, senha_hash, papel) VALUES (?,?,?,?)', [$nome, $email, hash_senha($senha), 'admin']);
            flash('ok', 'Administrador criado. Entre com seu e-mail e senha. Depois apague o setup_token do config.php.');
            redirect('/painel/entrar');
        }
    }
    view('painel/primeiro_acesso', ['erro' => $erro, 'token' => $enviado, 'titulo_pagina' => 'Primeiro acesso'], 'painel/layout_simples');
}

/* ---------- Lista ---------- */

function painel_inicio(array $user): void
{
    $busca = trim((string)($_GET['q'] ?? ''));
    $status = in_array($_GET['status'] ?? '', ['ativo', 'pausado', 'vendido', 'alugado'], true) ? $_GET['status'] : '';
    $where = [];
    $p = [];
    if ($busca !== '') {
        $where[] = '(i.codigo = ? OR i.titulo LIKE ? OR i.bairro LIKE ?)';
        array_push($p, $busca, "%$busca%", "%$busca%");
    }
    if ($status) {
        $where[] = 'i.status = ?';
        $p[] = $status;
    }
    $sqlWhere = $where ? 'WHERE ' . implode(' AND ', $where) : '';
    $imoveis = q("SELECT " . IMOVEL_COLUNAS_LISTA . ", i.destaque_portal, i.publicar_portais,
            (SELECT COUNT(*) FROM fotos f2 WHERE f2.imovel_id = i.id AND f2.oculta = 0) AS qtd_fotos,
            (SELECT COUNT(*) FROM cliques_whatsapp c WHERE c.imovel_id = i.id AND c.criado_em > NOW() - INTERVAL 30 DAY) AS cliques30
        FROM imoveis i $sqlWhere ORDER BY i.atualizado_em DESC LIMIT 300", $p)->fetchAll();
    $portalIds = array_map('intval', array_column(imoveis_para_portal(), 'id'));
    $stats = [
        'ativos' => (int)q("SELECT COUNT(*) FROM imoveis WHERE status = 'ativo'")->fetchColumn(),
        'portal' => count($portalIds),
        'cliques7' => (int)q("SELECT COUNT(*) FROM cliques_whatsapp WHERE criado_em > NOW() - INTERVAL 7 DAY")->fetchColumn(),
        'cliques30' => (int)q("SELECT COUNT(*) FROM cliques_whatsapp WHERE criado_em > NOW() - INTERVAL 30 DAY")->fetchColumn(),
    ];
    painel_view('inicio', [
        'titulo_pagina' => 'Imóveis',
        'imoveis' => anexar_capas($imoveis),
        'busca' => $busca,
        'status' => $status,
        'stats' => $stats,
        'portal_ids' => array_flip($portalIds),
    ]);
}

/* ---------- Formulário ---------- */

function painel_form_imovel(array $user, ?int $id, bool $post): void
{
    $im = $id ? imovel_por_id($id) : null;
    if ($id && !$im) {
        abort(404);
    }
    $erros = [];
    $valores = $im ?? [
        // imóvel novo: no ar e em destaque no site; CanalPro só quando o corretor ligar
        'codigo' => proximo_codigo(), 'status' => 'ativo', 'cidade' => 'Bertioga', 'uf' => 'SP', 'cep' => config('contato.cep_padrao'),
        'exibir_endereco' => 'bairro', 'destaque_portal' => 'padrao', 'publicar_portais' => 0, 'destaque_site' => 1, 'iptu_periodo' => 'mensal',
        'corretor_id' => $user['id'],
    ];

    if ($post) {
        [$dados, $erros] = validar_imovel($_POST, $id);
        [$condAcao, $condDados, $condErros] = validar_condominio_do_imovel($_POST, $dados);
        $erros += $condErros;
        if (!$erros) {
            $dados['condominio_id'] = aplicar_condominio_do_imovel($condAcao, $condDados);
            $novoId = salvar_imovel($dados, $id);
            sincronizar_fotos_condominio($novoId);
            log_atividade($id ? 'editou imóvel' : 'criou imóvel', 'Cód. ' . $dados['codigo']);
            flash('ok', $id ? 'Alterações salvas.' : 'Imóvel criado. Agora adicione as fotos.');
            redirect('/painel/imovel/' . $novoId . ($id ? '' : '#fotos'));
        }
        $valores = array_merge($valores, $_POST, ['caracteristicas' => $dados['caracteristicas'] ?? null, '_digitado' => true]);
        $valores['negocio'] = (array)($_POST['negocio'] ?? []);
    }

    $condominios = q('SELECT c.*, (SELECT COUNT(*) FROM condominio_fotos f WHERE f.condominio_id = c.id) AS qtd_fotos FROM condominios c ORDER BY nome')->fetchAll();
    foreach ($condominios as &$c) {
        $c['itens'] = separar_itens($c['lazer'], itens_do_condominio())[0];
        $c['miniaturas'] = array_map(fn($f) => foto_url($f, 'p'), array_slice(fotos_condominio((int)$c['id']), 0, 8));
    }
    unset($c);

    $fotos = $im ? fotos_do_imovel((int)$im['id']) : [];
    painel_view('imovel_form', [
        'titulo_pagina' => $im ? 'Cód. ' . $im['codigo'] : 'Novo imóvel',
        'imovel' => $im,
        'v' => $valores,
        'erros' => $erros,
        'fotos' => $fotos,
        'pendencias' => $im ? pendencias_portal($im, count(fotos_completas($im))) : [],
        'condominios' => $condominios,
        'corretores' => q('SELECT id, nome FROM usuarios WHERE ativo = 1 ORDER BY nome')->fetchAll(),
        'bairros' => q('SELECT DISTINCT bairro FROM imoveis ORDER BY bairro')->fetchAll(PDO::FETCH_COLUMN),
    ]);
}

/**
 * Bloco "Condomínio" do cadastro do imóvel: nenhum, escolher um existente ou cadastrar um novo ali mesmo.
 * @return array{0: string, 1: array, 2: array} [ação, dados, erros]
 */
function validar_condominio_do_imovel(array $in, array $imovel): array
{
    if (empty($in['tem_condominio'])) {
        return ['nenhum', [], []];
    }
    $itens = array_map('strval', (array)($in['cond_itens'] ?? []));
    if (($in['cond_modo'] ?? 'existente') === 'novo') {
        [$d, $erros] = validar_condominio([
            'nome' => $in['cond_nome'] ?? '',
            'nome_publico' => $in['cond_nome_publico'] ?? '',
            'lazer_itens' => $itens,
            'lazer_outros' => '',
            'endereco' => $imovel['endereco'] ?? '', 'numero' => $imovel['numero'] ?? '', 'bairro' => $imovel['bairro'] ?? '',
            'cidade' => $imovel['cidade'] ?? '', 'cep' => $imovel['cep'] ?? '', 'latitude' => $imovel['latitude'] ?? '', 'longitude' => $imovel['longitude'] ?? '',
        ]);
        if (isset($erros['nome'])) {
            $erros = ['cond_nome' => $erros['nome'] . (str_starts_with($erros['nome'], 'Já existe') ? ' Use "Selecionar".' : '')];
        }
        return ['novo', $d, $erros];
    }
    $c = condominio_por_id(parse_int($in['condominio_id'] ?? null));
    if (!$c) {
        return ['nenhum', [], ['condominio_id' => 'Escolha o condomínio na lista ou cadastre um novo.']];
    }
    // itens marcados aqui atualizam o condomínio (valem para todos os imóveis dele); itens "outros" são mantidos
    $outros = separar_itens($c['lazer'], itens_do_condominio())[1];
    return ['existente', ['id' => (int)$c['id'], 'lazer' => juntar_itens($itens, $outros, itens_do_condominio()), 'lazer_antigo' => $c['lazer']], []];
}

function aplicar_condominio_do_imovel(string $acao, array $d): ?int
{
    if ($acao === 'novo') {
        $id = salvar_condominio($d);
        log_atividade('criou condomínio', $d['nome']);
        return $id;
    }
    if ($acao === 'existente') {
        if ($d['lazer'] !== $d['lazer_antigo']) {
            q('UPDATE condominios SET lazer = ? WHERE id = ?', [$d['lazer'], $d['id']]);
        }
        return $d['id'];
    }
    return null;
}

function painel_upload_foto(int $id): void
{
    if (!imovel_por_id($id)) {
        json_response(['erro' => 'Imóvel não encontrado.'], 404);
    }
    try {
        $f = adicionar_foto_upload($id, $_FILES['foto'] ?? []);
        log_atividade('enviou foto', 'imóvel ' . $id);
        json_response(['ok' => true, 'foto' => [
            'id' => $f['id'], 'url' => foto_url($f, 'p'), 'em_pe' => foto_em_pe($f),
        ]]);
    } catch (Throwable $e) {
        json_response(['erro' => $e instanceof RuntimeException ? $e->getMessage() : 'Erro ao processar a foto.'], 422);
    }
}

function painel_ordem_fotos(int $id): void
{
    $ids = array_map('intval', (array)($_POST['ids'] ?? []));
    reordenar_fotos($id, $ids);
    json_response(['ok' => true]);
}

function painel_excluir_foto(int $id, int $fotoId): void
{
    $ok = excluir_foto($fotoId, $id);
    if ($ok) log_atividade('excluiu foto', 'imóvel ' . $id);
    json_response(['ok' => $ok]);
}

function painel_status(int $id): void
{
    $novo = (string)($_POST['status'] ?? '');
    if (!in_array($novo, ['ativo', 'pausado', 'vendido', 'alugado'], true) || !imovel_por_id($id)) {
        abort(400);
    }
    q('UPDATE imoveis SET status = ? WHERE id = ?', [$novo, $id]);
    log_atividade('mudou status', "imóvel $id → $novo");
    flash('ok', 'Situação atualizada.');
    redirect((string)($_POST['voltar'] ?? '') === 'form' ? '/painel/imovel/' . $id : '/painel');
}

function painel_excluir_imovel(int $id): void
{
    $im = imovel_por_id($id);
    if (!$im) abort(404);
    if (($_POST['confirmar'] ?? '') !== (string)$im['codigo']) {
        flash('erro', 'Para excluir, digite o código do imóvel.');
        redirect('/painel/imovel/' . $id);
    }
    excluir_imovel($id);
    log_atividade('excluiu imóvel', 'Cód. ' . $im['codigo']);
    flash('ok', 'Imóvel Cód. ' . $im['codigo'] . ' excluído.');
    redirect('/painel');
}

/* ---------- Minha conta (senha e 2 etapas) ---------- */

function painel_conta(array $user, bool $post): void
{
    start_session();
    $erro = null;
    if ($post) {
        $acao = $_POST['acao'] ?? '';
        $atual = q('SELECT senha_hash, totp_secret FROM usuarios WHERE id = ?', [$user['id']])->fetch();
        if (!password_verify((string)($_POST['senha_atual'] ?? ''), $atual['senha_hash'])) {
            $erro = 'Senha atual incorreta.';
        } elseif ($acao === 'senha') {
            $nova = (string)($_POST['nova_senha'] ?? '');
            $erro = senha_valida($nova) ?? ($nova !== ($_POST['confirmar'] ?? '') ? 'As senhas não conferem.' : null);
            if (!$erro) {
                q('UPDATE usuarios SET senha_hash = ? WHERE id = ?', [hash_senha($nova), $user['id']]);
                log_atividade('trocou senha');
                session_regenerate_id(true);
                flash('ok', 'Senha alterada.');
                redirect('/painel/conta');
            }
        } elseif ($acao === 'ativar_2fa') {
            $secret = (string)($_SESSION['totp_novo'] ?? '');
            if ($secret && totp_verify($secret, (string)($_POST['codigo'] ?? ''))) {
                q('UPDATE usuarios SET totp_secret = ?, totp_ativo = 1 WHERE id = ?', [$secret, $user['id']]);
                unset($_SESSION['totp_novo']);
                log_atividade('ativou 2 etapas');
                flash('ok', 'Verificação em duas etapas ativada.');
                redirect('/painel/conta');
            }
            $erro = 'Código incorreto. Confira o horário do celular e tente de novo.';
        } elseif ($acao === 'desativar_2fa') {
            q('UPDATE usuarios SET totp_secret = NULL, totp_ativo = 0 WHERE id = ?', [$user['id']]);
            log_atividade('desativou 2 etapas');
            flash('aviso', 'Verificação em duas etapas desativada.');
            redirect('/painel/conta');
        }
    }
    if (!$user['totp_ativo'] && empty($_SESSION['totp_novo'])) {
        $_SESSION['totp_novo'] = totp_new_secret();
    }
    painel_view('conta', [
        'titulo_pagina' => 'Minha conta',
        'erro' => $erro,
        'totp_secret' => $_SESSION['totp_novo'] ?? null,
        'totp_uri' => isset($_SESSION['totp_novo']) ? totp_uri($_SESSION['totp_novo'], $user['email']) : null,
    ]);
}

/* ---------- Usuários (só admin) ---------- */

function painel_usuarios(array $admin, bool $post): void
{
    $erro = null;
    $senhaGerada = null;
    if ($post) {
        $acao = $_POST['acao'] ?? '';
        if ($acao === 'criar') {
            $nome = trim(strip_tags((string)($_POST['nome'] ?? '')));
            $email = mb_strtolower(trim((string)($_POST['email'] ?? '')));
            $papel = ($_POST['papel'] ?? '') === 'admin' ? 'admin' : 'corretor';
            if ($nome === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $erro = 'Informe nome e um e-mail válido.';
            } elseif (q('SELECT id FROM usuarios WHERE email = ?', [$email])->fetchColumn()) {
                $erro = 'Esse e-mail já tem acesso.';
            } else {
                $senhaGerada = gerar_senha_temporaria();
                q('INSERT INTO usuarios (nome, email, senha_hash, papel, creci) VALUES (?,?,?,?,?)',
                    [$nome, $email, hash_senha($senhaGerada), $papel, trim((string)($_POST['creci'] ?? '')) ?: null]);
                log_atividade('criou usuário', $email);
            }
        } elseif (in_array($acao, ['redefinir', 'desativar', 'ativar', 'zerar_2fa'], true)) {
            $uid = (int)($_POST['id'] ?? 0);
            if ($uid === (int)$admin['id'] && $acao !== 'redefinir') {
                $erro = 'Você não pode fazer isso com a sua própria conta.';
            } elseif ($acao === 'redefinir') {
                $senhaGerada = gerar_senha_temporaria();
                q('UPDATE usuarios SET senha_hash = ? WHERE id = ?', [hash_senha($senhaGerada), $uid]);
                log_atividade('redefiniu senha', 'usuário ' . $uid);
            } elseif ($acao === 'zerar_2fa') {
                q('UPDATE usuarios SET totp_secret = NULL, totp_ativo = 0 WHERE id = ?', [$uid]);
                log_atividade('zerou 2 etapas', 'usuário ' . $uid);
                flash('ok', 'Verificação em duas etapas removida desse usuário.');
                redirect('/painel/usuarios');
            } else {
                q('UPDATE usuarios SET ativo = ? WHERE id = ?', [$acao === 'ativar' ? 1 : 0, $uid]);
                log_atividade($acao === 'ativar' ? 'reativou usuário' : 'desativou usuário', 'usuário ' . $uid);
                flash('ok', 'Acesso ' . ($acao === 'ativar' ? 'reativado.' : 'bloqueado.'));
                redirect('/painel/usuarios');
            }
        }
    }
    painel_view('usuarios', [
        'titulo_pagina' => 'Acessos',
        'usuarios' => q('SELECT id, nome, email, papel, creci, ativo, totp_ativo, ultimo_login FROM usuarios ORDER BY nome')->fetchAll(),
        'atividades' => q('SELECT a.*, u.nome FROM registro_atividades a LEFT JOIN usuarios u ON u.id = a.usuario_id ORDER BY a.id DESC LIMIT 40')->fetchAll(),
        'erro' => $erro,
        'senha_gerada' => $senhaGerada,
    ]);
}

function gerar_senha_temporaria(): string
{
    $chars = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $s = '';
    for ($i = 0; $i < 14; $i++) {
        $s .= $chars[random_int(0, strlen($chars) - 1)];
    }
    return $s . random_int(10, 99);
}

/* ---------- Importação da Code49 (só admin) ---------- */

function painel_importar(array $admin, bool $post): void
{
    if ($post) {
        @set_time_limit(300);
        ignore_user_abort(true); // se fechar a aba no meio, o imóvel atual termina de importar
        $acao = $_POST['acao'] ?? '';
        if ($acao === 'ligar_portais') {
            $n = q("UPDATE imoveis SET publicar_portais = 1 WHERE status = 'ativo' AND publicar_portais = 0")->rowCount();
            log_atividade('ligou CanalPro em massa', "$n imóveis");
            flash('ok', "CanalPro ligado em $n imóveis no ar. Só entram no feed os que estão completos (5 fotos, CEP, área…).");
            redirect('/painel/importar');
        }
        try {
            if ($acao === 'listar') {
                $total = code49_montar_fila();
                log_atividade('migração: fila montada', "$total imóveis");
                json_response(['ok' => true, 'total' => $total]);
            }
            if ($acao === 'proximo') {
                json_response(['ok' => true] + code49_processar_proximo());
            }
        } catch (Code49LimiteException $e) {
            json_response(['ok' => false, 'esperar' => $e->esperarSegundos, 'erro' => $e->getMessage()], 429);
        } catch (Throwable $e) {
            json_response(['ok' => false, 'erro' => $e->getMessage()], 500);
        }
        abort(400);
    }
    painel_view('importar', [
        'titulo_pagina' => 'Migração da Code49',
        'estado' => code49_estado_fila(),
        'token_configurado' => (string)config('code49.token') !== '',
    ]);
}

/* ---------- Blog ---------- */

function painel_blog_lista(): void
{
    painel_view('blog_lista', [
        'titulo_pagina' => 'Blog',
        'posts' => q('SELECT p.*, u.nome AS autor_nome FROM posts p LEFT JOIN usuarios u ON u.id = p.autor_id ORDER BY COALESCE(p.publicado_em, p.criado_em) DESC LIMIT 200')->fetchAll(),
    ]);
}

function painel_blog_form(array $user, ?int $id, bool $post): void
{
    $p = $id ? post_por_id($id) : null;
    if ($id && !$p) {
        abort(404);
    }
    $erros = [];
    $v = $p ?? ['status' => 'publicado', 'publicado_em' => date('Y-m-d H:i:s')];
    if ($post) {
        [$dados, $erros] = validar_post($_POST, $id);
        if (!$erros) {
            if (!$id) {
                $dados['autor_id'] = $user['id'];
            }
            $novoId = salvar_post($dados, $id);
            try {
                salvar_capa_post($novoId, $_FILES['capa'] ?? []);
            } catch (Throwable $e) {
                flash('erro', 'Texto salvo, mas a imagem não foi aceita: ' . ($e instanceof RuntimeException ? $e->getMessage() : 'erro ao processar.'));
            }
            log_atividade($id ? 'editou post' : 'criou post', $dados['titulo']);
            flash('ok', $dados['status'] === 'publicado' ? 'Post publicado no site.' : 'Rascunho salvo. Ele ainda não aparece no site.');
            redirect('/painel/blog/' . $novoId);
        }
        $v = array_merge($v, $_POST);
    }
    painel_view('blog_form', [
        'titulo_pagina' => $p ? 'Editar post' : 'Novo post',
        'p' => $p,
        'v' => $v,
        'erros' => $erros,
        'bairros' => q('SELECT DISTINCT bairro FROM imoveis ORDER BY bairro')->fetchAll(PDO::FETCH_COLUMN),
    ]);
}

function painel_blog_excluir(int $id): void
{
    $p = post_por_id($id);
    if (!$p) abort(404);
    excluir_post($id);
    log_atividade('excluiu post', $p['titulo']);
    flash('ok', 'Post excluído.');
    redirect('/painel/blog');
}

/* ---------- Condomínios ---------- */

function painel_condominio_form(?int $id, bool $post): void
{
    $c = condominio_por_id($id);
    if ($id && !$c) {
        abort(404);
    }
    $erros = [];
    $v = $c ?? ['cidade' => 'Bertioga'];
    if ($post) {
        [$dados, $erros] = validar_condominio($_POST, $id);
        if (!$erros) {
            $novoId = salvar_condominio($dados, $id);
            log_atividade($id ? 'editou condomínio' : 'criou condomínio', $dados['nome']);
            flash('ok', $id ? 'Condomínio salvo.' : 'Condomínio criado. Agora adicione as fotos das áreas comuns.');
            redirect('/painel/condominios/' . $novoId . ($id ? '' : '#fotos'));
        }
        $v = array_merge($v, $_POST);
    }
    painel_view('condominio_form', [
        'titulo_pagina' => $c ? $c['nome'] : 'Novo condomínio',
        'c' => $c,
        'v' => $v,
        'erros' => $erros,
        'fotos' => $c ? fotos_condominio((int)$c['id']) : [],
        'imoveis' => $c ? q('SELECT id, codigo, titulo, status FROM imoveis WHERE condominio_id = ? ORDER BY codigo', [$c['id']])->fetchAll() : [],
        'bairros' => q('SELECT DISTINCT bairro FROM imoveis ORDER BY bairro')->fetchAll(PDO::FETCH_COLUMN),
    ]);
}

function painel_condominio_foto(int $id): void
{
    if (!condominio_por_id($id)) {
        json_response(['erro' => 'Condomínio não encontrado.'], 404);
    }
    try {
        $f = adicionar_foto_condominio($id, $_FILES['foto'] ?? []);
        json_response(['ok' => true, 'foto' => ['id' => $f['id'], 'url' => foto_url($f, 'p'), 'em_pe' => foto_em_pe($f)]]);
    } catch (Throwable $e) {
        json_response(['erro' => $e instanceof RuntimeException ? $e->getMessage() : 'Erro ao processar a foto.'], 422);
    }
}

/* ---------- Integrações (Google / Meta) ---------- */

function painel_integracoes(array $admin, bool $post): void
{
    $valores = config_site();
    $erros = [];
    if ($post) {
        [$d, $erros] = validar_integracoes($_POST);
        if (!$erros) {
            salvar_config_site($d);
            log_atividade('alterou integrações', implode(', ', array_keys(array_filter($d, fn($v) => $v !== '' && $v !== '0'))));
            flash('ok', 'Integrações salvas. Já valem para o site.');
            redirect('/painel/integracoes');
        }
        $valores = $d;
    }
    painel_view('integracoes', [
        'titulo_pagina' => 'Integrações',
        'campos' => integracoes_campos(),
        'valores' => $valores,
        'erros' => $erros,
    ]);
}
