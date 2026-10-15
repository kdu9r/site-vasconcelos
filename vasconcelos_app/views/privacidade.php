<section class="secao secao--estreita pagina-texto">
  <h1>Política de privacidade</h1>
<?php if (usa_rastreamento()): ?>
  <p>Este site não usa formulários de cadastro. Com a sua permissão, usamos cookies de medição e publicidade, explicados abaixo.</p>
<?php else: ?>
  <p>Este site não usa formulários de cadastro nem cookies de rastreamento ou publicidade.</p>
<?php endif; ?>
  <h2>O que coletamos</h2>
  <p>Quando você clica em um botão de WhatsApp, registramos apenas qual imóvel originou o contato e a data, sem nenhum dado pessoal, para saber quais anúncios despertam mais interesse. A conversa acontece no próprio WhatsApp, e os dados que você enviar lá são usados só para o seu atendimento.</p>
  <h2>Serviços de terceiros</h2>
  <p>As fontes de texto são carregadas do Google Fonts, os mapas do OpenStreetMap e os vídeos do YouTube (somente quando você clica para assistir). Esses serviços podem registrar seu endereço IP conforme as políticas deles.</p>
<?php if (usa_rastreamento()): ?>
  <h2>Cookies de medição e publicidade</h2>
  <p>Usamos ferramentas do Google (Analytics, Tag Manager e Ads) e da Meta (Pixel do Facebook e Instagram) para contar visitas, entender quais imóveis interessam mais e mostrar nossos anúncios a quem já visitou o site. Esses serviços recebem dados de navegação, como páginas vistas, tipo de aparelho e cliques no WhatsApp, e podem usar cookies próprios.</p>
  <?php if (config_site('pedir_consentimento') !== '0'): ?>
  <p>Eles só são ativados se você clicar em "Aceitar" no aviso de cookies. Você pode mudar sua escolha quando quiser: <button type="button" class="link-botao" data-cookies-abrir>abrir preferências de cookies</button>.</p>
  <?php endif; ?>
<?php endif; ?>
  <h2>Seus direitos (LGPD)</h2>
  <p>Você pode pedir informações, correção ou exclusão de dados pessoais a qualquer momento pelo e-mail <a href="mailto:<?= e(config('contato.email')) ?>"><?= e(config('contato.email')) ?></a>.</p>
</section>
