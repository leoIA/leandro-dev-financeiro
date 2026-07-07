-- =====================================================================
-- Leandro DEV Financeiro — Módulo NFSe Bahia | Schema v1.0.0
-- Este arquivo é executado APÓS db.sql principal (pelo installer N09).
-- Não usa CREATE DATABASE nem USE — assume que leandro_dev_fin já está selecionado.
-- Engine: InnoDB | Charset: utf8mb4 | Collate: utf8mb4_unicode_ci
-- Compatível: MySQL 8.0+ / MariaDB 10.6+
-- =====================================================================

SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------------------
-- Tabela: provedores_nfse (catálogo de provedores homologados)
-- ---------------------------------------------------------------------
CREATE TABLE `provedores_nfse` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nome` VARCHAR(50) NOT NULL,
  `descricao` VARCHAR(255) NULL,
  `padrao_xml` ENUM('ABRASF','DSF_PROPRIO','SALVADOR_PROPRIO') NOT NULL DEFAULT 'ABRASF',
  `versao_padrao` VARCHAR(10) NOT NULL DEFAULT '2.03',
  `soap_action_emitir` VARCHAR(255) NULL,
  `soap_action_consultar` VARCHAR(255) NULL,
  `soap_action_cancelar` VARCHAR(255) NULL,
  `ativo` TINYINT(1) NOT NULL DEFAULT 1,
  `criado_em` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `atualizado_em` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_provedores_nome` (`nome`),
  KEY `idx_provedores_ativo` (`ativo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Tabela: municipios_nfse (catálogo das 10 maiores cidades BA)
-- ---------------------------------------------------------------------
CREATE TABLE `municipios_nfse` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `codigo_ibge` CHAR(7) NOT NULL,
  `nome` VARCHAR(150) NOT NULL,
  `uf` CHAR(2) NOT NULL DEFAULT 'BA',
  `provedor` ENUM('WEBISS','BETHA','DSF','SALVADOR','SIMPLISS','ISSNET','NAO_SUPORTADO') NOT NULL,
  `endpoint_producao` VARCHAR(255) NOT NULL,
  `endpoint_homologacao` VARCHAR(255) NULL,
  `ativo` TINYINT(1) NOT NULL DEFAULT 1,
  `criado_em` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `atualizado_em` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_mun_codigo_ibge` (`codigo_ibge`),
  KEY `idx_mun_uf` (`uf`),
  KEY `idx_mun_provedor` (`provedor`),
  KEY `idx_mun_ativo` (`ativo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Tabela: nfse (tabela principal — notas fiscais de serviço eletrônicas)
-- ---------------------------------------------------------------------
CREATE TABLE `nfse` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `numero_rps` INT UNSIGNED NOT NULL,
  `serie_rps` VARCHAR(5) NOT NULL DEFAULT '1',
  `tipo_rps` ENUM('RPS','NFSE','MISTA') NOT NULL DEFAULT 'RPS',
  `data_emissao` DATETIME NOT NULL,
  `prestador_cnpj` VARCHAR(20) NOT NULL,
  `prestador_im` VARCHAR(20) NOT NULL,
  `prestador_razao_social` VARCHAR(200) NULL,
  `tomador_tipo_pessoa` ENUM('FISICA','JURIDICA') NOT NULL DEFAULT 'JURIDICA',
  `tomador_nome` VARCHAR(200) NOT NULL,
  `tomador_cnpj_cpf` VARCHAR(20) NULL,
  `tomador_email` VARCHAR(150) NULL,
  `tomador_cep` VARCHAR(10) NULL,
  `tomador_endereco` VARCHAR(200) NULL,
  `tomador_numero` VARCHAR(20) NULL,
  `tomador_complemento` VARCHAR(100) NULL,
  `tomador_bairro` VARCHAR(100) NULL,
  `tomador_cidade` VARCHAR(100) NULL,
  `tomador_uf` CHAR(2) NULL,
  `tomador_codigo_municipio` CHAR(7) NULL,
  `servico_codigo` VARCHAR(10) NOT NULL,
  `servico_descricao` VARCHAR(255) NULL,
  `discriminacao` TEXT NOT NULL,
  `valor_servicos` DECIMAL(15,2) NOT NULL,
  `valor_deducoes` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `valor_base_calculo` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `aliquota` DECIMAL(5,4) NOT NULL DEFAULT 0.0300,
  `valor_iss` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `valor_liquido` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `iss_retido` TINYINT(1) NOT NULL DEFAULT 0,
  `desconto_incondicionado` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `desconto_condicionado` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `municipio_id` INT UNSIGNED NOT NULL,
  `conta_id` INT UNSIGNED NULL,
  `lancamento_id` INT UNSIGNED NULL,
  `ambiente` ENUM('HOMOLOGACAO','PRODUCAO') NOT NULL DEFAULT 'HOMOLOGACAO',
  `numero_nfse` VARCHAR(20) NULL,
  `codigo_verificacao` VARCHAR(255) NULL,
  `protocolo` VARCHAR(50) NULL,
  `status` ENUM('RASCUNHO','PROCESSANDO','AUTORIZADA','REJEITADA','CANCELADA') NOT NULL DEFAULT 'RASCUNHO',
  `xml_envio` LONGTEXT NULL,
  `xml_retorno` LONGTEXT NULL,
  `pdf_path` VARCHAR(255) NULL,
  `mensagem_erro` TEXT NULL,
  `data_autorizacao` DATETIME NULL,
  `data_cancelamento` DATETIME NULL,
  `motivo_cancelamento` TEXT NULL,
  `criado_por` INT UNSIGNED NULL,
  `criado_em` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `atualizado_em` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_nfse_numero_rps` (`numero_rps`),
  KEY `idx_nfse_status` (`status`),
  KEY `idx_nfse_ambiente` (`ambiente`),
  KEY `idx_nfse_data` (`data_emissao`),
  KEY `idx_nfse_municipio` (`municipio_id`),
  KEY `idx_nfse_conta` (`conta_id`),
  KEY `idx_nfse_lancamento` (`lancamento_id`),
  KEY `idx_nfse_criado_por` (`criado_por`),
  CONSTRAINT `fk_nfse_municipio` FOREIGN KEY (`municipio_id`)
    REFERENCES `municipios_nfse` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_nfse_conta` FOREIGN KEY (`conta_id`)
    REFERENCES `contas` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_nfse_lancamento` FOREIGN KEY (`lancamento_id`)
    REFERENCES `lancamentos` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_nfse_criado_por` FOREIGN KEY (`criado_por`)
    REFERENCES `usuarios` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Tabela: nfse_item (itemização do serviço — notas com múltiplos itens)
-- ---------------------------------------------------------------------
CREATE TABLE `nfse_item` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nfse_id` INT UNSIGNED NOT NULL,
  `descricao` VARCHAR(255) NOT NULL,
  `quantidade` DECIMAL(10,2) NOT NULL DEFAULT 1.00,
  `valor_unitario` DECIMAL(15,2) NOT NULL,
  `valor_total` DECIMAL(15,2) NOT NULL,
  `criado_em` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_item_nfse` (`nfse_id`),
  CONSTRAINT `fk_item_nfse` FOREIGN KEY (`nfse_id`)
    REFERENCES `nfse` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Tabela: certificados (A1 .pfx — upload pelo admin)
-- ---------------------------------------------------------------------
CREATE TABLE `certificados` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nome` VARCHAR(100) NOT NULL,
  `arquivo_path` VARCHAR(255) NOT NULL,
  `senha_criptografada` TEXT NOT NULL,
  `validade` DATE NOT NULL,
  `cnpj_titular` VARCHAR(20) NOT NULL,
  `titular_nome` VARCHAR(200) NULL,
  `ativo` TINYINT(1) NOT NULL DEFAULT 1,
  `criado_em` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `atualizado_em` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_cert_cnpj` (`cnpj_titular`),
  KEY `idx_cert_ativo` (`ativo`),
  KEY `idx_cert_validade` (`validade`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- Catálogos (INSERTs — apenas catálogos, zero dado de negócio)
-- =====================================================================

-- ---------------------------------------------------------------------
-- Catálogo: provedores_nfse (4 ativos + 2 stubs não implementados)
-- ---------------------------------------------------------------------
INSERT INTO `provedores_nfse` (`nome`,`descricao`,`padrao_xml`,`versao_padrao`,`soap_action_emitir`,`soap_action_consultar`,`soap_action_cancelar`,`ativo`) VALUES
('WEBISS','WebISS Sistema de Gestão','ABRASF','2.03','http://nfse.abrasf.org.br/EmitirNfse','http://nfse.abrasf.org.br/ConsultarNfse','http://nfse.abrasf.org.br/CancelarNfse',1),
('BETHA','Betha Sistemas','ABRASF','2.03','EmitirNfse','ConsultarNfse','CancelarNfse',1),
('DSF','DSF Sistemas','DSF_PROPRIO','1.00','GerarNfse','ConsultarNfse','CancelarNfse',1),
('SALVADOR','Salvador próprio','SALVADOR_PROPRIO','1.00','gerarNfse','consultarNfse','cancelarNfse',1),
('SIMPLISS','SimplISS (não implementado)','ABRASF','2.03',NULL,NULL,NULL,1),
('ISSNET','ISSNet (não implementado)','ABRASF','2.03',NULL,NULL,NULL,1);

-- ---------------------------------------------------------------------
-- Catálogo: municipios_nfse (10 maiores cidades BA)
-- ---------------------------------------------------------------------
INSERT INTO `municipios_nfse` (`codigo_ibge`,`nome`,`uf`,`provedor`,`endpoint_producao`,`endpoint_homologacao`,`ativo`) VALUES
('2927408','Salvador','BA','SALVADOR','https://nfse.salvador.ba.gov.br/ws/NfseWSService','https://nfsehml.salvador.ba.gov.br/ws/NfseWSService',1),
('2910800','Feira de Santana','BA','WEBISS','https://nfse.feiradesantana.ba.gov.br/webiss/nfseWebService','https://nfsehml.feiradesantana.ba.gov.br/webiss/nfseWebService',1),
('2905701','Camaçari','BA','WEBISS','https://nfse.camacari.ba.gov.br/webiss/nfseWebService','https://nfsehml.camacari.ba.gov.br/webiss/nfseWebService',1),
('2914802','Itabuna','BA','BETHA','https://nfse.itabuna.ba.gov.br/betha/nfse-web-services/nfsews','https://nfsehml.itabuna.ba.gov.br/betha/nfse-web-services/nfsews',1),
('2933307','Vitória da Conquista','BA','WEBISS','https://nfse.conquista.ba.gov.br/webiss/nfseWebService','https://nfsehml.conquista.ba.gov.br/webiss/nfseWebService',1),
('2918407','Juazeiro','BA','WEBISS','https://nfse.juazeiro.ba.gov.br/webiss/nfseWebService','https://nfsehml.juazeiro.ba.gov.br/webiss/nfseWebService',1),
('2913606','Ilhéus','BA','BETHA','https://nfse.ilheus.ba.gov.br/betha/nfse-web-services/nfsews','https://nfsehml.ilheus.ba.gov.br/betha/nfse-web-services/nfsews',1),
('2919207','Lauro de Freitas','BA','WEBISS','https://nfse.laurodefreitas.ba.gov.br/webiss/nfseWebService','https://nfsehml.laurodefreitas.ba.gov.br/webiss/nfseWebService',1),
('2918001','Jequié','BA','BETHA','https://nfse.jequie.ba.gov.br/betha/nfse-web-services/nfsews','https://nfsehml.jequie.ba.gov.br/betha/nfse-web-services/nfsews',1),
('2917900','Teixeira de Freitas','BA','DSF','https://nfse.teixeiradefreitas.ba.gov.br/dsf/nfsews','https://nfsehml.teixeiradefreitas.ba.gov.br/dsf/nfsews',1);

-- ---------------------------------------------------------------------
-- Catálogo: permissoes (módulo nfse — 6 permissões)
-- ---------------------------------------------------------------------
INSERT INTO `permissoes` (`modulo`,`acao`,`descricao`) VALUES
('nfse','create','Emitir NFSe'),
('nfse','read','Visualizar NFSe'),
('nfse','update','Editar NFSe (rascunho)'),
('nfse','delete','Cancelar NFSe'),
('nfse','certificado','Gerenciar certificados'),
('nfse','configuracao','Configurar município e ambiente');

-- ---------------------------------------------------------------------
-- Catálogo: configuracoes (defaults NFSe)
-- ---------------------------------------------------------------------
INSERT INTO `configuracoes` (`chave`,`valor`,`tipo`,`descricao`) VALUES
('nfse_municipio_ativo','2927408','string','Código IBGE do município ativo (default Salvador)'),
('nfse_ambiente','HOMOLOGACAO','string','Ambiente atual (HOMOLOGACAO/PRODUCAO)'),
('nfse_serie_rps','1','string','Série do RPS'),
('nfse_proximo_numero_rps','1','int','Próximo número de RPS a usar'),
('nfse_aliquota_default','0.0300','string','Alíquota padrão (3%)'),
('nfse_servico_codigo_default','7.02','string','Código LC 116 padrão (informática)');

SET FOREIGN_KEY_CHECKS = 1;
-- =====================================================================
-- FIM do schema NFSe. Nenhum dado de negócio é inserido.
-- Tabelas: municipios_nfse, provedores_nfse, nfse, nfse_item, certificados.
-- A ser executado APÓS db.sql principal (installer N09).
-- =====================================================================
