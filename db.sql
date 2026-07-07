-- =====================================================================
-- Leandro DEV Financeiro — MM Construtora
-- Schema versão 1.0.0
-- Engine: InnoDB | Charset: utf8mb4 | Collate: utf8mb4_unicode_ci
-- Compatível: MySQL 8.0+ / MariaDB 10.6+
-- =====================================================================

CREATE DATABASE IF NOT EXISTS `leandro_dev_fin`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE `leandro_dev_fin`;

SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------------------
-- Tabela: configuracoes (key-value global)
-- ---------------------------------------------------------------------
CREATE TABLE `configuracoes` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `chave` VARCHAR(100) NOT NULL,
  `valor` TEXT NULL,
  `tipo` ENUM('string','int','bool','json','date','datetime') NOT NULL DEFAULT 'string',
  `descricao` VARCHAR(255) NULL,
  `atualizado_em` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_configuracoes_chave` (`chave`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Tabela: usuarios
-- ---------------------------------------------------------------------
CREATE TABLE `usuarios` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nome` VARCHAR(150) NOT NULL,
  `email` VARCHAR(150) NOT NULL,
  `senha_hash` VARCHAR(255) NOT NULL,
  `perfil` ENUM('ADMIN','OPERADOR','VISUALIZADOR') NOT NULL DEFAULT 'OPERADOR',
  `ativo` TINYINT(1) NOT NULL DEFAULT 1,
  `ultimo_acesso` DATETIME NULL,
  `tentativas_login` INT UNSIGNED NOT NULL DEFAULT 0,
  `bloqueado_ate` DATETIME NULL,
  `foto_path` VARCHAR(255) NULL,
  `criado_em` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `atualizado_em` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_usuarios_email` (`email`),
  KEY `idx_usuarios_perfil` (`perfil`),
  KEY `idx_usuarios_ativo` (`ativo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Tabela: permissoes (catálogo)
-- ---------------------------------------------------------------------
CREATE TABLE `permissoes` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `modulo` VARCHAR(50) NOT NULL,
  `acao` VARCHAR(50) NOT NULL,
  `descricao` VARCHAR(150) NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_permissoes_modulo_acao` (`modulo`,`acao`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Tabela: usuario_permissoes
-- ---------------------------------------------------------------------
CREATE TABLE `usuario_permissoes` (
  `usuario_id` INT UNSIGNED NOT NULL,
  `permissao_id` INT UNSIGNED NOT NULL,
  PRIMARY KEY (`usuario_id`,`permissao_id`),
  KEY `idx_up_usuario` (`usuario_id`),
  KEY `idx_up_permissao` (`permissao_id`),
  CONSTRAINT `fk_up_usuario` FOREIGN KEY (`usuario_id`)
    REFERENCES `usuarios` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_up_permissao` FOREIGN KEY (`permissao_id`)
    REFERENCES `permissoes` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Tabela: contas (bancárias/caixa/ASAAS)
-- ---------------------------------------------------------------------
CREATE TABLE `contas` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nome` VARCHAR(100) NOT NULL,
  `tipo` ENUM('BANCO','CAIXA','ASAAS','CARTEIRA','OUTRO') NOT NULL DEFAULT 'BANCO',
  `instituicao` VARCHAR(150) NULL,
  `agencia` VARCHAR(20) NULL,
  `conta_numero` VARCHAR(30) NULL,
  `saldo_inicial` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `descricao` TEXT NULL,
  `ativo` TINYINT(1) NOT NULL DEFAULT 1,
  `criado_em` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `atualizado_em` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_contas_nome` (`nome`),
  KEY `idx_contas_tipo` (`tipo`),
  KEY `idx_contas_ativo` (`ativo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Tabela: plano_contas (auto-relacionamento hierárquico)
-- ---------------------------------------------------------------------
CREATE TABLE `plano_contas` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `parent_id` INT UNSIGNED NULL,
  `codigo` VARCHAR(50) NOT NULL,
  `nome` VARCHAR(150) NOT NULL,
  `tipo` ENUM('RECEITA','DESPESA','NEUTRO') NOT NULL DEFAULT 'NEUTRO',
  `nivel` TINYINT UNSIGNED NOT NULL DEFAULT 1,
  `ordem` INT NOT NULL DEFAULT 0,
  `ativo` TINYINT(1) NOT NULL DEFAULT 1,
  `criado_em` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `atualizado_em` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_plano_codigo` (`codigo`),
  KEY `idx_plano_parent` (`parent_id`),
  KEY `idx_plano_tipo` (`tipo`),
  KEY `idx_plano_ativo` (`ativo`),
  CONSTRAINT `fk_plano_parent` FOREIGN KEY (`parent_id`)
    REFERENCES `plano_contas` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Tabela: clientes_fornecedores
-- ---------------------------------------------------------------------
CREATE TABLE `clientes_fornecedores` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tipo` ENUM('CLIENTE','FORNECEDOR','AMBOS') NOT NULL DEFAULT 'CLIENTE',
  `tipo_pessoa` ENUM('FISICA','JURIDICA') NOT NULL DEFAULT 'FISICA',
  `nome_razao_social` VARCHAR(200) NOT NULL,
  `cpf_cnpj` VARCHAR(20) NULL,
  `email` VARCHAR(150) NULL,
  `telefone` VARCHAR(20) NULL,
  `celular` VARCHAR(20) NULL,
  `cep` VARCHAR(10) NULL,
  `endereco` VARCHAR(200) NULL,
  `numero` VARCHAR(20) NULL,
  `complemento` VARCHAR(100) NULL,
  `bairro` VARCHAR(100) NULL,
  `cidade` VARCHAR(100) NULL,
  `uf` CHAR(2) NULL,
  `observacao` TEXT NULL,
  `ativo` TINYINT(1) NOT NULL DEFAULT 1,
  `criado_em` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `atualizado_em` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_cf_cpf_cnpj_tipo` (`cpf_cnpj`,`tipo_pessoa`),
  KEY `idx_cf_tipo` (`tipo`),
  KEY `idx_cf_nome` (`nome_razao_social`),
  KEY `idx_cf_ativo` (`ativo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Tabela: lancamentos
-- ---------------------------------------------------------------------
CREATE TABLE `lancamentos` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `conta_id` INT UNSIGNED NOT NULL,
  `plano_conta_id` INT UNSIGNED NOT NULL,
  `cliente_fornecedor_id` INT UNSIGNED NULL,
  `transferencia_id` INT UNSIGNED NULL,
  `conta_programada_id` INT UNSIGNED NULL,
  `tipo` ENUM('RECEITA','DESPESA','TRANSFERENCIA') NOT NULL,
  `valor` DECIMAL(15,2) NOT NULL,
  `data_lancamento` DATE NOT NULL,
  `data_vencimento` DATE NULL,
  `data_pagamento` DATE NULL,
  `descricao` VARCHAR(255) NOT NULL,
  `observacao` TEXT NULL,
  `status` ENUM('PENDENTE','PAGO','CANCELADO') NOT NULL DEFAULT 'PENDENTE',
  `forma_pagamento` ENUM('DINHEIRO','PIX','CARTAO','BOLETO','TRANSFERENCIA','OUTRO') NULL,
  `documento` VARCHAR(100) NULL,
  `criado_por` INT UNSIGNED NULL,
  `criado_em` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `atualizado_em` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_lanc_conta` (`conta_id`),
  KEY `idx_lanc_plano` (`plano_conta_id`),
  KEY `idx_lanc_cf` (`cliente_fornecedor_id`),
  KEY `idx_lanc_transferencia` (`transferencia_id`),
  KEY `idx_lanc_programada` (`conta_programada_id`),
  KEY `idx_lanc_tipo` (`tipo`),
  KEY `idx_lanc_status` (`status`),
  KEY `idx_lanc_data` (`data_lancamento`),
  KEY `idx_lanc_vencimento` (`data_vencimento`),
  KEY `idx_lanc_criado_por` (`criado_por`),
  CONSTRAINT `fk_lanc_conta` FOREIGN KEY (`conta_id`)
    REFERENCES `contas` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_lanc_plano` FOREIGN KEY (`plano_conta_id`)
    REFERENCES `plano_contas` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_lanc_cf` FOREIGN KEY (`cliente_fornecedor_id`)
    REFERENCES `clientes_fornecedores` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_lanc_criado_por` FOREIGN KEY (`criado_por`)
    REFERENCES `usuarios` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Tabela: transferencias (orquestra duplo lançamento)
-- ---------------------------------------------------------------------
CREATE TABLE `transferencias` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `conta_origem_id` INT UNSIGNED NOT NULL,
  `conta_destino_id` INT UNSIGNED NOT NULL,
  `valor` DECIMAL(15,2) NOT NULL,
  `data_transferencia` DATE NOT NULL,
  `observacao` TEXT NULL,
  `criado_por` INT UNSIGNED NULL,
  `criado_em` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_trans_origem` (`conta_origem_id`),
  KEY `idx_trans_destino` (`conta_destino_id`),
  KEY `idx_trans_data` (`data_transferencia`),
  CONSTRAINT `fk_trans_origem` FOREIGN KEY (`conta_origem_id`)
    REFERENCES `contas` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_trans_destino` FOREIGN KEY (`conta_destino_id`)
    REFERENCES `contas` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_trans_criado_por` FOREIGN KEY (`criado_por`)
    REFERENCES `usuarios` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- FK circular: lancamentos.transferencia_id -> transferencias.id
ALTER TABLE `lancamentos`
  ADD CONSTRAINT `fk_lanc_transferencia` FOREIGN KEY (`transferencia_id`)
  REFERENCES `transferencias` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE;

-- ---------------------------------------------------------------------
-- Tabela: contas_programadas (recorrências)
-- ---------------------------------------------------------------------
CREATE TABLE `contas_programadas` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `descricao` VARCHAR(255) NOT NULL,
  `conta_id` INT UNSIGNED NOT NULL,
  `plano_conta_id` INT UNSIGNED NOT NULL,
  `cliente_fornecedor_id` INT UNSIGNED NULL,
  `tipo` ENUM('RECEITA','DESPESA') NOT NULL,
  `valor` DECIMAL(15,2) NOT NULL,
  `data_inicio` DATE NOT NULL,
  `data_termino` DATE NULL,
  `frequencia` ENUM('DIARIO','SEMANAL','MENSAL','ANUAL','PERSONALIZADO') NOT NULL DEFAULT 'MENSAL',
  `dia_referencia` INT NULL COMMENT 'Dia do mês (1-31) ou semana (0-6)',
  `parcelas_total` INT UNSIGNED NULL COMMENT 'NULL = ilimitado até data_termino',
  `parcelas_geradas` INT UNSIGNED NOT NULL DEFAULT 0,
  `forma_pagamento` ENUM('DINHEIRO','PIX','CARTAO','BOLETO','TRANSFERENCIA','OUTRO') NULL,
  `ativo` TINYINT(1) NOT NULL DEFAULT 1,
  `ultima_geracao` DATE NULL,
  `proxima_geracao` DATE NULL,
  `criado_em` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `atualizado_em` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_cp_conta` (`conta_id`),
  KEY `idx_cp_plano` (`plano_conta_id`),
  KEY `idx_cp_cf` (`cliente_fornecedor_id`),
  KEY `idx_cp_ativo` (`ativo`),
  KEY `idx_cp_proxima` (`proxima_geracao`),
  CONSTRAINT `fk_cp_conta` FOREIGN KEY (`conta_id`)
    REFERENCES `contas` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_cp_plano` FOREIGN KEY (`plano_conta_id`)
    REFERENCES `plano_contas` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_cp_cf` FOREIGN KEY (`cliente_fornecedor_id`)
    REFERENCES `clientes_fornecedores` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `lancamentos`
  ADD CONSTRAINT `fk_lanc_programada` FOREIGN KEY (`conta_programada_id`)
  REFERENCES `contas_programadas` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

-- ---------------------------------------------------------------------
-- Tabela: logs_auditoria
-- ---------------------------------------------------------------------
CREATE TABLE `logs_auditoria` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `usuario_id` INT UNSIGNED NULL,
  `acao` ENUM('CREATE','UPDATE','DELETE','LOGIN','LOGOUT','LOGIN_FALHA','BACKUP','RESTORE','INSTALL') NOT NULL,
  `modulo` VARCHAR(50) NOT NULL,
  `registro_id` INT UNSIGNED NULL,
  `dados_anteriores` JSON NULL,
  `dados_novos` JSON NULL,
  `ip` VARCHAR(45) NULL,
  `user_agent` VARCHAR(255) NULL,
  `criado_em` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_audit_usuario` (`usuario_id`),
  KEY `idx_audit_acao` (`acao`),
  KEY `idx_audit_modulo` (`modulo`),
  KEY `idx_audit_data` (`criado_em`),
  CONSTRAINT `fk_audit_usuario` FOREIGN KEY (`usuario_id`)
    REFERENCES `usuarios` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Tabela: logs_tentativas_login (rate limiting)
-- ---------------------------------------------------------------------
CREATE TABLE `logs_tentativas_login` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `email` VARCHAR(150) NOT NULL,
  `ip` VARCHAR(45) NOT NULL,
  `sucesso` TINYINT(1) NOT NULL DEFAULT 0,
  `criado_em` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_tent_email` (`email`),
  KEY `idx_tent_ip` (`ip`),
  KEY `idx_tent_data` (`criado_em`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Tabela: schema_version (controle de migrations futuras)
-- ---------------------------------------------------------------------
CREATE TABLE `schema_version` (
  `versao` VARCHAR(20) NOT NULL,
  `aplicada_em` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`versao`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `schema_version` (`versao`) VALUES ('1.0.0');

-- ---------------------------------------------------------------------
-- Catálogo de permissões (sem usuários — apenas catálogo)
-- ---------------------------------------------------------------------
INSERT INTO `permissoes` (`modulo`,`acao`,`descricao`) VALUES
('contas','create','Criar contas'),
('contas','read','Visualizar contas'),
('contas','update','Editar contas'),
('contas','delete','Desativar contas'),
('plano_contas','create','Criar plano de contas'),
('plano_contas','read','Visualizar plano de contas'),
('plano_contas','update','Editar plano de contas'),
('plano_contas','delete','Desativar plano de contas'),
('lancamentos','create','Criar lançamentos'),
('lancamentos','read','Visualizar lançamentos'),
('lancamentos','update','Editar lançamentos'),
('lancamentos','delete','Cancelar lançamentos'),
('contas_programadas','create','Criar contas programadas'),
('contas_programadas','read','Visualizar contas programadas'),
('contas_programadas','update','Editar contas programadas'),
('contas_programadas','delete','Desativar contas programadas'),
('contas_programadas','gerar','Forçar geração de lançamentos'),
('transferencias','create','Realizar transferências'),
('transferencias','read','Visualizar transferências'),
('clientes_fornecedores','create','Criar clientes/fornecedores'),
('clientes_fornecedores','read','Visualizar clientes/fornecedores'),
('clientes_fornecedores','update','Editar clientes/fornecedores'),
('clientes_fornecedores','delete','Desativar clientes/fornecedores'),
('relatorios','read','Visualizar relatórios'),
('relatorios','export','Exportar relatórios'),
('usuarios','create','Criar usuários'),
('usuarios','read','Visualizar usuários'),
('usuarios','update','Editar usuários'),
('usuarios','delete','Desativar usuários'),
('configuracoes','read','Visualizar configurações'),
('configuracoes','update','Editar configurações'),
('backups','create','Gerar backup'),
('backups','read','Listar backups'),
('backups','restore','Restaurar backup'),
('backups','delete','Excluir backup');

-- ---------------------------------------------------------------------
-- Configurações padrão (sem dados de negócio — só defaults)
-- ---------------------------------------------------------------------
INSERT INTO `configuracoes` (`chave`,`valor`,`tipo`,`descricao`) VALUES
('empresa_razao_social','MM Construtora','string','Razão social'),
('empresa_cnpj','','string','CNPJ'),
('empresa_logo_path','','string','Caminho do logo'),
('sistema_nome','Leandro DEV Financeiro','string','Nome do sistema'),
('moeda','BRL','string','Código ISO da moeda'),
('locale','pt_BR','string','Locale do sistema'),
('fuso_horario','America/Sao_Paulo','string','Fuso horário'),
('session_timeout_min','30','int','Minutos de inatividade antes de lock'),
('tentativas_login_max','5','int','Tentativas antes de bloquear'),
('bloqueio_login_min','15','int','Minutos de bloqueio após exceder tentativas'),
('backup_automatico','0','bool','Backup automático diário'),
('backup_manter_ultimos','10','int','Quantidade de backups a manter'),
('backup_dia_mes','1','int','Dia do mês para backup automático'),
('schema_version','1.0.0','string','Versão do schema instalado');

SET FOREIGN_KEY_CHECKS = 1;
-- =====================================================================
-- FIM do schema. Nenhum dado de negócio é inserido.
-- O admin default é criado pelo installer via PHP (password_hash).
-- =====================================================================
