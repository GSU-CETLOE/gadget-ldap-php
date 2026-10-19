<?php

declare(strict_types=1);

namespace Gadget\Ldap;

/** @implements \IteratorAggregate<int,array<string,mixed>> */
class Result implements \IteratorAggregate
{
    private \LDAP\ResultEntry|false $next = false;
    public int $errorCode = 0;
    public string|null $matchedDN = null;
    public string $errorMessage = '';
    /** @var mixed[] $referrals */
    public array $referrals = [];
    /** @var mixed[] $controls */
    public array $controls = [];
    public string $cookie = '';


    /**
     * @param Connection $conn
     * @param array<int,string> $attributes
     * @param \LDAP\Result $result
     */
    public function __construct(
        private Connection $conn,
        private array $attributes,
        private \LDAP\Result $result
    ) {
        $success = ldap_parse_result(
            ldap: $this->conn->getConnection(),
            result: $result,
            error_code: $errorCode,
            matched_dn: $matchedDN,
            error_message: $errorMessage,
            referrals: $referrals,
            controls: $controls
        );
        if (!$success) {
            throw new LdapException("");
        }

        /** @var int $errorCode */
        $this->errorCode = $errorCode;
        /** @var string $matchedDN */
        $this->matchedDN = $matchedDN;
        /** @var string $errorMessage */
        $this->errorMessage = $errorMessage;
        /** @var mixed[] $referrals */
        $this->referrals = $referrals;
        /** @var mixed[][][] $controls */
        $this->controls = $controls;

        $cookie = $controls[LdapOptions::CONTROL_PAGEDRESULTS]['value']['cookie'] ?? '';
        $this->cookie = is_string($cookie) ? $cookie : '';
    }


    /** @inheritdoc */
    public function getIterator(): \Traversable
    {
        for ($entry = $this->first(); $entry instanceof \LDAP\ResultEntry; $entry = $this->next()) {
            yield $this->getEntryValues($entry);
        }
    }


    /**
     * @return \LDAP\ResultEntry|false
     */
    protected function first(): \LDAP\ResultEntry|false
    {
        $this->next = ldap_first_entry(
            $this->conn->getConnection(),
            $this->result
        );
        return $this->next;
    }


    /**
     * @return \LDAP\ResultEntry|false
     */
    protected function next(): \LDAP\ResultEntry|false
    {
        $this->next = $this->next instanceof \LDAP\ResultEntry
            ? ldap_next_entry(
                $this->conn->getConnection(),
                $this->next
            )
            : false;
        return $this->next;
    }


    /**
     * @param \LDAP\ResultEntry $entry
     * @return array<string,mixed>
     */
    protected function getEntryValues(\LDAP\ResultEntry $entry): array
    {
        /** @var array<(int|string)[]|int|string> $attributes */
        $attributes = ldap_get_attributes(
            $this->conn->getConnection(),
            $entry
        );

        $values = [];
        foreach ($this->attributes as $name) {
            $value = $attributes[$name] ?? null;
            if (is_array($value)) {
                $newValue = [];
                for ($i = 0; $i < ($value['count'] ?? 0); $i++) {
                    $newValue[] = strval($value[$i]);
                }
                $value = implode(",", $newValue);
            }
            $values[$name] = strval($value);
        }
        return $values;
    }
}
