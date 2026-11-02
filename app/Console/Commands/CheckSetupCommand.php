<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use PDO;
use PDOException;
use SimpleXMLElement;
use Throwable;

/**
 * Diagnostic de l'environnement local :
 *
 *   php artisan stive:check-setup
 *
 * Vérifie les extensions PHP, la clé d'application, les droits d'écriture,
 * la connexion à la base de développement et l'existence de la base de test
 * utilisée par `php artisan test`. Chaque échec est accompagné de la commande
 * exacte à exécuter pour le corriger.
 */
class CheckSetupCommand extends Command
{
    protected $signature = 'stive:check-setup';

    protected $description = 'Vérifie les prérequis locaux (PHP, .env, base de développement et base de test)';

    /**
     * Extensions indispensables au fonctionnement de l'API.
     *
     * @var list<string>
     */
    private const REQUIRED_EXTENSIONS = [
        'pdo',
        'pdo_mysql',
        'mbstring',
        'openssl',
        'fileinfo',
        'ctype',
        'json',
        'tokenizer',
        'xml',
        'curl',
    ];

    /**
     * Extensions utiles mais non bloquantes.
     *
     * @var array<string, string>
     */
    private const OPTIONAL_EXTENSIONS = [
        'gd' => 'téléversement des photos produits et tests d’images',
        'zip' => 'installation des dépendances par Composer',
        'intl' => 'formatage avancé des nombres et des dates',
    ];

    private int $failures = 0;

    private int $warnings = 0;

    public function handle(): int
    {
        $this->newLine();
        $this->line('<options=bold>Diagnostic de l’environnement — API Stive Clothing</options=bold>');
        $this->newLine();

        $this->checkPhp();
        $this->checkApplication();
        $this->checkDevelopmentDatabase();
        $this->checkTestDatabase();

        $this->newLine();

        if ($this->failures > 0) {
            $this->line(sprintf('<error>%d point(s) bloquant(s)</error>, %d avertissement(s).', $this->failures, $this->warnings));
            $this->line('Corrigez les lignes marquées « ÉCHEC » puis relancez cette commande.');

            return self::FAILURE;
        }

        $this->line(sprintf('<info>Environnement prêt</info> — %d avertissement(s).', $this->warnings));
        $this->line('Vous pouvez lancer : <options=bold>php artisan test</options=bold>');

        return self::SUCCESS;
    }

    private function checkPhp(): void
    {
        $this->line('<options=bold>1. PHP</options=bold>');

        $ok = version_compare(PHP_VERSION, '8.2.0', '>=');
        $this->report('Version de PHP', $ok, PHP_VERSION.' (8.2 minimum)');

        foreach (self::REQUIRED_EXTENSIONS as $extension) {
            $loaded = extension_loaded($extension);

            $this->report(
                'Extension '.$extension,
                $loaded,
                $loaded
                    ? 'chargée'
                    : 'absente — décommentez « extension='.$extension.' » dans php.ini puis rouvrez le terminal',
            );
        }

        foreach (self::OPTIONAL_EXTENSIONS as $extension => $usage) {
            if (extension_loaded($extension)) {
                $this->line('  <info>[OK]</info>    Extension '.$extension);
                continue;
            }

            $this->warnings++;
            $this->line('  <comment>[INFO]</comment>  Extension '.$extension.' absente — utile pour '.$usage);
        }

        $this->newLine();
    }

    private function checkApplication(): void
    {
        $this->line('<options=bold>2. Application</options=bold>');

        $key = (string) config('app.key');
        $this->report(
            'Clé APP_KEY',
            $key !== '',
            $key !== '' ? 'définie' : 'absente — exécutez « php artisan key:generate »',
        );

        $this->report(
            'Fichier .env',
            is_file(base_path('.env')),
            is_file(base_path('.env'))
                ? 'présent'
                : 'absent — copiez .env.example vers .env puis générez la clé',
        );

        foreach (['storage', 'bootstrap/cache'] as $directory) {
            $path = base_path($directory);
            $writable = is_dir($path) && is_writable($path);

            $this->report(
                'Écriture dans '.$directory.'/',
                $writable,
                $writable ? 'autorisée' : 'refusée — vérifiez les droits du dossier',
            );
        }

        if (is_file(base_path('.env.testing'))) {
            $this->line('  <info>[OK]</info>    .env.testing détecté (identifiants de test dédiés)');
        }

        $this->newLine();
    }

    private function checkDevelopmentDatabase(): void
    {
        $this->line('<options=bold>3. Base de développement</options=bold>');

        $driver = (string) config('database.default');
        $database = (string) config("database.connections.{$driver}.database");

        try {
            DB::connection()->getPdo();
            $this->report('Connexion ('.$driver.')', true, $database.' accessible');
        } catch (Throwable $exception) {
            $this->report('Connexion ('.$driver.')', false, $this->explainDatabaseError($exception, $database));
        }

        $this->newLine();
    }

    private function checkTestDatabase(): void
    {
        $this->line('<options=bold>4. Base de test (php artisan test)</options=bold>');

        $database = $this->testDatabaseName();
        [$connected, $message] = $this->connectTo($database);

        $this->report('Base « '.$database.' »', $connected, $message);

        if (! $connected) {
            $driver = (string) config('database.default');
            $username = (string) config("database.connections.{$driver}.username");
            $host = (string) config("database.connections.{$driver}.host", '127.0.0.1');

            $this->newLine();
            $this->line('  Pour la créer :');
            $this->line('    <options=bold>mysql -u '.$username.' -p -e "CREATE DATABASE '.$database
                .' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"</options=bold>');
            $this->line('    <options=bold>GRANT ALL PRIVILEGES ON '.$database.'.* TO \''.$username.'\'@\''.$host.'\';</options=bold>');
            $this->line('  Ou, si vous utilisez Docker : <options=bold>docker compose up -d db</options=bold> (base créée automatiquement).');
        }

        $this->newLine();
    }

    /**
     * Nom de la base utilisée par PHPUnit : lu dans phpunit.xml, avec repli
     * sur la convention « <base>_test ».
     */
    private function testDatabaseName(): string
    {
        $path = base_path('phpunit.xml');

        if (is_file($path)) {
            try {
                $xml = simplexml_load_file($path);

                if ($xml instanceof SimpleXMLElement) {
                    foreach ($xml->php->env ?? [] as $env) {
                        if ((string) $env['name'] === 'DB_DATABASE' && (string) $env['value'] !== '') {
                            return (string) $env['value'];
                        }
                    }
                }
            } catch (Throwable) {
                // phpunit.xml illisible : on retombe sur la convention.
            }
        }

        $driver = (string) config('database.default');

        return ((string) config("database.connections.{$driver}.database")).'_test';
    }

    /**
     * @return array{0: bool, 1: string}
     */
    private function connectTo(string $database): array
    {
        $driver = (string) config('database.default');
        $config = (array) config("database.connections.{$driver}", []);

        if (! in_array($driver, ['mysql', 'mariadb'], true)) {
            return [true, 'vérification non applicable au pilote « '.$driver.' »'];
        }

        $socket = (string) ($config['socket'] ?? '');
        $dsn = $socket !== ''
            ? sprintf('mysql:unix_socket=%s;dbname=%s;charset=utf8mb4', $socket, $database)
            : sprintf(
                'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
                (string) ($config['host'] ?? '127.0.0.1'),
                (string) ($config['port'] ?? 3306),
                $database,
            );

        try {
            new PDO(
                $dsn,
                (string) ($config['username'] ?? ''),
                (string) ($config['password'] ?? ''),
                [PDO::ATTR_TIMEOUT => 5, PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
            );

            return [true, 'accessible avec l’utilisateur « '.($config['username'] ?? '').' »'];
        } catch (PDOException $exception) {
            return [false, $this->explainDatabaseError($exception, $database)];
        }
    }

    /**
     * Traduit les codes d'erreur MySQL les plus fréquents en actions concrètes.
     */
    private function explainDatabaseError(Throwable $exception, string $database): string
    {
        $message = $exception->getMessage();
        $driver = (string) config('database.default');
        $username = (string) config("database.connections.{$driver}.username");

        if (str_contains($message, '1045')) {
            return 'identifiants refusés pour l’utilisateur « '.$username
                .' » — corrigez DB_USERNAME / DB_PASSWORD dans .env (php artisan config:clear)';
        }

        if (str_contains($message, '1049') || str_contains($message, 'Unknown database')) {
            return 'base « '.$database.' » inexistante — créez-la (commande ci-dessous)';
        }

        if (str_contains($message, '2002') || str_contains($message, 'Connection refused')) {
            return 'serveur MySQL injoignable — démarrez MySQL (service, Laragon/XAMPP) ou « docker compose up -d db »';
        }

        if (str_contains($message, 'could not find driver')) {
            return 'extension pdo_mysql non chargée — activez-la dans php.ini puis rouvrez le terminal';
        }

        return mb_strimwidth($message, 0, 180, '…');
    }

    private function report(string $label, bool $ok, string $detail): void
    {
        if ($ok) {
            $this->line('  <info>[OK]</info>    '.$label);

            return;
        }

        $this->failures++;
        $this->line('  <error>[ÉCHEC]</error> '.$label.' : '.$detail);
    }
}
