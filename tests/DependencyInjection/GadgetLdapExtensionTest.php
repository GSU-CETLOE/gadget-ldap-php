<?php

declare(strict_types=1);

namespace Gadget\Ldap\Tests\DependencyInjection;

use Gadget\Ldap\Connection;
use Gadget\Ldap\DependencyInjection\GadgetLdapExtension;
use Gadget\Ldap\LdapException;
use Gadget\Ldap\Tests\Fixture\SlapdServer;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;

final class GadgetLdapExtensionTest extends TestCase
{
    private static function loadedContainer(): ContainerBuilder
    {
        $container = new ContainerBuilder();
        (new GadgetLdapExtension())->load([], $container);
        return $container;
    }


    public function testRegistersTheConnectionAsAnAutowiredServiceReadingTheEnvironment(): void
    {
        $container = self::loadedContainer();

        self::assertTrue($container->hasDefinition(Connection::class));
        $definition = $container->getDefinition(Connection::class);
        self::assertTrue($definition->isAutowired());
        self::assertTrue($definition->isAutoconfigured());
        self::assertSame([
            '$uri' => '%env(string:default::LDAP_URI)%',
            '$user' => '%env(string:default::LDAP_USER)%',
            '$pass' => '%env(string:default::LDAP_PASS)%'
        ], $definition->getArguments());
    }


    public function testCompiledContainerBuildsAConnectionFromTheEnvironment(): void
    {
        $server = SlapdServer::get();
        $container = self::loadedContainer();
        $container->getDefinition(Connection::class)->setPublic(true);

        putenv("LDAP_URI={$server->uri}");
        putenv('LDAP_USER=' . SlapdServer::ADMIN_DN);
        putenv('LDAP_PASS=' . SlapdServer::ADMIN_PASSWORD);
        try {
            $container->compile(true);
            $connection = $container->get(Connection::class);
        } finally {
            putenv('LDAP_URI');
            putenv('LDAP_USER');
            putenv('LDAP_PASS');
        }

        self::assertInstanceOf(Connection::class, $connection);
        self::assertInstanceOf(\LDAP\Connection::class, $connection->getConnection());
    }


    public function testAConnectionBuiltWithoutEnvironmentVariablesHasBlankCredentials(): void
    {
        $container = self::loadedContainer();
        $container->getDefinition(Connection::class)->setPublic(true);
        $container->compile(true);

        $connection = $container->get(Connection::class);

        self::assertInstanceOf(Connection::class, $connection);
        $this->expectException(LdapException::class);
        $this->expectExceptionMessage("Username/password is blank");
        $connection->getConnection();
    }
}
