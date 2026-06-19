-- ============================================================
--  SYSTÈME DE GESTION LOCATIVE — Schema MySQL
--  Compatible XAMPP / MySQL 5.7+
-- ============================================================

CREATE DATABASE IF NOT EXISTS gestion_loyer
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE gestion_loyer;

-- ------------------------------------------------------------
-- Table : locaux
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `locaux` (
    `id`                   INT           NOT NULL AUTO_INCREMENT,
    `nom_local`            VARCHAR(255)  NOT NULL,
    `adresse`              TEXT          NOT NULL,
    `proprietaire`         VARCHAR(255)  NOT NULL,
    `cin_mf_proprietaire`  VARCHAR(100)  NOT NULL DEFAULT '',
    `document_path`        VARCHAR(500)  DEFAULT NULL,
    `created_at`           DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Table : locataires
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `locataires` (
    `id`                         INT             NOT NULL AUTO_INCREMENT,
    `local_id`                   INT             NOT NULL,
    `nom`                        VARCHAR(255)    NOT NULL,
    `cin`                        VARCHAR(50)     NOT NULL DEFAULT '',
    `num_tel`                    VARCHAR(30)     NOT NULL DEFAULT '',
    `mf`                         VARCHAR(100)    NOT NULL DEFAULT '',
    `montant_mensuel`            DECIMAL(10,3)   NOT NULL DEFAULT 0.000,
    `frequence_paiement`         ENUM('mois','trimestre','semestre') NOT NULL DEFAULT 'mois',
    `retenue`                    TINYINT         NOT NULL DEFAULT 0,
    `augmentation_montant`       DECIMAL(10,3)   NOT NULL DEFAULT 0.000,
    `augmentation_periode`       ENUM('annee','deux_ans') NOT NULL DEFAULT 'annee',
    `type_local`                 VARCHAR(100)    NOT NULL DEFAULT '',
    `contrat_path`               VARCHAR(500)    DEFAULT NULL,
    `date_debut`                 DATE            NOT NULL,
    `date_derniere_augmentation` DATE            DEFAULT NULL,
    `actif`                      TINYINT         NOT NULL DEFAULT 1,
    `note`                       TEXT            DEFAULT NULL,
    `charges_additionnelles`     TEXT            DEFAULT NULL,
    `created_at`                 DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    CONSTRAINT `fk_locataire_local`
        FOREIGN KEY (`local_id`) REFERENCES `locaux`(`id`)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Table : archives_contrats
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `archives_contrats` (
    `id`             INT          NOT NULL AUTO_INCREMENT,
    `local_id`       INT          NOT NULL,
    `locataire_data` JSON         NOT NULL,
    `date_archive`   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    CONSTRAINT `fk_archive_local`
        FOREIGN KEY (`local_id`) REFERENCES `locaux`(`id`)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Table : recus_historique
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `recus_historique` (
    `id`               INT           NOT NULL AUTO_INCREMENT,
    `locataire_id`     INT           NOT NULL,
    `local_id`         INT           NOT NULL,
    `mois`             DATE          NOT NULL,
    `montant_brut`     DECIMAL(10,3) NOT NULL DEFAULT 0.000,
    `retenue_montant`  DECIMAL(10,3) NOT NULL DEFAULT 0.000,
    `syndic`           DECIMAL(10,3) NOT NULL DEFAULT 0.000,
    `timbre_fiscal`    DECIMAL(10,3) NOT NULL DEFAULT 1.000,
    `montant_net`      DECIMAL(10,3) NOT NULL DEFAULT 0.000,
    `date_generation`  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    CONSTRAINT `fk_recu_locataire`
        FOREIGN KEY (`locataire_id`) REFERENCES `locataires`(`id`)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_recu_local`
        FOREIGN KEY (`local_id`) REFERENCES `locaux`(`id`)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Données de démonstration
-- ------------------------------------------------------------
INSERT INTO `locaux` (`nom_local`, `adresse`, `proprietaire`, `cin_mf_proprietaire`) VALUES
('Magasin Centre-Ville',   'Avenue Habib Bourguiba, Tunis 1001',     'Ahmed Ben Ali',    '08123456'),
('Appartement Lac',        'Rue du Lac Biwa, Les Berges du Lac 1053','Fatma Trabelsi',   '05987654'),
('Garage Ariana',          'Cité Ennasr II, Ariana 2037',            'Mohamed Chaabane', '1234567/A/M/000'),
('Bureau Menzah',          'Avenue des Jasmins, El Menzah 1004',     'Sonia Khalil',     '07654321');

INSERT INTO `locataires`
    (`local_id`,`nom`,`cin`,`num_tel`,`mf`,`montant_mensuel`,`frequence_paiement`,
     `retenue`,`augmentation_montant`,`augmentation_periode`,`type_local`,`date_debut`,`date_derniere_augmentation`,`actif`)
VALUES
(1,'SARL TechStore Tunis','','+216 71 000 111','1234567/A/P/000',
 850.000,'mois',10,50.000,'annee','Magasin commercial','2024-01-01','2025-01-01',1),
(2,'Karim Mansouri','12345678','+216 98 123 456','',
 650.000,'mois',0,30.000,'annee','Appartement','2023-06-01','2024-06-01',1),
(3,'Entreprise AutoPieces','','+216 71 555 888','9876543/B/M/000',
 300.000,'trimestre',10,20.000,'deux_ans','Garage','2025-03-01','2025-03-01',1),
(4,'Dr. Leila Bouzid','87654321','+216 50 987 654','',
 900.000,'semestre',0,0.000,'annee','Bureau professionnel','2025-01-01','2025-01-01',1);
