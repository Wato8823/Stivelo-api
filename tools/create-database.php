<?php

/**
 * Crée une base MySQL (et, au besoin, en accorde l'accès à l'utilisateur de
 * l'application). Utile quand le client `mysql` n'est pas disponible dans le
 * PATH — cas fréquent sur Windows avec Laragon/XAMPP.
 *
 *   php tools/create-database.php stive_clothing
 *   php tools/create-database.php stive_clothing_test stive
 *
 * Connexion administrateur : variables d'environnement, avec repli sur .env/
 *   DB_HOST (127.0.0.1), DB_PORT (3306)
 *   DB_ADMIN_USER (root), DB_ADMIN_PASSWORD ('')
 *
 * Exemple avec un mot de passe root :
 *   DB_ADMIN_PASSWORD=monmotdepasse php tools/create-database.php stive_clothing_test stive
 */

declare(strict_types=1);

/** Lit une variable du .env (sans charger tout Laravel). */
function envValue(string $key, string $default = ''): string
{
    $value = $_SERVER[$key] ?? $_ENV[$key] ?? getenv($key);

    if ($value !== false && $value !== null && $value !== '') {
        return (string) $value;
    }

    static $dotenv = null;

    if ($dotenv === null) {
        $dotenv = [];
        $path = dirname(__DIR__).'/.env';

        if (is_file($path)) {
            foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
                $line = trim($line);

                if ($line === '' || str_starts_with($line, '#') || ! str_contains($line, '=')) {
                    continue;
                }

                [$name, $raw] = explode('=', $line, 2);
                $dotenv[trim($name)] = trim($raw, " \t\"'");
            }
        }
    }

    return $dotenv[$key] ?? $default;
}

$database = $argv[1] ?? null;
$owner = $argv[2] ?? null;

if ($database === null) {
    fwrite(STDERR, "Usage : php tools/create-database.php <base> [utilisateur_proprietaire]\n");
    exit(1);
}

if (! preg_match('/^[A-Za-z0-9_]+$/', $database)) {
    fwrite(STDERR, "Nom de base invalide : lettres, chiffres et underscores uniquement.\n");
    exit(1);
}

if ($owner !== null && ! preg_match('/^[A-Za-z0-9_.\-]+$/', $owner)) {
    fwrite(STDERR, "Nom d'utilisateur invalide.\n");
    exit(1);
}

$host = envValue('DB_HOST', '127.0.0.1');
$port = envValue('DB_PORT', '3306');
$socket = envValue('DB_SOCKET');
$adminUser = envValue('DB_ADMIN_USER', 'root');
$adminPassword = envValue('DB_ADMIN_PASSWORD');

$dsn = $socket !== ''
    ? "mysql:unix_socket={$socket}"
    : "mysql:host={$host};port={$port}";

try {
    $pdo = new PDO($dsn, $adminUser, $adminPassword, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_TIMEOUT => 5,
    ]);
} catch (PDOException $exception) {
    fwrite(STDERR, "Connexion impossible en tant que « {$adminUser} » sur {$host}:{$port}\n");
    fwrite(STDERR, $exception->getMessage()."\n");
    fwrite(STDERR, "Renseignez DB_ADMIN_USER / DB_ADMIN_PASSWORD (ex. DB_ADMIN_PASSWORD=secret).\n");
    exit(1);
}

try {
    $pdo->exec(sprintf(
        'CREATE DATABASE IF NOT EXISTS `%s` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci',
        $database,
    ));
    echo "Base « {$database} » prête (utf8mb4).\n";

    if ($owner !== null) {
        foreach (['%', 'localhost'] as $hostPart) {
            $pdo->exec(sprintf(
                "GRANT ALL PRIVILEGES ON `%s`.* TO '%s'@'%s'",
                $database,
                $owner,
                $hostPart,
            ));
        }

        $pdo->exec('FLUSH PRIVILEGES');
        echo "Droits accordés à « {$owner} » sur « {$database} ».\n";
    }
} catch (PDOException $exception) {
    fwrite(STDERR, "Échec de la création : ".$exception->getMessage()."\n");
    fwrite(STDERR, "L'utilisateur « {$adminUser} » n'a peut-être pas les droits nécessaires.\n");
    exit(1);
}
