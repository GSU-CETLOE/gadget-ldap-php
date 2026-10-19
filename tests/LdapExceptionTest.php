<?php

declare(strict_types=1);

namespace Gadget\Ldap\Tests;

use Gadget\Exception\Exception;
use Gadget\Ldap\LdapException;
use PHPUnit\Framework\TestCase;

final class LdapExceptionTest extends TestCase
{
    public function testIsAGadgetException(): void
    {
        self::assertInstanceOf(Exception::class, new LdapException("boom"));
    }


    public function testKeepsAPlainMessage(): void
    {
        self::assertSame("boom", (new LdapException("boom"))->getMessage());
    }


    public function testFormatsAMessageGivenAsFormatAndArguments(): void
    {
        $exception = new LdapException(["Invalid timestamp: %d, month %d", [20241301000000, 13]]);

        self::assertSame("Invalid timestamp: 20241301000000, month 13", $exception->getMessage());
    }


    public function testKeepsThePreviousException(): void
    {
        $previous = new \RuntimeException("cause");

        self::assertSame($previous, (new LdapException("boom", $previous))->getPrevious());
    }
}
