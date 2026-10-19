<?php

declare(strict_types=1);

namespace Gadget\Ldap;

class Query
{
    /**
     * @param string $base
     * @param string|string[] $filter
     * @param array<int,string> $attributes
     * @param int $pageSize
     */
    public function __construct(
        public string $base,
        public string|array $filter,
        public array $attributes,
        public int $pageSize = 1000,
    ) {
    }


    /**
     * @template T
     * @param Connection $conn
     * @param (callable(mixed $values): T) $create
     * @return iterable<T>
     */
    public function execute(
        Connection $conn,
        callable $create
    ): iterable {
        $cookie = '';

        do {
            $result = $this->search($conn, $cookie);
            foreach ($result as $values) {
                yield $create($values);
            }
            $cookie = $result->cookie;
        } while ($cookie !== '');
    }


    /**
     * @param Connection $conn
     * @param string $cookie
     * @return Result
     */
    protected function search(
        Connection $conn,
        string $cookie = ''
    ): Result {
        $result = ldap_search(
            ldap: $conn->getConnection(),
            base: $this->base,
            filter: is_array($this->filter) ? implode('', $this->filter) : $this->filter,
            attributes: $this->attributes,
            controls: [
                LdapOptions::CONTROL_PAGEDRESULTS => [
                    'oid' => LdapOptions::CONTROL_PAGEDRESULTS,
                    'iscritical' => true,
                    'value' => [
                        'cookie' => $cookie,
                        'size' => $this->pageSize
                    ]
                ]
            ]
        );

        return $result instanceof \LDAP\Result
            ? new Result(
                $conn,
                $this->attributes,
                $result
            )
            : throw new LdapException("Search error");
    }
}
