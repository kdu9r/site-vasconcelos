-- Vasconcelos Corretor — esquema do banco (MySQL 5.7+ / MariaDB 10.3+)
-- Importe pelo phpMyAdmin do cPanel (aba "Importar").

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS usuarios (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nome          VARCHAR(100) NOT NULL,
  email         VARCHAR(190) NOT NULL,
  senha_hash    VARCHAR(255) NOT NULL,
  papel         ENUM('admin','corretor') NOT NULL DEFAULT 'corretor',
  creci         VARCHAR(30)  NULL,
  whatsapp      VARCHAR(20)  NULL,
  totp_secret   VARCHAR(64)  NULL,
  totp_ativo    TINYINT(1)   NOT NULL DEFAULT 0,
  sessao_versao INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'sobe ao trocar senha/2 etapas: derruba as outras sessões',
  ativo         TINYINT(1)   NOT NULL DEFAULT 1,
  ultimo_login  DATETIME     NULL,
  criado_em     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_usuarios_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS condominios (
  id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nome           VARCHAR(120) NOT NULL,
  slug           VARCHAR(140) NOT NULL,
  nome_publico   TINYINT(1)   NOT NULL DEFAULT 0 COMMENT '0 = nome e endereço só no painel',
  descricao      TEXT         NULL COMMENT 'texto público, sem nome nem endereço',
  lazer          TEXT         NULL COMMENT 'um item por linha',
  endereco       VARCHAR(150) NULL,
  numero         VARCHAR(20)  NULL,
  bairro         VARCHAR(80)  NULL,
  cidade         VARCHAR(80)  NOT NULL DEFAULT 'Bertioga',
  uf             CHAR(2)      NOT NULL DEFAULT 'SP',
  cep            VARCHAR(9)   NULL,
  latitude       DECIMAL(10,7) NULL,
  longitude      DECIMAL(10,7) NULL,
  code49_id      INT UNSIGNED NULL,
  criado_em      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  atualizado_em  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_condominios_slug (slug),
  UNIQUE KEY uq_condominios_code49 (code49_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS imoveis (
  id                 INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  codigo             VARCHAR(20)  NOT NULL,
  slug               VARCHAR(160) NOT NULL,
  titulo             VARCHAR(150) NOT NULL,
  descricao          TEXT         NULL,
  tipo               VARCHAR(30)  NOT NULL,
  finalidade         ENUM('residencial','comercial','rural') NOT NULL DEFAULT 'residencial',
  preco_venda        DECIMAL(12,2) NULL,
  preco_locacao      DECIMAL(10,2) NULL,
  preco_temporada    DECIMAL(10,2) NULL,
  locacao_pacote     TINYINT(1)   NOT NULL DEFAULT 0 COMMENT 'valor de locação já inclui condomínio/IPTU',
  condominio         DECIMAL(10,2) NULL,
  iptu               DECIMAL(10,2) NULL,
  iptu_periodo       ENUM('mensal','anual') NOT NULL DEFAULT 'mensal',
  quartos            TINYINT UNSIGNED NULL,
  suites             TINYINT UNSIGNED NULL,
  banheiros          TINYINT UNSIGNED NULL,
  vagas              TINYINT UNSIGNED NULL,
  area_util          DECIMAL(10,2) NULL,
  area_total         DECIMAL(10,2) NULL,
  endereco           VARCHAR(150) NULL,
  numero             VARCHAR(20)  NULL,
  complemento        VARCHAR(80)  NULL,
  bairro             VARCHAR(80)  NOT NULL,
  cidade             VARCHAR(80)  NOT NULL DEFAULT 'Bertioga',
  uf                 CHAR(2)      NOT NULL DEFAULT 'SP',
  cep                VARCHAR(9)   NULL,
  latitude           DECIMAL(10,7) NULL,
  longitude          DECIMAL(10,7) NULL,
  exibir_endereco    ENUM('bairro','rua','completo') NOT NULL DEFAULT 'bairro',
  caracteristicas    TEXT         NULL COMMENT 'uma por linha',
  video_url          VARCHAR(255) NULL COMMENT 'link do YouTube',
  destaque_site      TINYINT(1)   NOT NULL DEFAULT 0,
  destaque_portal    ENUM('padrao','destaque','super_destaque') NOT NULL DEFAULT 'padrao',
  publicar_portais   TINYINT(1)   NOT NULL DEFAULT 0 COMMENT 'liga manualmente em cada imóvel',
  status             ENUM('ativo','pausado','vendido','alugado') NOT NULL DEFAULT 'ativo',
  corretor_id        INT UNSIGNED NULL,
  condominio_id      INT UNSIGNED NULL,
  code49_id          INT UNSIGNED NULL,
  code49_atualizado  VARCHAR(30)  NULL,
  url_antiga         VARCHAR(255) NULL,
  criado_em          DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  atualizado_em      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_imoveis_codigo (codigo),
  UNIQUE KEY uq_imoveis_code49 (code49_id),
  KEY ix_imoveis_status (status),
  KEY ix_imoveis_bairro (bairro),
  CONSTRAINT fk_imoveis_corretor FOREIGN KEY (corretor_id) REFERENCES usuarios(id) ON DELETE SET NULL,
  CONSTRAINT fk_imoveis_condominio FOREIGN KEY (condominio_id) REFERENCES condominios(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS condominio_fotos (
  id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  condominio_id  INT UNSIGNED NOT NULL,
  arquivo        VARCHAR(120) NOT NULL,
  largura        SMALLINT UNSIGNED NULL,
  altura         SMALLINT UNSIGNED NULL,
  legenda        VARCHAR(120) NULL,
  ordem          SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  criado_em      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY ix_cfotos (condominio_id, ordem),
  CONSTRAINT fk_cfotos_cond FOREIGN KEY (condominio_id) REFERENCES condominios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS fotos (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  imovel_id    INT UNSIGNED NOT NULL,
  arquivo      VARCHAR(120) NULL COMMENT 'nome base em /uploads/imoveis/{imovel_id}/',
  url_externa  VARCHAR(255) NULL COMMENT 'usada só antes da migração das fotos',
  legenda      VARCHAR(120) NULL,
  largura      SMALLINT UNSIGNED NULL,
  altura       SMALLINT UNSIGNED NULL,
  ordem        SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  condominio_foto_id INT UNSIGNED NULL COMMENT 'foto da área comum: o arquivo fica no condomínio, a ordem é deste imóvel',
  oculta       TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'foto do condomínio escondida neste anúncio',
  criado_em    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY ix_fotos_imovel (imovel_id, ordem),
  UNIQUE KEY uq_fotos_cond (imovel_id, condominio_foto_id),
  CONSTRAINT fk_fotos_imovel FOREIGN KEY (imovel_id) REFERENCES imoveis(id) ON DELETE CASCADE,
  CONSTRAINT fk_fotos_cond FOREIGN KEY (condominio_foto_id) REFERENCES condominio_fotos(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS cliques_whatsapp (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  imovel_id   INT UNSIGNED NULL,
  origem      VARCHAR(20) NOT NULL,
  criado_em   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY ix_cliques_data (criado_em),
  KEY ix_cliques_imovel (imovel_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tentativas_login (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  ip_hash     CHAR(64)     NOT NULL,
  email       VARCHAR(190) NOT NULL,
  sucesso     TINYINT(1)   NOT NULL DEFAULT 0,
  criado_em   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY ix_tentativas_ip (ip_hash, criado_em),
  KEY ix_tentativas_email (email, criado_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS registro_atividades (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  usuario_id  INT UNSIGNED NULL,
  acao        VARCHAR(60)  NOT NULL,
  detalhe     VARCHAR(255) NULL,
  ip_hash     CHAR(64)     NULL,
  criado_em   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY ix_atividades_data (criado_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS posts (
  id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  slug           VARCHAR(160) NOT NULL,
  titulo         VARCHAR(150) NOT NULL,
  resumo         VARCHAR(220) NULL,
  conteudo       MEDIUMTEXT   NULL,
  capa           VARCHAR(120) NULL COMMENT 'nome base em /uploads/blog/{id}/',
  bairro         VARCHAR(80)  NULL COMMENT 'mostra imóveis desse bairro no fim do post',
  status         ENUM('rascunho','publicado') NOT NULL DEFAULT 'rascunho',
  publicado_em   DATETIME     NULL,
  autor_id       INT UNSIGNED NULL,
  criado_em      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  atualizado_em  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_posts_slug (slug),
  KEY ix_posts_status (status, publicado_em),
  CONSTRAINT fk_posts_autor FOREIGN KEY (autor_id) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



CREATE TABLE IF NOT EXISTS configuracoes (
  chave          VARCHAR(60)  NOT NULL PRIMARY KEY,
  valor          VARCHAR(500) NOT NULL,
  atualizado_em  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
