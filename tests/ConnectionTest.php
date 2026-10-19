<?php

declare(strict_types=1);

namespace Gadget\Ldap\Tests;

use Gadget\Ldap\Connection;
use Gadget\Ldap\LdapException;
use Gadget\Ldap\Query;
use Gadget\Ldap\Tests\Fixture\SlapdServer;

final class ConnectionTest extends LdapTestCase
{
    public function testGetConnectionBindsOnceAndReturnsTheSameHandleAfterwards(): void
    {
        $connection = $this->adminConnection();

        $handle = $connection->getConnection();

        self::assertInstanceOf(\LDAP\Connection::class, $handle);
        self::assertSame($handle, $connection->getConnection());
    }


    public function testSetUriDiscardsTheCachedHandle(): void
    {
        $connection = $this->adminConnection();
        $handle = $connection->getConnection();

        self::assertSame($connection, $connection->setUri($this->server->uri));

        self::assertNotSame($handle, $connection->getConnection());
    }


    public function testSetUserDiscardsTheCachedHandle(): void
    {
        $connection = $this->adminConnection();
        $handle = $connection->getConnection();

        self::assertSame($connection, $connection->setUser(SlapdServer::ADMIN_DN));

        self::assertNotSame($handle, $connection->getConnection());
    }


    public function testSetPassDiscardsTheCachedHandle(): void
    {
        $connection = $this->adminConnection();
        $handle = $connection->getConnection();

        self::assertSame($connection, $connection->setPass(SlapdServer::ADMIN_PASSWORD));

        self::assertNotSame($handle, $connection->getConnection());
    }


    public function testSetConnectionUsesTheGivenHandleInsteadOfBinding(): void
    {
        $handle = $this->server->adminConnection();
        $connection = new Connection();

        self::assertSame($connection, $connection->setConnection($handle));

        self::assertSame($handle, $connection->getConnection());
    }


    public function testSetConnectionWithNullMakesTheNextCallBindAgain(): void
    {
        $connection = $this->adminConnection();
        $handle = $connection->getConnection();

        $connection->setConnection(null);

        self::assertNotSame($handle, $connection->getConnection());
    }


    public function testBlankUserThrowsBeforeBinding(): void
    {
        $connection = new Connection($this->server->uri, '', SlapdServer::ADMIN_PASSWORD);

        $this->expectException(LdapException::class);
        $this->expectExceptionMessage("Username/password is blank");

        $connection->getConnection();
    }


    public function testBlankPasswordThrowsBeforeBinding(): void
    {
        $connection = new Connection($this->server->uri, SlapdServer::ADMIN_DN, '');

        $this->expectException(LdapException::class);
        $this->expectExceptionMessage("Username/password is blank");

        $connection->getConnection();
    }


    public function testWrongPasswordThrowsAndReportsTheUser(): void
    {
        $connection = new Connection($this->server->uri, SlapdServer::ADMIN_DN, 'wrong');

        $exception = null;
        self::assertRaisesWarnings(
            function () use ($connection, &$exception): void {
                $exception = self::captureLdapException(fn() => $connection->getConnection());
            },
            ["ldap_bind(): Unable to bind to server: Invalid credentials"]
        );

        self::assertInstanceOf(LdapException::class, $exception);
        self::assertSame(
            'ldap_bind() failed: {"ldap":"resource","user":"' . SlapdServer::ADMIN_DN . '"}',
            $exception->getMessage()
        );
    }


    public function testUnreachableServerThrowsOnBind(): void
    {
        $connection = new Connection('ldap://127.0.0.1:1', SlapdServer::ADMIN_DN, SlapdServer::ADMIN_PASSWORD);

        $exception = null;
        self::assertRaisesWarnings(
            function () use ($connection, &$exception): void {
                $exception = self::captureLdapException(fn() => $connection->getConnection());
            },
            ["ldap_bind(): Unable to bind to server: Can't contact LDAP server"]
        );

        self::assertInstanceOf(LdapException::class, $exception);
        self::assertStringStartsWith('ldap_bind() failed: ', $exception->getMessage());
    }


    public function testInvalidUriThrowsOnConnect(): void
    {
        $connection = new Connection('not a uri', SlapdServer::ADMIN_DN, SlapdServer::ADMIN_PASSWORD);

        $exception = null;
        self::assertRaisesWarnings(
            function () use ($connection, &$exception): void {
                $exception = self::captureLdapException(fn() => $connection->getConnection());
            },
            ["ldap_connect(): Could not create session handle: Bad parameter to an ldap routine"]
        );

        self::assertInstanceOf(LdapException::class, $exception);
        self::assertSame('ldap_connect() failed: $uri => : {"uri":"not a uri"}', $exception->getMessage());
    }


    public function testInvalidGlobalOptionThrows(): void
    {
        $connection = new Connection(
            $this->server->uri,
            SlapdServer::ADMIN_DN,
            SlapdServer::ADMIN_PASSWORD,
            globalOptions: [999999 => 1]
        );

        $this->expectException(LdapException::class);
        $this->expectExceptionMessage('ldap_set_option() failed: {"ldap":null,"option":999999,"value":1}');

        $connection->getConnection();
    }


    public function testInvalidConnectOptionThrows(): void
    {
        $connection = new Connection(
            $this->server->uri,
            SlapdServer::ADMIN_DN,
            SlapdServer::ADMIN_PASSWORD,
            connectOptions: [999999 => 1]
        );

        $this->expectException(LdapException::class);
        $this->expectExceptionMessage('ldap_set_option() failed: {"ldap":"resource","option":999999,"value":1}');

        $connection->getConnection();
    }


    public function testQueryRunsTheQueryAgainstThisConnection(): void
    {
        $query = new Query(SlapdServer::PEOPLE_DN, '(objectClass=person)', ['cn']);

        $names = self::collect($this->adminConnection()->query($query, fn(mixed $values): string => self::cn($values)));
        sort($names);

        self::assertSame(['user1', 'user2', 'user3', 'user4', 'user5'], $names);
    }


    /**
     * @param mixed $values
     * @return string
     */
    private static function cn(mixed $values): string
    {
        self::assertIsArray($values);
        self::assertIsString($values['cn']);
        return $values['cn'];
    }
}
