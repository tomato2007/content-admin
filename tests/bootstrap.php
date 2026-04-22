<?php

declare(strict_types=1);

require __DIR__.'/../vendor/autoload.php';

bootstrapTestDatabase();

/**
 * Configure the test database for the current PHP runtime.
 *
 * Prefer in-memory SQLite when the driver is available.
 * Fall back to a temporary local PostgreSQL cluster when only pdo_pgsql exists.
 */
function bootstrapTestDatabase(): void
{
    if (extension_loaded('pdo_sqlite')) {
        setTestEnv('DB_CONNECTION', 'sqlite');
        setTestEnv('DB_DATABASE', ':memory:');

        return;
    }

    if (! extension_loaded('pdo_pgsql')) {
        throw new RuntimeException('Neither pdo_sqlite nor pdo_pgsql is available for the test suite.');
    }

    $runtime = ensureTemporaryPostgresRuntime();

    setTestEnv('DB_CONNECTION', 'pgsql');
    setTestEnv('DB_HOST', $runtime['socket_dir']);
    setTestEnv('DB_PORT', (string) $runtime['port']);
    setTestEnv('DB_DATABASE', $runtime['database']);
    setTestEnv('DB_USERNAME', $runtime['username']);
    setTestEnv('DB_PASSWORD', '');
}

/**
 * @return array{socket_dir: string, port: int, database: string, username: string}
 */
function ensureTemporaryPostgresRuntime(): array
{
    $baseDir = __DIR__.'/../storage/framework/testing/postgres';
    $dataDir = $baseDir.'/data';
    $socketDir = $baseDir.'/socket';
    $logFile = $baseDir.'/postgres.log';
    $database = 'content_admin_test';
    $username = 'postgres';
    $port = 55432;

    ensureDirectory($baseDir);
    ensureDirectory($socketDir);

    $binDir = detectPostgresBinDir();
    $pgCtl = $binDir.'/pg_ctl';
    $initDb = $binDir.'/initdb';
    $pgIsReady = $binDir.'/pg_isready';

    if (! is_dir($dataDir.'/base')) {
        runCommand([
            $initDb,
            '-D', $dataDir,
            '-A', 'trust',
            '-U', $username,
        ]);
    }

    if (! postgresIsReady($pgIsReady, $socketDir, $port, $username, $database)) {
        clearStalePostgresState($dataDir, $socketDir);

        runCommand([
            $pgCtl,
            '-D', $dataDir,
            '-l', $logFile,
            '-o', sprintf("-F -k %s -c listen_addresses='' -p %d", $socketDir, $port),
            'start',
        ]);

        if (! postgresIsReady($pgIsReady, $socketDir, $port, $username, $database, 10)) {
            throw new RuntimeException('Temporary PostgreSQL server did not become ready for tests.');
        }

        register_shutdown_function(static function () use ($pgCtl, $dataDir): void {
            runCommand(
                [$pgCtl, '-D', $dataDir, '-m', 'fast', 'stop'],
                throwOnFailure: false,
            );
        });
    }

    ensurePostgresDatabaseExists($socketDir, $port, $database, $username);

    return [
        'socket_dir' => $socketDir,
        'port' => $port,
        'database' => $database,
        'username' => $username,
    ];
}

function detectPostgresBinDir(): string
{
    $candidates = glob('/usr/lib/postgresql/*/bin');

    if ($candidates === false || $candidates === []) {
        throw new RuntimeException('PostgreSQL binaries were not found under /usr/lib/postgresql/*/bin.');
    }

    rsort($candidates, SORT_NATURAL);

    foreach ($candidates as $candidate) {
        if (is_file($candidate.'/pg_ctl') && is_file($candidate.'/initdb')) {
            return $candidate;
        }
    }

    throw new RuntimeException('Required PostgreSQL binaries (pg_ctl/initdb) were not found.');
}

function ensurePostgresDatabaseExists(string $socketDir, int $port, string $database, string $username): void
{
    $pdo = new PDO(
        sprintf('pgsql:host=%s;port=%d;dbname=postgres', $socketDir, $port),
        $username,
        '',
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ],
    );

    $statement = $pdo->prepare('SELECT 1 FROM pg_database WHERE datname = :database');
    $statement->execute(['database' => $database]);

    if ($statement->fetchColumn() === false) {
        $quotedDatabase = '"'.str_replace('"', '""', $database).'"';
        $pdo->exec('CREATE DATABASE '.$quotedDatabase);
    }
}

function postgresIsReady(string $pgIsReady, string $socketDir, int $port, string $username, string $database, int $attempts = 1): bool
{
    for ($attempt = 0; $attempt < $attempts; $attempt++) {
        $exitCode = runCommand(
            [
                $pgIsReady,
                '-h', $socketDir,
                '-p', (string) $port,
                '-U', $username,
                '-d', $database,
            ],
            throwOnFailure: false,
        );

        if ($exitCode === 0) {
            return true;
        }

        usleep(250000);
    }

    return false;
}

function clearStalePostgresState(string $dataDir, string $socketDir): void
{
    $pidFile = $dataDir.'/postmaster.pid';
    if (is_file($pidFile)) {
        @unlink($pidFile);
    }

    foreach (glob($socketDir.'/.s.PGSQL.*') ?: [] as $socketFile) {
        @unlink($socketFile);
    }
}

function ensureDirectory(string $path): void
{
    if (! is_dir($path) && ! mkdir($path, 0777, true) && ! is_dir($path)) {
        throw new RuntimeException(sprintf('Unable to create directory: %s', $path));
    }
}

function setTestEnv(string $key, string $value): void
{
    putenv($key.'='.$value);
    $_ENV[$key] = $value;
    $_SERVER[$key] = $value;
}

/**
 * @param  array<int, string>  $command
 */
function runCommand(array $command, bool $throwOnFailure = true): int
{
    $descriptor = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];

    $process = proc_open($command, $descriptor, $pipes, __DIR__.'/..');

    if (! is_resource($process)) {
        throw new RuntimeException('Unable to start external command for test bootstrap.');
    }

    fclose($pipes[0]);
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);

    $exitCode = proc_close($process);

    if ($throwOnFailure && $exitCode !== 0) {
        $message = trim($stderr !== '' ? $stderr : $stdout);

        throw new RuntimeException($message === '' ? 'External command failed during test bootstrap.' : $message);
    }

    return $exitCode;
}
