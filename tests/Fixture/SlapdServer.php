<?php

declare(strict_types=1);

namespace Gadget\Ldap\Tests\Fixture;

use PHPUnit\Framework\Assert;

/**
 * Runs a real OpenLDAP server (slapd) for the duration of the test process.
 *
 * The server listens on a free loopback port, keeps its database under working/slapd, and is
 * loaded with a small directory tree: a base entry, an "ou=people" container, and five person
 * entries. It starts on first use and stops when the PHP process exits.
 */
final class SlapdServer
{
    public const BASE_DN = 'dc=example,dc=test';
    public const PEOPLE_DN = 'ou=people,dc=example,dc=test';
    public const ADMIN_DN = 'cn=admin,dc=example,dc=test';
    public const ADMIN_PASSWORD = 'secret';
    public const PEOPLE_COUNT = 5;

    /** Environment variable naming the slapd binary when it is not in a known location. */
    public const SLAPD_ENV = 'GADGET_LDAP_SLAPD';

    /** Environment variable naming the core.schema file when it is not in a known location. */
    public const SCHEMA_ENV = 'GADGET_LDAP_SCHEMA';

    private const KNOWN_SLAPD_PATHS = [
        '/opt/homebrew/opt/openldap/libexec/slapd',
    ];

    private const KNOWN_SCHEMA_PATHS = [
        '/opt/homebrew/etc/openldap/schema/core.schema',
    ];

    private const READY_TIMEOUT_SECONDS = 10;

    private static self|null $instance = null;


    /**
     * @param string $uri
     * @param resource $process
     * @param string $dir
     */
    private function __construct(
        public readonly string $uri,
        private $process,
        private string $dir
    ) {
    }


    /**
     * Returns the running server, starting it on first use. Skips the calling test when no
     * slapd binary or core.schema can be found.
     *
     * @return self
     */
    public static function get(): self
    {
        if (self::$instance === null) {
            $slapd = self::slapdPath();
            $schema = self::schemaPath();
            if ($slapd === null || $schema === null) {
                Assert::markTestSkipped(sprintf(
                    "slapd or core.schema not found; set %s and %s to their paths",
                    self::SLAPD_ENV,
                    self::SCHEMA_ENV
                ));
            }

            self::$instance = self::start($slapd, $schema);
            register_shutdown_function(static fn() => self::$instance?->stop());
        }

        return self::$instance;
    }


    /**
     * Opens a bound connection to the server as the admin user.
     *
     * @return \LDAP\Connection
     */
    public function adminConnection(): \LDAP\Connection
    {
        $conn = ldap_connect($this->uri);
        if (!$conn instanceof \LDAP\Connection) {
            throw new \RuntimeException("ldap_connect() failed for {$this->uri}");
        }
        ldap_set_option($conn, LDAP_OPT_PROTOCOL_VERSION, 3);
        if (ldap_bind($conn, self::ADMIN_DN, self::ADMIN_PASSWORD) !== true) {
            throw new \RuntimeException("ldap_bind() failed: " . ldap_error($conn));
        }
        return $conn;
    }


    /**
     * Stops the server and removes its working directory.
     *
     * @return void
     */
    public function stop(): void
    {
        if (proc_get_status($this->process)['running']) {
            proc_terminate($this->process);
        }
        proc_close($this->process);
        self::removeDirectory($this->dir);
        self::$instance = null;
    }


    /**
     * @return string|null
     */
    private static function slapdPath(): string|null
    {
        $fromEnv = getenv(self::SLAPD_ENV);
        $candidates = $fromEnv !== false && $fromEnv !== '' ? [$fromEnv] : self::KNOWN_SLAPD_PATHS;
        $path = getenv('PATH');
        foreach (explode(PATH_SEPARATOR, $path === false ? '' : $path) as $dir) {
            $candidates[] = $dir . DIRECTORY_SEPARATOR . 'slapd';
        }
        foreach ($candidates as $candidate) {
            if (is_executable($candidate)) {
                return $candidate;
            }
        }
        return null;
    }


    /**
     * @return string|null
     */
    private static function schemaPath(): string|null
    {
        $fromEnv = getenv(self::SCHEMA_ENV);
        $candidates = $fromEnv !== false && $fromEnv !== '' ? [$fromEnv] : self::KNOWN_SCHEMA_PATHS;
        foreach ($candidates as $candidate) {
            if (is_readable($candidate)) {
                return $candidate;
            }
        }
        return null;
    }


    /**
     * @param string $slapd
     * @param string $schema
     * @return self
     */
    private static function start(string $slapd, string $schema): self
    {
        $dir = self::workingDirectory();
        $port = self::freePort();
        $uri = "ldap://127.0.0.1:{$port}";

        file_put_contents($dir . '/slapd.conf', implode("\n", [
            "include {$schema}",
            "pidfile {$dir}/slapd.pid",
            "database mdb",
            "suffix \"" . self::BASE_DN . "\"",
            "rootdn \"" . self::ADMIN_DN . "\"",
            "rootpw " . self::ADMIN_PASSWORD,
            "directory {$dir}/db",
            ""
        ]));

        $process = proc_open(
            [$slapd, '-f', $dir . '/slapd.conf', '-h', $uri, '-d', '0'],
            [
                0 => ['file', '/dev/null', 'r'],
                1 => ['file', $dir . '/slapd.log', 'a'],
                2 => ['file', $dir . '/slapd.log', 'a']
            ],
            $pipes
        );
        if ($process === false) {
            throw new \RuntimeException("Could not start {$slapd}");
        }

        $server = new self($uri, $process, $dir);
        $server->waitUntilReady();
        $server->loadFixtureEntries();
        return $server;
    }


    /**
     * @return string
     */
    private static function workingDirectory(): string
    {
        $dir = dirname(__DIR__, 2) . '/working/slapd/' . getmypid();
        self::removeDirectory($dir);
        if (!mkdir($dir . '/db', 0700, true)) {
            throw new \RuntimeException("Could not create {$dir}");
        }
        return $dir;
    }


    /**
     * @return int
     */
    private static function freePort(): int
    {
        $socket = stream_socket_server('tcp://127.0.0.1:0', $errorCode, $errorMessage);
        if ($socket === false) {
            throw new \RuntimeException("Could not find a free port: {$errorMessage}");
        }
        $name = stream_socket_get_name($socket, false);
        fclose($socket);
        if ($name === false) {
            throw new \RuntimeException("Could not read the port of the probe socket");
        }
        $colon = strrpos($name, ':');
        if ($colon === false) {
            throw new \RuntimeException("Unexpected probe socket name: {$name}");
        }
        return intval(substr($name, $colon + 1));
    }


    /**
     * Polls the server until the admin bind succeeds or the server dies or times out.
     *
     * @return void
     */
    private function waitUntilReady(): void
    {
        $deadline = microtime(true) + self::READY_TIMEOUT_SECONDS;
        do {
            $conn = ldap_connect($this->uri);
            if ($conn instanceof \LDAP\Connection) {
                ldap_set_option($conn, LDAP_OPT_PROTOCOL_VERSION, 3);
                if (@ldap_bind($conn, self::ADMIN_DN, self::ADMIN_PASSWORD) === true) {
                    ldap_unbind($conn);
                    return;
                }
            }
            if (!proc_get_status($this->process)['running']) {
                throw new \RuntimeException("slapd exited during startup:\n" . $this->log());
            }
            usleep(100000);
        } while (microtime(true) < $deadline);

        throw new \RuntimeException("slapd did not become ready in time:\n" . $this->log());
    }


    /**
     * @return void
     */
    private function loadFixtureEntries(): void
    {
        $conn = $this->adminConnection();
        $entries = [
            self::BASE_DN => [
                'objectClass' => ['top', 'dcObject', 'organization'],
                'dc' => 'example',
                'o' => 'Example'
            ],
            self::PEOPLE_DN => [
                'objectClass' => ['top', 'organizationalUnit'],
                'ou' => 'people'
            ]
        ];
        for ($i = 1; $i <= self::PEOPLE_COUNT; $i++) {
            $entries[self::personDN($i)] = [
                'objectClass' => ['top', 'person'],
                'cn' => "user{$i}",
                'sn' => "Surname{$i}",
                'telephoneNumber' => ["555-000{$i}", "555-100{$i}"]
            ] + ($i === 1 ? ['description' => 'The first user'] : []);
        }

        foreach ($entries as $dn => $entry) {
            if (ldap_add($conn, $dn, $entry) !== true) {
                throw new \RuntimeException("ldap_add() failed for {$dn}: " . ldap_error($conn));
            }
        }
        ldap_unbind($conn);
    }


    /**
     * @param int $number
     * @return string
     */
    public static function personDN(int $number): string
    {
        return "cn=user{$number}," . self::PEOPLE_DN;
    }


    /**
     * @return string
     */
    private function log(): string
    {
        $log = @file_get_contents($this->dir . '/slapd.log');
        return $log !== false ? $log : '(no log)';
    }


    /**
     * @param string $dir
     * @return void
     */
    private static function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($items as $item) {
            if ($item instanceof \SplFileInfo) {
                $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
            }
        }
        rmdir($dir);
    }
}
