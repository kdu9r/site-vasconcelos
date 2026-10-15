# Site Vasconcelos Corretor: como instalar na HostGator

Site em PHP + MySQL, sem build e sem dependências externas. Roda em qualquer plano da HostGator com PHP 8.1 ou superior.

## O que vem no pacote

```
public_html/        → vai para a pasta pública do site (o que o visitante acessa)
vasconcelos_app/    → vai para FORA da pasta pública (código, configuração, banco)
```

O código fica fora da pasta pública de propósito: mesmo que alguém tente, não consegue acessar a configuração nem o código pela internet.

---

## 1. Antes de começar (no cPanel da HostGator)

1. **Versão do PHP:** cPanel → *Selecionar versão do PHP* (ou *MultiPHP Manager*) → escolha **8.1 ou mais nova**.
2. **SSL (cadeado):** cPanel → *SSL/TLS Status* → ative o certificado gratuito (AutoSSL) para o domínio.

## 2. Testar sem mexer no site atual

Enquanto a Code49 continua no ar, instale o site novo num **subdomínio de teste**:

1. cPanel → *Domínios* (ou *Subdomínios*) → crie, por exemplo, `novo.SEUDOMINIO.com.br`.
2. Anote a pasta que o cPanel criar para ele (ex.: `/home/usuario/novo.seudominio.com.br`). Ela vai fazer o papel de `public_html`.

> Na virada final, o conteúdo vai para o `public_html` do domínio `vasconceloscorretor.com`.

## 3. Banco de dados

1. cPanel → *Bancos de dados MySQL*:
   - Crie um banco (ex.: `usuario_vasconcelos`).
   - Crie um usuário com **senha forte** (use o gerador do cPanel).
   - Adicione o usuário ao banco com **TODOS OS PRIVILÉGIOS**.
2. cPanel → *phpMyAdmin* → clique no banco → aba **Importar** → envie `vasconcelos_app/database/schema.sql`.

## 4. Enviar os arquivos

cPanel → *Gerenciador de Arquivos*:

1. Envie a pasta **`vasconcelos_app`** para `/home/usuario/` (ao lado do `public_html`, **não dentro**).
2. Envie o **conteúdo** de `public_html/` para a pasta pública do site (a do subdomínio, no teste).
   - Ative *Mostrar arquivos ocultos*: os arquivos `.htaccess` precisam ir junto.
3. O `index.php` procura a pasta `vasconcelos_app` um nível acima da pasta pública. Isso funciona para `/home/usuario/public_html` e para `/home/usuario/novo.seudominio.com.br`. Só ajuste o caminho no `index.php` se a pasta pública estiver em outro nível.

## 5. Configuração

1. Em `vasconcelos_app/`, copie `config.example.php` para **`config.php`**.
2. Preencha:
   - `site_url`: endereço do site (no teste, o do subdomínio, com `https://`).
   - `db`: nome do banco, usuário e senha do passo 3.
   - `app_secret`: um texto aleatório longo (pode gerar uma senha de 64 caracteres no gerador do cPanel).
   - `setup_token`: outro texto aleatório longo (usado só uma vez, no passo 6).
   - `public_dir`: caminho completo da pasta pública (ex.: `/home/usuario/public_html`).
   - `code49.token`: o token da API da Code49 (só para a migração).
3. Dê permissão de escrita (755) para `public_html/uploads/imoveis` e `vasconcelos_app/storage`.

## 6. Criar o seu acesso

1. Abra `https://SEU-SITE/painel/primeiro-acesso?token=SEU_SETUP_TOKEN`.
2. Crie o administrador (você).
3. **Apague o valor de `setup_token` no `config.php`** (deixe `''`).
4. Entre no painel → **Acessos** → crie os acessos do Fred e da Priscila. O painel gera uma senha temporária, que você envia para cada um.
5. Cada pessoa: **Minha conta** → ativar a **verificação em duas etapas** (Google Authenticator).

## 7. Migrar os imóveis da Code49

> ⚠️ **Faça antes de mudar o domínio e antes de cancelar a Code49.** A API e as fotos estão nos servidores da Code49.

1. Painel → **Migração** → *1. Buscar lista de imóveis*.
2. Deixe a página aberta até terminar (cerca de 1 imóvel por segundo, com as fotos).
3. Se aparecer "limite diário", volte no dia seguinte e clique em **Continuar**. O que já foi importado não duplica.
4. Confira o relatório em `vasconcelos_app/storage/relatorio_migracao.csv` (o que ficou sem preço, fotos que falharam etc.).
5. Marque de novo os **destaques** do CanalPro em cada imóvel (a Code49 não envia essa informação).

Se a API não responder pelo domínio, troque `code49.base_url` para `https://www.websitesparaimobiliarias.com.br/crm/api/v2` (endereço que aparece na documentação).

## 8. Ligar o CanalPro

Todo imóvel, novo ou migrado, nasce com o CanalPro **desligado** (no site, o imóvel novo já entra no ar e em destaque). Nada vai para os portais sem alguém ligar.

1. Para ligar um a um: no imóvel, bloco **7. Publicação** → *Anunciar no CanalPro*. Para ligar todos de uma vez: Painel → **Migração** → *Ligar CanalPro em todos os imóveis no ar*.
2. Abra `https://SEU-SITE/feed/canalpro.xml` e confira se os imóveis aparecem.
3. No **CanalPro** → *Integração* (ou peça ao suporte do Grupo ZAP): cadastre a URL do feed, formato **VRSync**.
4. Só entram no feed os imóveis completos: **5+ fotos, descrição com 50+ letras, CEP e área**. O painel mostra o que falta em cada um ("Fora do CanalPro: falta informação"). Os itens marcados no cadastro (piscina, portão eletrônico etc.) vão com os nomes oficiais do CanalPro.
5. Depois que o CanalPro importar, **pare de cadastrar à mão por lá**, senão os anúncios duplicam.

## 8b. Google e Instagram (opcional)

Painel → **Integrações** (só administrador). Cole só os códigos; o site coloca os scripts sozinho:

| Campo | Onde achar |
|---|---|
| Google Tag Manager `GTM-…` | tagmanager.google.com, ao lado do nome do contêiner |
| Google Analytics `G-…` | Analytics → Administrador → Fluxos de dados |
| Google Ads `AW-…` + rótulo | Google Ads → Metas → Conversões → crie a conversão "Contato" |
| Pixel da Meta (número) | business.facebook.com → Gerenciador de Eventos |
| Verificação do Search Console | Search Console → Prefixo do URL → Tag HTML (pode colar a tag inteira) |

- Cada clique em botão de WhatsApp vira conversão: `generate_lead` no Analytics, conversão no Ads e "Contato" no Pixel, com o código do imóvel. No Tag Manager, use o evento `whatsapp_clique`.
- Com o aviso de cookies ligado (recomendado, LGPD), Google e Meta só carregam depois que o visitante aceita. A política de privacidade se ajusta sozinha.
- Visitas de quem está logado no painel não contam.
- Depois de verificar no Search Console, envie `https://www.vasconceloscorretor.com/sitemap.xml`.

## 9. Virada do domínio

1. Coloque os arquivos no `public_html` do `vasconceloscorretor.com` e ajuste `site_url` e `public_dir` no `config.php`.
2. No **registro.br**, aponte o domínio para a HostGator (DNS da HostGator, informados no e-mail de boas-vindas da hospedagem).
3. Em até 24 h o site novo assume. Os links antigos da Code49 (`/123/imoveis/...`) **redirecionam sozinhos** para os novos.
4. Atualize a URL do feed no CanalPro, se tiver mudado.
5. Só então cancele a Code49.

---

## Publicação automática pelo GitHub

Depois da primeira instalação (feita à mão, com o zip), as atualizações chegam sozinhas: cada alteração na branch `main` do repositório é enviada para a HostGator por FTP.

1. cPanel → **Contas FTP**: anote o servidor (ex.: `ftp.seudominio.com.br`), o usuário e a senha. A conta principal do cPanel serve.
2. GitHub → repositório → **Settings → Secrets and variables → Actions**:
   - aba *Secrets*: `FTP_SERVER`, `FTP_USERNAME`, `FTP_PASSWORD`;
   - aba *Variables*: `DEPLOY_DIR` = pasta no servidor, terminando em `/` (ex.: `demo-vasconcelos/` no teste, `./` no site definitivo).
3. Em **Actions → Publicar na HostGator → Run workflow** dá para publicar na hora, sem alteração nova.

O envio nunca mexe no `config.php`, nas fotos enviadas pelo painel nem na pasta `storage`. Se o FTP recusar a conexão segura, troque `protocol: ftps` por `protocol: ftp` no arquivo `.github/workflows/publicar.yml`.

## Segurança: o que já está pronto

- Código e configuração fora da pasta pública; nenhum `.php` além do `index.php` executa.
- Senhas com hash forte (Argon2id/bcrypt). Nenhuma senha é guardada em texto.
- Bloqueio de 15 minutos após 5 senhas erradas (por e-mail) ou 10 (por IP).
- Verificação em duas etapas (app autenticador) opcional por usuário.
- Sessão expira após 2 h parado (12 h no máximo) e vale só para o navegador que fez login.
- Proteção contra CSRF, XSS (CSP rígida) e SQL injection (consultas preparadas).
- Fotos recriadas do zero no upload (remove arquivos maliciosos e dados de GPS das fotos).
- Registro de atividades: quem entrou, editou, excluiu (painel → Acessos).

**Cuidados contínuos:** mantenha o PHP atualizado no cPanel, use senhas únicas e ative as duas etapas para todos. Depois da migração, apague o `code49.token` do `config.php` e peça à Code49 para desativar o token.

## Trocar logo e foto do topo

- Logo: substitua `public_html/assets/img/logo.svg` (versão escura) e `logo-branco.svg` (versão clara, usada no rodapé).
- Foto do topo (drone): salve como `public_html/assets/img/hero.jpg` (ideal 1920×1080, até 400 KB). O site usa a foto automaticamente.
- Imagem de compartilhamento padrão: `public_html/assets/img/og-padrao.jpg` (1200×630).

## Testar no computador (opcional, para desenvolvedor)

```bash
mysql -u root -e "CREATE DATABASE vasconcelos CHARACTER SET utf8mb4"
mysql -u root vasconcelos < vasconcelos_app/database/schema.sql
cp vasconcelos_app/config.example.php vasconcelos_app/config.php   # e preencha
php vasconcelos_app/scripts/importar_teste.php --fotos-de-exemplo
php -S localhost:8080 -t public_html vasconcelos_app/scripts/servidor_local.php
```
