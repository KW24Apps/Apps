-- Script SQL para criar a tabela batch_jobs
-- Execute este script no seu banco de dados MySQL

CREATE TABLE IF NOT EXISTS `batch_jobs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `job_id` varchar(255) NOT NULL UNIQUE,
  `tipo` varchar(100) NOT NULL,
  `status` enum('pendente','processando','concluido','erro') NOT NULL DEFAULT 'pendente',
  `dados_entrada` longtext,
  `total_items` int(11) DEFAULT 0,
  `items_processados` int(11) DEFAULT 0,
  `resultado` longtext,
  `erro_mensagem` text,
  `criado_em` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `iniciado_em` datetime DEFAULT NULL,
  `concluido_em` datetime DEFAULT NULL,
  `atualizado_em` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_job_id` (`job_id`),
  KEY `idx_status` (`status`),
  KEY `idx_tipo` (`tipo`),
  KEY `idx_criado_em` (`criado_em`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
