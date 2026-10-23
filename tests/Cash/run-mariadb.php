<?php

/** Creates and removes ONLY a fresh, randomly named test database. */
ini_set('display_errors', '0');
ini_set('log_errors', '0');
require dirname(__DIR__, 2).'/vendor/autoload.php';

$database = 'gi_cash_test_'.bin2hex(random_bytes(8));
$created = false;
$exitCode = 1;
$stage = 'configuration';
try {
    $config = Dotenv\Dotenv::parse(file_get_contents(dirname(__DIR__, 2).'/.env'));
    $host = $config['DB_HOST'] ?? '';
    $port = $config['DB_PORT'] ?? '3306';
    if (! in_array($host, ['127.0.0.1', 'localhost', '::1'], true) || ! ctype_digit($port)
        || ! in_array($config['DB_CONNECTION'] ?? '', ['mysql', 'mariadb'], true)
        || ! preg_match('/\Agi_cash_test_[a-f0-9]{16}\z/', $database)) {
        throw new RuntimeException('Unsafe test connection.');
    }
    // No USE base_comedor; the administrative connection has no default database.
    $stage = 'connection';
    $pdo = new PDO('mysql:host='.$host.';port='.$port.';charset=utf8mb4', $config['DB_USERNAME'] ?? '', $config['DB_PASSWORD'] ?? '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $stage = 'create-test-database';
    $pdo->exec('CREATE DATABASE '.$database.' CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci');
    $created = true;
    $stage = 'test-process';
    $environment = array_merge(getenv(), [
        'CASH_TEST_ALLOW_MYSQL' => '1', 'CASH_TEST_DATABASE' => $database,
        'CASH_TEST_HOST' => $host, 'CASH_TEST_PORT' => $port,
        'CASH_TEST_USERNAME' => $config['DB_USERNAME'] ?? '', 'CASH_TEST_PASSWORD' => $config['DB_PASSWORD'] ?? '',
    ]);
    echo 'Isolated MariaDB database created: '.$database.". No business database selected.\n";
    $process = proc_open([PHP_BINARY, dirname(__DIR__, 2).'/vendor/phpunit/phpunit/phpunit', '--configuration', 'phpunit.cash.xml'], [STDIN, STDOUT, STDERR], $pipes, dirname(__DIR__, 2), $environment);
    if (! is_resource($process)) {
        throw new RuntimeException('Unable to start isolated tests.');
    }
    $exitCode = proc_close($process);
} catch (Throwable $exception) {
    fwrite(STDERR, 'Isolated MariaDB tests could not complete at '.$stage.' ('.get_class($exception).', code '.$exception->getCode()."). Connection details suppressed.\n");
} finally {
    if ($created) {
        try {
            // Exact generated identifier, never an external/configured database name.
            if (! preg_match('/\Agi_cash_test_[a-f0-9]{16}\z/', $database)) {
                throw new RuntimeException('Unsafe cleanup target.');
            }
            $pdo->exec('DROP DATABASE '.$database);
            echo "Isolated test database removed.\n";
        } catch (Throwable $exception) {
            fwrite(STDERR, 'Isolated test database cleanup failed: '.$database.".\n");
            $exitCode = 1;
        }
    }
}
exit($exitCode);
