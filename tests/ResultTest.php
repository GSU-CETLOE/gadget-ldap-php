<?php

declare(strict_types=1);

namespace Gadget\Ldap\Tests;

use Gadget\Ldap\Connection;
use Gadget\Ldap\LdapOptions;
use Gadget\Ldap\Result;
use Gadget\Ldap\Tests\Fixture\SlapdServer;

final class ResultTest extends LdapTestCase
{
    private const ATTRIBUTES = ['cn', 'telephoneNumber', 'description'];


    public function testParsesASuccessfulSearch(): void
    {
        $result = $this->pagedSearch('(objectClass=person)', 10);

        self::assertSame(0, $result->errorCode);
        self::assertSame('', $result->matchedDN);
        self::assertSame('', $result->errorMessage);
        self::assertSame([], $result->referrals);
        self::assertArrayHasKey(LdapOptions::CONTROL_PAGEDRESULTS, $result->controls);
    }


    public function testTheCookieIsSetWhileMorePagesRemain(): void
    {
        $result = $this->pagedSearch('(objectClass=person)', 2);

        self::assertNotSame('', $result->cookie);
    }


    public function testTheCookieIsEmptyOnTheLastPage(): void
    {
        $result = $this->pagedSearch('(objectClass=person)', 10);

        self::assertSame('', $result->cookie);
    }


    public function testTheCookieIsEmptyWhenTheSearchWasNotPaged(): void
    {
        $connection = $this->adminConnection();
        $raw = ldap_search($connection->getConnection(), SlapdServer::PEOPLE_DN, '(objectClass=person)', ['cn']);
        self::assertInstanceOf(\LDAP\Result::class, $raw);

        $result = new Result($connection, ['cn'], $raw);

        self::assertSame('', $result->cookie);
        self::assertSame([], $result->controls);
    }


    public function testEachEntryIsAnArrayKeyedByTheRequestedAttributes(): void
    {
        $entries = iterator_to_array($this->pagedSearch('(cn=user1)', 10), false);

        self::assertSame([[
            'cn' => 'user1',
            'telephoneNumber' => '555-0001,555-1001',
            'description' => 'The first user'
        ]], $entries);
    }


    public function testARequestedAttributeTheEntryLacksIsAnEmptyString(): void
    {
        $entries = iterator_to_array($this->pagedSearch('(cn=user2)', 10), false);

        self::assertCount(1, $entries);
        self::assertSame('', $entries[0]['description']);
    }


    public function testIteratesEveryEntryOfThePage(): void
    {
        $names = array_map(
            fn(array $entry): mixed => $entry['cn'],
            iterator_to_array($this->pagedSearch('(objectClass=person)', 10), false)
        );
        sort($names);

        self::assertSame(['user1', 'user2', 'user3', 'user4', 'user5'], $names);
    }


    public function testIteratesOnlyThePageTheServerReturned(): void
    {
        self::assertCount(2, iterator_to_array($this->pagedSearch('(objectClass=person)', 2), false));
    }


    public function testAnEmptyResultIteratesNothing(): void
    {
        self::assertSame([], iterator_to_array($this->pagedSearch('(cn=nobody)', 10), false));
    }


    public function testCanBeIteratedMoreThanOnce(): void
    {
        $result = $this->pagedSearch('(objectClass=person)', 10);

        self::assertCount(5, iterator_to_array($result, false));
        self::assertCount(5, iterator_to_array($result, false));
    }


    /**
     * @param string $filter
     * @param int $pageSize
     * @return Result
     */
    private function pagedSearch(string $filter, int $pageSize): Result
    {
        $connection = $this->adminConnection();
        $raw = ldap_search(
            $connection->getConnection(),
            SlapdServer::PEOPLE_DN,
            $filter,
            self::ATTRIBUTES,
            controls: [
                LdapOptions::CONTROL_PAGEDRESULTS => [
                    'oid' => LdapOptions::CONTROL_PAGEDRESULTS,
                    'iscritical' => true,
                    'value' => ['cookie' => '', 'size' => $pageSize]
                ]
            ]
        );
        self::assertInstanceOf(\LDAP\Result::class, $raw);

        return new Result($connection, self::ATTRIBUTES, $raw);
    }
}
