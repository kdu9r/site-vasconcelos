<?php
/** @var ?array $p @var array $v @var array $erros @var array $bairros */
$erro = fn(string $k) => isset($erros[$k]) ? '<span class="p-erro">' . e($erros[$k]) . '</span>' : '';
$dataValor = !empty($v['publicado_em']) ? date('Y-m-d\TH:i', strtotime((string)$v['publicado_em'])) : '';
?>
<div class="p-cabeca">
  <div>
    <a class="p-voltar" href="/painel/blog">← Blog</a>
    <h1><?= $p ? 'Editar post' : 'Novo post' ?></h1>
  </div>
  <?php if ($p && $p['status'] === 'publicado'): ?><a class="p-botao p-botao--fantasma" href="<?= e(post_url($p)) ?>" target="_blank" rel="noopener">Ver no site ↗</a><?php endif; ?>
</div>
<?php if ($erros): ?><div class="p-aviso p-aviso--erro" role="alert">Confira os campos marcados em vermelho.</div><?php endif; ?>

<form method="post" action="<?= $p ? '/painel/blog/' . (int)$p['id'] : '/painel/blog/novo' ?>" enctype="multipart/form-data" class="p-form-imovel" novalidate>
  <?= csrf_field() ?>
  <section class="p-cartao">
    <label class="p-campo">
      <span>Título <small>Use as palavras que as pessoas buscam. Ex.: "Morar no Maitinga: como é o bairro"</small></span>
      <input name="titulo" value="<?= e($v['titulo'] ?? '') ?>" maxlength="150" required data-contar="titulo">
      <?= $erro('titulo') ?>
    </label>
    <label class="p-campo">
      <span>Resumo <small data-contador-de="resumo">aparece no Google e no WhatsApp (até 220 letras)</small></span>
      <textarea name="resumo" rows="2" maxlength="220" data-contar="resumo"><?= e($v['resumo'] ?? '') ?></textarea>
    </label>
    <label class="p-campo">
      <span>Texto <small data-contador-de="conteudo">mínimo 300 letras para publicar</small></span>
      <textarea name="conteudo" rows="18" data-contar="conteudo" placeholder="Escreva aqui. Deixe uma linha em branco entre os parágrafos."><?= e($v['conteudo'] ?? '') ?></textarea>
      <?= $erro('conteudo') ?>
    </label>
    <details class="p-avancado">
      <summary>Como formatar o texto</summary>
      <ul class="p-passos">
        <li><code>## Subtítulo</code> no começo da linha cria um subtítulo</li>
        <li><code>- item</code> no começo da linha cria uma lista</li>
        <li><code>**palavra**</code> deixa em negrito</li>
        <li><code>[texto do link](/imovel/538)</code> cria um link (pode ser para um imóvel do site)</li>
      </ul>
    </details>
  </section>

  <section class="p-cartao">
    <h2>Imagem e publicação</h2>
    <?php if ($p && ($capa = capa_post_url($p, 'p'))): ?>
      <img src="<?= e($capa) ?>" alt="Capa atual" class="p-capa-atual" width="320" height="213">
    <?php endif; ?>
    <label class="p-campo">
      <span>Imagem de capa <small>de preferência deitada; <?= $p && $p['capa'] ? 'envie outra para trocar' : 'opcional' ?></small></span>
      <input type="file" name="capa" accept="image/jpeg,image/png,image/webp">
    </label>
    <div class="p-grade p-grade--3">
      <label class="p-campo">
        <span>Situação</span>
        <select name="status">
          <option value="rascunho" <?= ($v['status'] ?? '') !== 'publicado' ? 'selected' : '' ?>>Rascunho (não aparece)</option>
          <option value="publicado" <?= ($v['status'] ?? '') === 'publicado' ? 'selected' : '' ?>>Publicado</option>
        </select>
      </label>
      <label class="p-campo">
        <span>Data de publicação <small>uma data futura agenda o post</small></span>
        <input type="datetime-local" name="publicado_em" value="<?= e($dataValor) ?>">
      </label>
      <label class="p-campo">
        <span>Bairro relacionado <small>mostra imóveis dele no fim</small></span>
        <select name="bairro">
          <option value="">Nenhum</option>
          <?php foreach ($bairros as $b): ?><option value="<?= e($b) ?>" <?= ($v['bairro'] ?? '') === $b ? 'selected' : '' ?>><?= e($b) ?></option><?php endforeach; ?>
        </select>
      </label>
    </div>
    <label class="p-campo">
      <span>Endereço do post <small>opcional; gerado a partir do título</small></span>
      <input name="slug" value="<?= e($v['slug'] ?? '') ?>" placeholder="morar-no-maitinga">
    </label>
  </section>

  <div class="p-salvar">
    <button class="p-botao p-botao--primario p-botao--grande" type="submit">Salvar</button>
  </div>
</form>

<?php if ($p): ?>
<details class="p-cartao p-perigo">
  <summary>Excluir este post</summary>
  <form method="post" action="/painel/blog/<?= (int)$p['id'] ?>/excluir" class="p-grade p-grade--3">
    <?= csrf_field() ?>
    <p>O post sai do site e não dá para desfazer.</p>
    <button class="p-botao p-botao--perigo" type="submit">Excluir post</button>
  </form>
</details>
<?php endif; ?>
