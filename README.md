# Site Vasconcelos Corretor

Site de imóveis com painel para os corretores (PHP 8.1+ e MySQL, sem dependências), feito para a hospedagem HostGator.

- **Instalação, migração da Code49, CanalPro e virada do domínio:** [LEIA-ME.md](LEIA-ME.md)
- **Publicação automática:** cada alteração na branch `main` é enviada para a HostGator por FTP ([.github/workflows/publicar.yml](.github/workflows/publicar.yml)). Configure os segredos `FTP_SERVER`, `FTP_USERNAME`, `FTP_PASSWORD` e a variável `DEPLOY_DIR` em *Settings → Secrets and variables → Actions*.

O `config.php` (senhas do banco e chaves) e as fotos enviadas pelo painel ficam só no servidor e nunca vão para o GitHub.
