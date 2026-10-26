-- Prospec database schema (MySQL/MariaDB). Import with phpMyAdmin or the mysql CLI.
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS leads (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  place_id VARCHAR(191) NOT NULL,
  nome VARCHAR(200) NOT NULL DEFAULT '',
  endereco VARCHAR(300) NOT NULL DEFAULT '',
  telefone VARCHAR(40) NOT NULL DEFAULT '',
  site VARCHAR(300) NOT NULL DEFAULT '',
  nota DECIMAL(2,1) NOT NULL DEFAULT 0,
  avaliacoes INT UNSIGNED NOT NULL DEFAULT 0,
  nicho VARCHAR(100) NOT NULL DEFAULT '',
  cidade VARCHAR(100) NOT NULL DEFAULT '',
  perfil VARCHAR(20) NOT NULL DEFAULT '',
  motivos VARCHAR(300) NOT NULL DEFAULT '',
  score INT NOT NULL DEFAULT 0,
  status VARCHAR(20) NOT NULL DEFAULT 'novo',
  dono VARCHAR(40) NULL DEFAULT NULL,
  criado DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uk_place (place_id),
  KEY idx_dono_status (dono, status),
  KEY idx_ordem (score, avaliacoes),
  KEY idx_cidade (cidade),
  KEY idx_nicho (nicho)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS leads_resp (
  lead_id INT UNSIGNED NOT NULL,
  nome VARCHAR(100) NOT NULL DEFAULT '',
  telefone VARCHAR(40) NOT NULL DEFAULT '',
  atualizado DATETIME NOT NULL,
  PRIMARY KEY (lead_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS lead_log (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  lead_id INT UNSIGNED NOT NULL,
  usuario VARCHAR(40) NOT NULL,
  acao VARCHAR(30) NOT NULL,
  detalhe TEXT NULL,
  criado DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY idx_lead_acao (lead_id, acao)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS buscas (
  nicho VARCHAR(100) NOT NULL,
  cidade VARCHAR(100) NOT NULL,
  pagina INT UNSIGNED NOT NULL DEFAULT 0,
  atualizado DATETIME NOT NULL,
  PRIMARY KEY (nicho, cidade)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS uso_busca (
  usuario VARCHAR(40) NOT NULL,
  dia DATE NOT NULL,
  qtd INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (usuario, dia)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS senhas (
  usuario VARCHAR(40) NOT NULL,
  hash VARCHAR(255) NOT NULL,
  atualizado DATETIME NOT NULL,
  PRIMARY KEY (usuario)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS login_tentativas (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  ip VARCHAR(45) NOT NULL,
  usuario VARCHAR(40) NOT NULL,
  criado DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY idx_criado (criado),
  KEY idx_ip (ip),
  KEY idx_usuario (usuario)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
