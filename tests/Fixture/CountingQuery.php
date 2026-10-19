<?php

declare(strict_types=1);

namespace Gadget\Ldap\Tests\Fixture;

use Gadget\Ldap\Connection;
use Gadget\Ldap\Query;
use Gadget\Ldap\Result;

/**
 * Counts how many searches a query sends to the server, to prove paging happens.
 */
final class CountingQuery extends Query
{
    public int $searches = 0;


    /** @inheritdoc */
    protected function search(Connection $conn, string $cookie = ''): Result
    {
        $this->searches++;
        return parent::search($conn, $cookie);
    }
}
