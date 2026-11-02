-- Base dédiée aux tests automatisés (php artisan test).
-- Exécuté automatiquement par l'image MySQL au premier démarrage du volume.
CREATE DATABASE IF NOT EXISTS stive_clothing_test
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

GRANT ALL PRIVILEGES ON stive_clothing_test.* TO 'stive'@'%';
FLUSH PRIVILEGES;
