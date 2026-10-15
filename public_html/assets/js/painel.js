// Vasconcelos Corretor — painel (sem dependências)
(function () {
  'use strict';

  var csrf = (document.querySelector('meta[name="csrf"]') || {}).content || '';
  var qs = function (s, el) { return (el || document).querySelector(s); };
  var qsa = function (s, el) { return Array.prototype.slice.call((el || document).querySelectorAll(s)); };

  var post = function (url, dados) {
    var fd = dados instanceof FormData ? dados : new FormData();
    if (!(dados instanceof FormData) && dados) {
      Object.keys(dados).forEach(function (k) {
        var v = dados[k];
        if (Array.isArray(v)) v.forEach(function (x) { fd.append(k + '[]', x); });
        else fd.append(k, v);
      });
    }
    return fetch(url, {
      method: 'POST',
      body: fd,
      credentials: 'same-origin',
      headers: { 'X-CSRF-Token': csrf, 'Accept': 'application/json' }
    }).then(function (r) {
      return r.json().catch(function () { return { erro: 'Resposta inesperada (' + r.status + ').' }; })
        .then(function (j) { j._status = r.status; return j; });
    });
  };

  /* ---------- Formulário do imóvel ---------- */
  var atualizarNegocios = function () {
    qsa('[data-negocio]').forEach(function (cb) {
      var k = cb.getAttribute('data-negocio');
      qsa('[data-preco="' + k + '"]').forEach(function (el) { el.hidden = !cb.checked; });
    });
  };
  qsa('[data-negocio]').forEach(function (cb) { cb.addEventListener('change', atualizarNegocios); });
  atualizarNegocios();

  var togglePortal = qs('[data-toggle-portal]');
  var boxPortal = qs('[data-portal]');
  if (togglePortal && boxPortal) {
    var fp = function () { boxPortal.hidden = !togglePortal.checked; };
    togglePortal.addEventListener('change', fp);
    fp();
  }

  qsa('[data-contar]').forEach(function (campo) {
    var nome = campo.getAttribute('data-contar');
    var alvo = qs('[data-contador-de="' + nome + '"]');
    if (!alvo) return;
    var original = alvo.textContent;
    var f = function () {
      var n = campo.value.trim().length;
      alvo.textContent = n ? n + ' letras · ' + original : original;
    };
    campo.addEventListener('input', f);
    f();
  });

  /* ---------- Fotos ---------- */
  var grade = qs('[data-fotos]');
  if (grade) {
    var base = grade.getAttribute('data-base');
    var avisoCapa = qs('[data-aviso-capa]');
    var progresso = qs('[data-progresso]');

    // capa = primeira foto que aparece no anúncio (fotos do condomínio escondidas não contam)
    var checarCapa = function () {
      var fotos = qsa('.p-foto', grade);
      fotos.forEach(function (el) { el.classList.remove('p-foto--capa'); });
      var primeira = fotos.filter(function (el) { return !el.classList.contains('p-foto--oculta'); })[0];
      if (primeira) primeira.classList.add('p-foto--capa');
      if (avisoCapa) avisoCapa.classList.toggle('p-oculto', !(primeira && primeira.classList.contains('p-foto--em-pe')));
    };
    var salvarOrdem = function () {
      var ids = qsa('.p-foto', grade).map(function (el) { return el.getAttribute('data-id'); });
      checarCapa();
      post(base + '/fotos/ordem', { ids: ids });
    };
    var montarFoto = function (f) {
      var div = document.createElement('div');
      div.className = 'p-foto' + (f.em_pe ? ' p-foto--em-pe' : '');
      div.draggable = true;
      div.setAttribute('data-id', f.id);
      var img = document.createElement('img');
      img.src = f.url; img.alt = '';
      div.appendChild(img);
      div.insertAdjacentHTML('beforeend', '<span class="p-foto__tags"><span class="p-foto__capa">Capa</span></span><span class="p-foto__pe">Em pé</span>' +
        '<button type="button" class="p-foto__excluir" data-excluir aria-label="Excluir foto">✕</button>');
      prepararFoto(div);
      return div;
    };
    var prepararFoto = function (el) {
      // Botões de mover (funcionam no celular, onde arrastar é difícil)
      if (!el.querySelector('.p-foto__mover')) {
        el.insertAdjacentHTML('beforeend', '<span class="p-foto__mover"><button type="button" data-mover="-1" aria-label="Mover para a esquerda">‹</button><button type="button" data-mover="1" aria-label="Mover para a direita">›</button></span>');
      }
      el.addEventListener('dragstart', function (ev) { el.classList.add('arrastando'); ev.dataTransfer.effectAllowed = 'move'; });
      el.addEventListener('dragend', function () { el.classList.remove('arrastando'); salvarOrdem(); });
    };
    qsa('.p-foto', grade).forEach(prepararFoto);

    grade.addEventListener('dragover', function (ev) {
      var arrastando = qs('.arrastando', grade);
      if (!arrastando) return;
      ev.preventDefault();
      var alvo = ev.target.closest('.p-foto');
      if (!alvo || alvo === arrastando) return;
      var r = alvo.getBoundingClientRect();
      var depois = (ev.clientX - r.left) > r.width / 2;
      grade.insertBefore(arrastando, depois ? alvo.nextSibling : alvo);
    });

    grade.addEventListener('click', function (ev) {
      var mover = ev.target.closest('[data-mover]');
      if (mover) {
        var el = mover.closest('.p-foto');
        if (mover.getAttribute('data-mover') === '-1' && el.previousElementSibling) grade.insertBefore(el, el.previousElementSibling);
        if (mover.getAttribute('data-mover') === '1' && el.nextElementSibling) grade.insertBefore(el.nextElementSibling, el);
        salvarOrdem();
        return;
      }
      var ocultar = ev.target.closest('[data-ocultar]');
      if (ocultar) {
        var fc = ocultar.closest('.p-foto');
        ocultar.disabled = true;
        post(base + '/fotos/' + fc.getAttribute('data-id') + '/ocultar').then(function (r) {
          ocultar.disabled = false;
          if (!r.ok) return;
          fc.classList.toggle('p-foto--oculta', r.oculta);
          ocultar.textContent = r.oculta ? 'Mostrar' : 'Esconder';
          ocultar.setAttribute('aria-pressed', r.oculta ? 'true' : 'false');
          checarCapa();
        });
        return;
      }
      var excluir = ev.target.closest('[data-excluir]');
      if (excluir) {
        var foto = excluir.closest('.p-foto');
        if (!window.confirm('Excluir esta foto?')) return;
        post(base + '/fotos/' + foto.getAttribute('data-id') + '/excluir').then(function (r) {
          if (r.ok) { foto.remove(); checarCapa(); }
        });
      }
    });
    checarCapa();

    // Reduz a foto no próprio celular antes de enviar (envio muito mais rápido)
    var reduzir = function (arquivo) {
      if (!window.createImageBitmap || !arquivo.type.match(/^image\//)) return Promise.resolve(arquivo);
      return createImageBitmap(arquivo, { imageOrientation: 'from-image' }).then(function (bmp) {
        var max = 2400;
        var escala = Math.min(1, max / Math.max(bmp.width, bmp.height));
        if (escala === 1 && arquivo.size < 3 * 1024 * 1024) return arquivo;
        var c = document.createElement('canvas');
        c.width = Math.round(bmp.width * escala);
        c.height = Math.round(bmp.height * escala);
        c.getContext('2d').drawImage(bmp, 0, 0, c.width, c.height);
        return new Promise(function (ok) {
          c.toBlob(function (b) { ok(b ? new File([b], 'foto.jpg', { type: 'image/jpeg' }) : arquivo); }, 'image/jpeg', 0.9);
        });
      }).catch(function () { return arquivo; });
    };

    var enviar = function (arquivos) {
      arquivos = Array.prototype.slice.call(arquivos);
      if (!arquivos.length) return;
      var total = arquivos.length, feitos = 0, erros = [];
      progresso.classList.remove('p-oculto');
      var barra = progresso.querySelector('div');
      var texto = progresso.querySelector('span');
      var passo = function () {
        barra.style.setProperty('--p', Math.round(feitos / total * 100) + '%');
        texto.textContent = feitos < total ? 'Enviando ' + (feitos + 1) + ' de ' + total + '…' : 'Pronto! ' + total + ' foto(s).' + (erros.length ? ' Erros: ' + erros.join('; ') : '');
        if (feitos >= total) { checarCapa(); return; }
        var arq = arquivos[feitos];
        reduzir(arq).then(function (pronto) {
          var fd = new FormData();
          fd.append('foto', pronto, arq.name || 'foto.jpg');
          return post(base + '/fotos', fd);
        }).then(function (r) {
          if (r.ok) grade.appendChild(montarFoto(r.foto));
          else erros.push((arq.name || 'foto') + ': ' + (r.erro || 'falhou'));
        }).catch(function () {
          erros.push((arq.name || 'foto') + ': sem conexão');
        }).then(function () { feitos++; passo(); });
      };
      passo();
    };

    var input = qs('[data-input-fotos]');
    var zona = qs('[data-upload]');
    if (input) input.addEventListener('change', function () { enviar(input.files); input.value = ''; });
    if (zona) {
      ['dragenter', 'dragover'].forEach(function (t) { zona.addEventListener(t, function (ev) { ev.preventDefault(); zona.classList.add('sobre'); }); });
      ['dragleave', 'drop'].forEach(function (t) { zona.addEventListener(t, function () { zona.classList.remove('sobre'); }); });
      zona.addEventListener('drop', function (ev) { ev.preventDefault(); enviar(ev.dataTransfer.files); });
    }
  }

  /* ---------- Botões 0–5 / 6+ ---------- */
  qsa('[data-chips]').forEach(function (grupo) {
    var mais = qs('.p-chips__mais', grupo);
    var f = function () {
      var marcado = qs('input[type=radio]:checked', grupo);
      var abrir = !!(marcado && marcado.value === 'mais');
      mais.hidden = !abrir;
      if (abrir && !mais.value) mais.focus();
    };
    qsa('input[type=radio]', grupo).forEach(function (r) { r.addEventListener('change', f); });
  });

  /* ---------- Condomínio no cadastro do imóvel ---------- */
  var temCond = qs('[data-tem-condominio]');
  if (temCond) {
    var camposCond = qs('[data-cond-campos]');
    var selCond = qs('[data-condominio]');
    var infoCond = qs('[data-info-condominio]');
    var fotosCond = qs('[data-cond-fotos]');
    var itensCond = qsa('[name="cond_itens[]"]');
    var modo = function () { var r = qs('[data-cond-modo]:checked'); return r ? r.value : 'existente'; };
    var dadosCond = function () {
      var opt = selCond && selCond.options[selCond.selectedIndex];
      try { return opt ? JSON.parse(opt.getAttribute('data-dados') || '{}') : {}; } catch (e) { return {}; }
    };
    var mostrarCond = function () {
      var d = selCond && selCond.value ? dadosCond() : null;
      fotosCond.replaceChildren();
      (d && d.fotos || []).forEach(function (u) {
        var img = document.createElement('img');
        img.src = u; img.alt = ''; img.loading = 'lazy';
        fotosCond.appendChild(img);
      });
      fotosCond.hidden = !(d && d.fotos && d.fotos.length);
      infoCond.hidden = !d;
      infoCond.textContent = '';
      if (d) {
        infoCond.append((d.qtd_fotos ? d.qtd_fotos + ' fotos das áreas comuns entram na galeria deste imóvel. Em "6. Fotos" você escolhe a posição de cada uma ou esconde. ' : 'Este condomínio ainda não tem fotos. '));
        var a = document.createElement('a');
        a.href = d.editar; a.target = '_blank'; a.rel = 'noopener';
        a.textContent = d.qtd_fotos ? 'Editar condomínio ↗' : 'Adicionar fotos ↗';
        infoCond.appendChild(a);
      }
    };
    var aplicarModo = function () {
      var novo = modo() === 'novo';
      qs('[data-cond-existente]').hidden = novo;
      qs('[data-cond-novo]').hidden = !novo;
    };
    temCond.addEventListener('change', function () { camposCond.hidden = !temCond.checked; });
    qsa('[data-cond-modo]').forEach(function (r) {
      r.addEventListener('change', function () {
        aplicarModo();
        if (modo() === 'novo') itensCond.forEach(function (cb) { cb.checked = false; });
        else if (selCond.value) selCond.dispatchEvent(new Event('change'));
      });
    });
    if (selCond) {
      selCond.addEventListener('change', function () {
        var d = dadosCond();
        var marcados = d.itens || [];
        itensCond.forEach(function (cb) { cb.checked = marcados.indexOf(cb.value) !== -1; });
        ['endereco', 'numero', 'bairro', 'cep', 'latitude', 'longitude'].forEach(function (k) {
          var campo = qs('[name="' + k + '"]');
          if (campo && !campo.value && d[k]) campo.value = d[k];
        });
        mostrarCond();
      });
    }
    aplicarModo();
    mostrarCond();
  }

  /* ---------- Localização: bairro, CEP automático e endereço completo ---------- */
  var selBairro = qs('[data-bairro]');
  if (selBairro) {
    var ceps = {};
    try { ceps = JSON.parse(selBairro.getAttribute('data-ceps') || '{}'); } catch (e) {}
    var cepsTabela = Object.keys(ceps).map(function (k) { return ceps[k]; });
    var campoCep = qs('[data-cep]');
    var boxOutro = qs('[data-bairro-outro]');
    var atualizarBairro = function () {
      boxOutro.hidden = selBairro.value !== '__outro';
      // CEP vem do bairro, a não ser que o corretor tenha digitado um CEP próprio
      if (campoCep && ceps[selBairro.value] && (!campoCep.value || campoCep.value === campoCep.defaultValue || cepsTabela.indexOf(campoCep.value) !== -1)) {
        campoCep.value = ceps[selBairro.value];
      }
    };
    selBairro.addEventListener('change', atualizarBairro);
    var chaveEnd = qs('[data-endereco-completo]');
    var boxEnd = qs('[data-endereco-campos]');
    if (chaveEnd) chaveEnd.addEventListener('change', function () { boxEnd.hidden = !chaveEnd.checked; });
  }

  /* ---------- Preencher a partir do texto ---------- */
  var boxPreencher = qs('[data-preencher]');
  if (boxPreencher) {
    var form = boxPreencher.closest('form');
    var btnPreencher = qs('[data-preencher-botao]', boxPreencher);
    var btnDesfazer = qs('[data-preencher-desfazer]', boxPreencher);
    var resultado = qs('[data-preencher-resultado]', boxPreencher);
    var fotoAntes = null;
    var fmt = function (v) { return new Intl.NumberFormat('pt-BR', { maximumFractionDigits: 2 }).format(v); };
    var disparar = function (el, tipo) { el.dispatchEvent(new Event(tipo || 'change', { bubbles: true })); };
    var marcar = function (el) {
      var alvo = el.closest('.p-campo, .p-chips') || el;
      alvo.classList.add('p-preenchido');
    };
    var campo = function (nome) { return form.querySelector('[name="' + nome + '"]'); };
    var definir = function (nome, valor) {
      var el = campo(nome);
      if (!el || valor === undefined || valor === null || valor === '') return 0;
      el.value = valor;
      disparar(el, 'input'); disparar(el);
      marcar(el);
      return 1;
    };
    var chip = function (nome, n) {
      if (n === undefined || n === null) return 0;
      var grupo = campo(nome) && campo(nome).closest('[data-chips]');
      if (!grupo) return 0;
      var r = n > 5 ? qs('input[value="mais"]', grupo) : qs('input[value="' + n + '"]', grupo);
      r.checked = true; disparar(r);
      if (n > 5) qs('.p-chips__mais', grupo).value = n;
      grupo.classList.add('p-preenchido');
      return 1;
    };
    var marcarCaixas = function (nome, lista) {
      var n = 0;
      (lista || []).forEach(function (v) {
        qsa('[name="' + nome + '"]', form).forEach(function (cb) {
          if (cb.value === v) { if (!cb.checked) { cb.checked = true; disparar(cb); } cb.closest('label').classList.add('p-preenchido'); n++; }
        });
      });
      return n;
    };
    var tirarFoto = function () {
      return qsa('input, select, textarea', form).filter(function (el) { return el.name && el.name !== '_csrf'; })
        .map(function (el) { return { el: el, valor: el.value, marcado: el.checked }; });
    };
    var restaurar = function (foto) {
      foto.forEach(function (f) {
        if (f.el.type === 'checkbox' || f.el.type === 'radio') f.el.checked = f.marcado; else f.el.value = f.valor;
      });
      foto.forEach(function (f) { disparar(f.el); if (f.el.matches('[data-contar]')) disparar(f.el, 'input'); });
      // os eventos acima podem mexer em outros campos (ex.: condomínio remarca itens): volta tudo de novo, sem eventos
      foto.forEach(function (f) {
        if (f.el.type === 'checkbox' || f.el.type === 'radio') f.el.checked = f.marcado; else f.el.value = f.valor;
      });
      qsa('.p-preenchido', form).forEach(function (el) { el.classList.remove('p-preenchido'); });
    };
    form.addEventListener('input', function (ev) {
      var w = ev.target.closest && ev.target.closest('.p-preenchido');
      if (w && ev.isTrusted) w.classList.remove('p-preenchido');
    });

    btnPreencher.addEventListener('click', function () {
      var texto = qs('[data-preencher-texto]', boxPreencher).value;
      btnPreencher.disabled = true;
      btnPreencher.textContent = 'Lendo o texto…';
      post('/painel/preencher', { texto: texto }).then(function (r) {
        btnPreencher.disabled = false;
        btnPreencher.textContent = 'Preencher o formulário';
        resultado.hidden = false;
        resultado.replaceChildren();
        if (!r.ok) { resultado.textContent = r.erro || 'Não consegui ler o texto.'; return; }
        fotoAntes = tirarFoto();
        qsa('.p-preenchido', form).forEach(function (el) { el.classList.remove('p-preenchido'); });
        var c = r.campos, n = 0;
        n += definir('tipo', c.tipo);
        ['quartos', 'suites', 'banheiros', 'vagas'].forEach(function (k) { n += chip(k, c[k]); });
        ['area_util', 'area_total', 'preco_venda', 'preco_locacao', 'condominio', 'iptu'].forEach(function (k) { n += definir(k, c[k] != null ? fmt(c[k]) : null); });
        n += definir('iptu_periodo', c.iptu_periodo && c.iptu ? c.iptu_periodo : null);
        if (c.negocio) {
          qsa('[data-negocio]', form).forEach(function (cb) { cb.checked = c.negocio.indexOf(cb.value) !== -1; disparar(cb); });
          n++;
        }
        if (c.locacao_pacote) { var pc = campo('locacao_pacote'); pc.checked = true; disparar(pc); n++; }
        n += marcarCaixas('itens[]', c.itens);
        n += definir('titulo', c.titulo);
        n += definir('descricao', c.descricao);
        n += definir('video_url', c.video_url);
        if (c.bairro) {
          var sb = campo('bairro');
          var temOpcao = qsa('option', sb).some(function (o) { return o.value === c.bairro; });
          if (temOpcao) n += definir('bairro', c.bairro);
          else { n += definir('bairro', '__outro'); definir('bairro_outro', c.bairro); }
        }
        if (c.tem_condominio) {
          var tc = qs('[data-tem-condominio]', form);
          if (!tc.checked) { tc.checked = true; disparar(tc); }
          var modoExist = qs('[data-cond-modo][value="existente"]', form);
          modoExist.checked = true; disparar(modoExist);
          if (c.condominio_id) n += definir('condominio_id', String(c.condominio_id));
          n += marcarCaixas('cond_itens[]', c.cond_itens);
        }
        var forte = document.createElement('strong');
        forte.textContent = n ? 'Preenchi ' + n + ' campos, marcados em amarelo. Confira antes de salvar.' : 'Não achei dados de imóvel nesse texto.';
        resultado.appendChild(forte);
        if (r.avisos && r.avisos.length) {
          var ul = document.createElement('ul');
          r.avisos.forEach(function (a) { var li = document.createElement('li'); li.textContent = a; ul.appendChild(li); });
          resultado.appendChild(ul);
        }
        btnDesfazer.hidden = !n;
      }).catch(function () {
        btnPreencher.disabled = false;
        btnPreencher.textContent = 'Preencher o formulário';
        resultado.hidden = false;
        resultado.textContent = 'Sem conexão. Tente de novo.';
      });
    });
    btnDesfazer.addEventListener('click', function () {
      if (fotoAntes) restaurar(fotoAntes);
      fotoAntes = null;
      btnDesfazer.hidden = true;
      resultado.textContent = 'Desfeito. O formulário voltou ao que estava.';
    });
  }

  /* ---------- Barras de progresso com valor inicial ---------- */
  qsa('[data-pct]').forEach(function (el) { el.style.setProperty('--p', el.getAttribute('data-pct') + '%'); });

  /* ---------- Migração ---------- */
  var botoesMig = qsa('[data-migrar]');
  if (botoesMig.length) {
    var log = qs('[data-log]');
    var barraMig = qs('[data-progresso-migracao]');
    var escrever = function (txt) {
      var li = document.createElement('li');
      li.textContent = txt;
      log.insertBefore(li, log.firstChild);
    };
    var atualizarBarra = function (feitos, total) {
      barraMig.classList.remove('p-oculto');
      barraMig.querySelector('div').style.setProperty('--p', (total ? Math.round(feitos / total * 100) : 0) + '%');
      barraMig.querySelector('span').textContent = feitos + ' de ' + total;
    };
    var rodando = false;
    var proximo = function () {
      post('/painel/importar', { acao: 'proximo' }).then(function (r) {
        if (r._status === 429 && r.esperar) {
          escrever(r.erro);
          if (r.esperar <= 120) { setTimeout(proximo, r.esperar * 1000); } else { rodando = false; }
          return;
        }
        if (!r.ok) { escrever('Erro: ' + (r.erro || 'desconhecido') + '. Clique em continuar para tentar de novo.'); rodando = false; return; }
        if (r.situacao === 'fim') { escrever('Migração concluída!'); rodando = false; return; }
        atualizarBarra(r.feitos, r.total);
        escrever('Cód. ' + (r.codigo || '?') + ': ' + r.situacao + (r.obs ? ' (' + r.obs + ')' : ''));
        if (r.restantes > 0) setTimeout(proximo, 1100); else { escrever('Migração concluída!'); rodando = false; }
      }).catch(function () { escrever('Conexão caiu. Clique em continuar.'); rodando = false; });
    };
    botoesMig.forEach(function (b) {
      b.addEventListener('click', function () {
        if (rodando) return;
        rodando = true;
        if (b.getAttribute('data-migrar') === 'listar') {
          escrever('Buscando lista na Code49…');
          post('/painel/importar', { acao: 'listar' }).then(function (r) {
            if (!r.ok) { escrever('Erro: ' + r.erro); rodando = false; return; }
            escrever(r.total + ' imóveis na fila. Importando…');
            atualizarBarra(0, r.total);
            proximo();
          });
        } else {
          proximo();
        }
      });
    });
  }

  /* ---------- QR code da verificação em duas etapas ---------- */
  document.addEventListener('DOMContentLoaded', function () {
    var qr = qs('[data-qr]');
    if (qr && window.qrcode) {
      var q = window.qrcode(0, 'M');
      q.addData(qr.getAttribute('data-qr'));
      q.make();
      qr.innerHTML = q.createSvgTag({ cellSize: 5, margin: 4, scalable: true });
    }
  });
})();
