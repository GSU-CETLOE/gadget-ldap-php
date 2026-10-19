<?php

declare(strict_types=1);

namespace Gadget\Ldap\Tests;

use Gadget\Ldap\Connection;
use Gadget\Ldap\LdapException;
use Gadget\Ldap\Tests\Fixture\SlapdServer;
use PHPUnit\Framework\TestCase;

/**
 * Base class for tests that talk to the fixture slapd server.
 */
abstract class LdapTestCase extends TestCase
{
    protected SlapdServer $server;


    protected function setUp(): void
    {
        $this->server = SlapdServer::get();
    }


    /**
     * A Connection configured for the fixture server's admin user.
     *
     * @return Connection
     */
    protected function adminConnection(): Connection
    {
        return new Connection($this->server->uri, SlapdServer::ADMIN_DN, SlapdServer::ADMIN_PASSWORD);
    }


    /**
     * Collects the items of an iterable into a list.
     *
     * @template T
     * @param iterable<T> $items
     * @return T[]
     */
    protected static function collect(iterable $items): array
    {
        return is_array($items) ? array_values($items) : iterator_to_array($items, false);
    }


    /**
     * Runs $action, which is expected to throw an LdapException, and returns that exception.
     *
     * @param callable(): mixed $action
     * @return LdapException
     */
    protected static function captureLdapException(callable $action): LdapException
    {
        try {
            $action();
        } catch (LdapException $exception) {
            return $exception;
        }
        self::fail("Expected an LdapException");
    }


    /**
     * Runs $action while capturing PHP warnings, then asserts exactly the expected warnings were
     * raised. The ext-ldap functions report failures as E_WARNING before returning false, so a
     * test of a failure path has to account for them.
     *
     * @param callable(): void $action
     * @param string[] $expectedWarnings
     * @return void
     */
    protected static function assertRaisesWarnings(callable $action, array $expectedWarnings): void
    {
        $warnings = [];
        set_error_handler(static function (int $level, string $message) use (&$warnings): bool {
            $warnings[] = $message;
            return $level === E_WARNING;
        });
        try {
            $action();
        } finally {
            restore_error_handler();
        }
        self::assertSame($expectedWarnings, $warnings);
    }
}
