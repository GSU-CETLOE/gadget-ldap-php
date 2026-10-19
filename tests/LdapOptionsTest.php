<?php

declare(strict_types=1);

namespace Gadget\Ldap\Tests;

use Gadget\Ldap\LdapOptions;
use PHPUnit\Framework\TestCase;

final class LdapOptionsTest extends TestCase
{
    public function testTheConstantsMatchTheLdapExtension(): void
    {
        self::assertSame(LDAP_OPT_DEBUG_LEVEL, LdapOptions::DEBUG_LEVEL);
        self::assertSame(LDAP_OPT_PROTOCOL_VERSION, LdapOptions::PROTOCOL_VERSION);
        self::assertSame(LDAP_OPT_REFERRALS, LdapOptions::REFERRALS);
        self::assertSame(LDAP_CONTROL_PAGEDRESULTS, LdapOptions::CONTROL_PAGEDRESULTS);
    }
}
