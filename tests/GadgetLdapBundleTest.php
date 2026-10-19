<?php

declare(strict_types=1);

namespace Gadget\Ldap\Tests;

use Gadget\Ldap\DependencyInjection\GadgetLdapExtension;
use Gadget\Ldap\GadgetLdapBundle;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Bundle\Bundle;

final class GadgetLdapBundleTest extends TestCase
{
    public function testIsASymfonyBundle(): void
    {
        self::assertInstanceOf(Bundle::class, new GadgetLdapBundle());
    }


    public function testExposesTheGadgetLdapExtension(): void
    {
        $extension = (new GadgetLdapBundle())->getContainerExtension();

        self::assertInstanceOf(GadgetLdapExtension::class, $extension);
        self::assertSame('gadget_ldap', $extension->getAlias());
    }
}
