// Vasconcelos Corretor — interações do site (sem dependências)
(function () {
  'use strict';

  // Menu do celular
  var botaoMenu = document.querySelector('.menu-botao');
  var menu = document.getElementById('menu');
  if (botaoMenu && menu) {
    botaoMenu.addEventListener('click', function () {
      var aberto = menu.classList.toggle('aberto');
      botaoMenu.setAttribute('aria-expanded', aberto ? 'true' : 'false');
      botaoMenu.setAttribute('aria-label', aberto ? 'Fechar menu' : 'Abrir menu');
    });
  }

  // Carrossel do blog (página inicial): do mais recente ao mais antigo, passa sozinho
  document.querySelectorAll('[data-carrossel]').forEach(function (c) {
    var trilho = c.querySelector('[data-carrossel-trilho]');
    var secao = c.closest('section') || c;
    var ant = secao.querySelector('[data-carrossel-ant]');
    var prox = secao.querySelector('[data-carrossel-prox]');
    if (!trilho || trilho.children.length < 2) return;
    function passo() { var i = trilho.children[0]; return i.getBoundingClientRect().width + 20; }
    function noFim() { return trilho.scrollLeft + trilho.clientWidth >= trilho.scrollWidth - 4; }
    function avancar() { if (noFim()) trilho.scrollTo({ left: 0 }); else trilho.scrollBy({ left: passo() }); }
    function voltar() { if (trilho.scrollLeft <= 4) trilho.scrollTo({ left: trilho.scrollWidth }); else trilho.scrollBy({ left: -passo() }); }
    if (prox) prox.addEventListener('click', function () { avancar(); reiniciar(); });
    if (ant) ant.addEventListener('click', function () { voltar(); reiniciar(); });
    var reduzir = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    var timer = null, pausado = false;
    function reiniciar() { clearInterval(timer); if (!reduzir) timer = setInterval(function () { if (!pausado && !document.hidden) avancar(); }, 4000); }
    ['mouseenter', 'focusin', 'touchstart'].forEach(function (e) { c.addEventListener(e, function () { pausado = true; }, { passive: true }); });
    ['mouseleave', 'focusout'].forEach(function (e) { c.addEventListener(e, function () { pausado = false; }); });
    c.addEventListener('touchend', function () { setTimeout(function () { pausado = false; }, 4000); }, { passive: true });
    reiniciar();
  });

  // Compartilhar imóvel: menu do celular quando existe; senão copia o link
  document.querySelectorAll('[data-compartilhar]').forEach(function (b) {
    var rotulo = b.querySelector('[data-compartilhar-rotulo]');
    b.addEventListener('click', function () {
      var dados = { title: b.getAttribute('data-titulo') || document.title, url: b.getAttribute('data-url') || location.href };
      if (navigator.share) { navigator.share(dados).catch(function () {}); return; }
      var avisar = function (t) { if (!rotulo) return; rotulo.textContent = t; rotulo.classList.add('mostrar'); setTimeout(function () { rotulo.classList.remove('mostrar'); }, 2500); };
      if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(dados.url).then(function () { avisar('Link copiado!'); }, function () { window.prompt('Copie o link:', dados.url); });
      } else {
        window.prompt('Copie o link:', dados.url);
      }
    });
  });

  // Busca: não manda campos vazios (link limpo: /imoveis?transacao=venda em vez de ...&bairro=&tipo=)
  document.querySelectorAll('form[role="search"]').forEach(function (f) {
    f.addEventListener('submit', function () {
      Array.prototype.forEach.call(f.elements, function (el) {
        if (el.name && !el.disabled && (el.type !== 'radio' || el.checked) && el.value === '') el.disabled = true;
      });
      setTimeout(function () { Array.prototype.forEach.call(f.elements, function (el) { el.disabled = false; }); }, 0);
    });
  });

  // Galeria de fotos
  var galeria = document.querySelector('[data-galeria]');
  if (galeria) {
    var trilho = galeria.querySelector('[data-trilho]');
    var contador = galeria.querySelector('[data-contador]');
    var miniaturas = document.querySelectorAll('[data-miniaturas] button');
    var total = trilho ? trilho.children.length : 0;
    var atual = 0;

    var irPara = function (i, suave) {
      if (!trilho || !total) return;
      atual = (i + total) % total;
      trilho.scrollTo({ left: atual * trilho.clientWidth, behavior: suave === false ? 'auto' : 'smooth' });
    };
    var atualizar = function () {
      if (!trilho) return;
      var i = Math.round(trilho.scrollLeft / Math.max(1, trilho.clientWidth));
      atual = Math.max(0, Math.min(total - 1, i));
      if (contador) contador.textContent = (atual + 1) + ' / ' + total;
      miniaturas.forEach(function (m, idx) {
        if (idx === atual) {
          m.setAttribute('aria-current', 'true');
          m.scrollIntoView({ block: 'nearest', inline: 'nearest' });
        } else {
          m.removeAttribute('aria-current');
        }
      });
    };
    if (trilho) {
      var t;
      trilho.addEventListener('scroll', function () { clearTimeout(t); t = setTimeout(atualizar, 60); }, { passive: true });
      trilho.addEventListener('keydown', function (ev) {
        if (ev.key === 'ArrowRight') { ev.preventDefault(); irPara(atual + 1); }
        if (ev.key === 'ArrowLeft') { ev.preventDefault(); irPara(atual - 1); }
      });
    }
    var ant = galeria.querySelector('[data-ant]');
    var prox = galeria.querySelector('[data-prox]');
    if (ant) ant.addEventListener('click', function () { irPara(atual - 1); });
    if (prox) prox.addEventListener('click', function () { irPara(atual + 1); });
    miniaturas.forEach(function (m) {
      m.addEventListener('click', function () { irPara(parseInt(m.getAttribute('data-ir'), 10)); });
    });

    // Tela cheia
    var dialogo = document.querySelector('[data-dialogo]');
    var botaoCheia = galeria.querySelector('[data-tela-cheia]');
    var abrirTelaCheia = function () {
      if (!dialogo || typeof dialogo.showModal !== 'function') return;
      dialogo.querySelectorAll('img[data-src]').forEach(function (img) {
        img.src = img.getAttribute('data-src');
        img.removeAttribute('data-src');
      });
      dialogo.showModal();
      var tc = dialogo.querySelector('[data-trilho-cheio]');
      if (tc) tc.scrollLeft = atual * tc.clientWidth;
    };
    if (botaoCheia) botaoCheia.addEventListener('click', abrirTelaCheia);
    if (trilho && window.matchMedia('(min-width: 700px)').matches) {
      trilho.addEventListener('click', abrirTelaCheia);
      trilho.style.cursor = 'zoom-in';
    }
    if (dialogo) {
      var fechar = dialogo.querySelector('[data-fechar]');
      if (fechar) fechar.addEventListener('click', function () { dialogo.close(); });
    }
  }

  // Vídeo do YouTube: só carrega quando a pessoa clica (página mais leve)
  document.querySelectorAll('[data-video]').forEach(function (box) {
    var capa = box.querySelector('.video__capa');
    if (!capa) return;
    capa.addEventListener('click', function () {
      var id = box.getAttribute('data-video');
      if (!/^[A-Za-z0-9_-]{11}$/.test(id)) return;
      var iframe = document.createElement('iframe');
      iframe.src = 'https://www.youtube-nocookie.com/embed/' + id + '?autoplay=1&rel=0&playsinline=1';
      iframe.title = 'Vídeo do imóvel';
      iframe.allow = 'autoplay; encrypted-media; picture-in-picture; fullscreen';
      iframe.allowFullscreen = true;
      box.replaceChildren(iframe);
    });
  });

  // ---------- Google / Meta (configurados em Painel → Integrações) ----------
  var cfgEl = document.getElementById('rastreio-config');
  var cfg = null;
  try { cfg = cfgEl ? JSON.parse(cfgEl.textContent) : null; } catch (e) { cfg = null; }
  var carregado = false;

  function lerConsentimento() {
    var m = document.cookie.match(/(?:^|; )vc_cookies=([01])/);
    return m ? m[1] : null;
  }
  function gravarConsentimento(v) {
    document.cookie = 'vc_cookies=' + v + '; Max-Age=' + (180 * 86400) + '; Path=/; SameSite=Lax' + (location.protocol === 'https:' ? '; Secure' : '');
  }
  function addScript(src) {
    var s = document.createElement('script');
    s.async = true; s.src = src;
    if (cfgEl && cfgEl.nonce) s.nonce = cfgEl.nonce;
    document.head.appendChild(s);
  }
  function carregarRastreadores() {
    if (!cfg || carregado) return;
    carregado = true;
    window.dataLayer = window.dataLayer || [];
    if (cfg.gtm_id) {
      window.dataLayer.push({ 'gtm.start': Date.now(), event: 'gtm.js' });
      addScript('https://www.googletagmanager.com/gtm.js?id=' + encodeURIComponent(cfg.gtm_id));
    }
    if (cfg.ga4_id || cfg.ads_id) {
      window.gtag = function () { window.dataLayer.push(arguments); };
      window.gtag('js', new Date());
      if (cfg.ga4_id) window.gtag('config', cfg.ga4_id);
      if (cfg.ads_id) window.gtag('config', cfg.ads_id);
      addScript('https://www.googletagmanager.com/gtag/js?id=' + encodeURIComponent(cfg.ga4_id || cfg.ads_id));
    }
    if (cfg.meta_pixel) {
      var f = window.fbq = function () { f.callMethod ? f.callMethod.apply(f, arguments) : f.queue.push(arguments); };
      if (!window._fbq) window._fbq = f;
      f.push = f; f.loaded = true; f.version = '2.0'; f.queue = [];
      addScript('https://connect.facebook.net/en_US/fbevents.js');
      window.fbq('init', cfg.meta_pixel);
      window.fbq('track', 'PageView');
      if (cfg.imovel) window.fbq('track', 'ViewContent', { content_ids: [cfg.imovel.codigo], content_type: 'home_listing', value: cfg.imovel.valor, currency: 'BRL' });
    }
    if (cfg.imovel && window.gtag && cfg.ga4_id) {
      window.gtag('event', 'view_item', { items: [{ item_id: cfg.imovel.codigo }], value: cfg.imovel.valor, currency: 'BRL' });
    }
  }

  var aviso = document.getElementById('cookies');
  function mostrarAviso(mostrar) {
    if (!aviso) return;
    aviso.hidden = !mostrar;
    document.body.classList.toggle('com-cookies', mostrar);
  }
  if (cfg) {
    var escolha = lerConsentimento();
    if (!cfg.consentimento || escolha === '1') carregarRastreadores();
    else if (escolha === null) mostrarAviso(true);
  }
  document.querySelectorAll('[data-cookies]').forEach(function (b) {
    b.addEventListener('click', function () {
      var v = b.getAttribute('data-cookies');
      gravarConsentimento(v);
      mostrarAviso(false);
      if (v === '1') carregarRastreadores();
      else if (carregado) location.reload(); // remove o que já estava rodando
    });
  });
  document.querySelectorAll('[data-cookies-abrir]').forEach(function (b) {
    b.addEventListener('click', function () { mostrarAviso(true); });
  });

  // Clique no WhatsApp = conversão (Analytics, Google Ads e Pixel)
  document.addEventListener('click', function (ev) {
    var a = ev.target.closest ? ev.target.closest('a[href^="/whatsapp"]') : null;
    if (!a || !carregado) return;
    var p = new URL(a.href, location.href).searchParams;
    var codigo = p.get('c') || '';
    var origem = p.get('o') || '';
    window.dataLayer.push({ event: 'whatsapp_clique', imovel_codigo: codigo, origem: origem });
    if (window.gtag) {
      if (cfg.ga4_id) window.gtag('event', 'generate_lead', { method: 'whatsapp', imovel_codigo: codigo, origem: origem, transport_type: 'beacon' });
      if (cfg.ads_id && cfg.ads_rotulo_whatsapp) window.gtag('event', 'conversion', { send_to: cfg.ads_id + '/' + cfg.ads_rotulo_whatsapp, transport_type: 'beacon' });
    }
    if (window.fbq) window.fbq('track', 'Contact', codigo ? { content_ids: [codigo] } : {});
    // espera um instante para o Pixel enviar o evento antes de sair da página
    if (window.fbq && !ev.ctrlKey && !ev.metaKey && !ev.shiftKey && a.target !== '_blank') {
      ev.preventDefault();
      setTimeout(function () { location.href = a.href; }, 250);
    }
  }, true);
})();

// Filtros da listagem: recolhidos no celular, abertos no computador
(function () {
  var caixa = document.querySelector('[data-filtros]');
  if (!caixa || !window.matchMedia) return;
  var mq = window.matchMedia('(min-width: 900px)');
  function ajustar() { caixa.open = mq.matches; }
  ajustar();
  if (mq.addEventListener) mq.addEventListener('change', ajustar);
})();

// Busca da home: "Mais filtros" aberto no computador, recolhido no celular
(function () {
  var mais = document.querySelector('[data-busca-mais]');
  if (!mais || !window.matchMedia) return;
  var mq = window.matchMedia('(min-width: 900px)');
  function ajustar() { mais.open = mq.matches; }
  ajustar();
  if (mq.addEventListener) mq.addEventListener('change', ajustar);
})();
