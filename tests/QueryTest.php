<?php

declare(strict_types=1);

namespace Gadget\Ldap\Tests;

use Gadget\Ldap\LdapException;
use Gadget\Ldap\Query;
use Gadget\Ldap\Tests\Fixture\CountingQuery;
use Gadget\Ldap\Tests\Fixture\SlapdServer;

final class QueryTest extends LdapTestCase
{
    public function testExecuteYieldsEveryEntryAcrossPages(): void
    {
        $query = new CountingQuery(SlapdServer::PEOPLE_DN, '(objectClass=person)', ['cn'], pageSize: 2);

        $names = $this->names($query);

        self::assertSame(['user1', 'user2', 'user3', 'user4', 'user5'], $names);
        self::assertSame(3, $query->searches);
    }


    public function testAPageSizeLargerThanTheResultRunsASingleSearch(): void
    {
        $query = new CountingQuery(SlapdServer::PEOPLE_DN, '(objectClass=person)', ['cn'], pageSize: 100);

        self::assertCount(5, $this->names($query));
        self::assertSame(1, $query->searches);
    }


    public function testAFilterGivenAsAnArrayIsJoinedIntoOneFilter(): void
    {
        $query = new Query(SlapdServer::PEOPLE_DN, ['(&', '(objectClass=person)', '(cn=user3)', ')'], ['cn']);

        self::assertSame(['user3'], $this->names($query));
    }


    public function testTheCreateCallbackBuildsEachYieldedItem(): void
    {
        $query = new Query(SlapdServer::PEOPLE_DN, '(cn=user2)', ['cn', 'sn']);

        $people = self::collect($query->execute($this->adminConnection(), function (mixed $values): \ArrayObject {
            self::assertIsArray($values);
            return new \ArrayObject($values);
        }));

        self::assertCount(1, $people);
        self::assertSame(['cn' => 'user2', 'sn' => 'Surname2'], $people[0]->getArrayCopy());
    }


    public function testAFilterThatMatchesNothingYieldsNothing(): void
    {
        $query = new Query(SlapdServer::PEOPLE_DN, '(cn=nobody)', ['cn']);

        self::assertSame([], $this->names($query));
    }


    public function testAMissingBaseThrowsASearchError(): void
    {
        $query = new Query('ou=nope,' . SlapdServer::BASE_DN, '(objectClass=*)', ['cn']);

        $this->assertSearchErrorWith($query, "ldap_search(): Search: No such object");
    }


    public function testABadFilterThrowsASearchError(): void
    {
        $query = new Query(SlapdServer::PEOPLE_DN, '(((', ['cn']);

        $this->assertSearchErrorWith($query, "ldap_search(): Search: Bad search filter");
    }


    /**
     * @param Query $query
     * @return string[]
     */
    private function names(Query $query): array
    {
        $names = self::collect($query->execute($this->adminConnection(), function (mixed $values): string {
            self::assertIsArray($values);
            self::assertIsString($values['cn']);
            return $values['cn'];
        }));
        sort($names);
        return $names;
    }


    /**
     * @param Query $query
     * @param string $expectedWarning
     * @return void
     */
    private function assertSearchErrorWith(Query $query, string $expectedWarning): void
    {
        $connection = $this->adminConnection();

        $exception = null;
        self::assertRaisesWarnings(
            function () use ($query, $connection, &$exception): void {
                $exception = self::captureLdapException(
                    fn() => self::collect($query->execute($connection, fn(mixed $values): mixed => $values))
                );
            },
            [$expectedWarning]
        );

        self::assertInstanceOf(LdapException::class, $exception);
        self::assertSame("Search error", $exception->getMessage());
    }
}
