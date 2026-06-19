-- ============================================================
--  Migration : ajout de la colonne charges_additionnelles
--  À exécuter UNIQUEMENT si votre base de données existe déjà
--  (sinon, schema.sql contient déjà cette colonne pour une
--  nouvelle installation).
--  Compatible MySQL 5.7+ / 8.0 — sans erreur si déjà appliquée.
-- ============================================================

USE gestion_loyer;

SET @col_exists = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME   = 'locataires'
      AND COLUMN_NAME  = 'charges_additionnelles'
);

SET @sql = IF(
    @col_exists = 0,
    'ALTER TABLE `locataires` ADD COLUMN `charges_additionnelles` TEXT DEFAULT NULL AFTER `note`',
    'SELECT "La colonne charges_additionnelles existe déjà — rien à faire." AS message'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
