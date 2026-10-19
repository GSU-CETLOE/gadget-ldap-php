<?php

declare(strict_types=1);

namespace Gadget\Ldap;

use Gadget\Io\JSON;

class Connection
{
    /**
     * @param string $uri
     * @param string $user
     * @param string $pass
     * @param \LDAP\Connection|null $conn
     * @param array<int,bool|int|string|(bool|int|string)[]> $globalOptions
     * @param array<int,bool|int|string|(bool|int|string)[]> $connectOptions
     */
    public function __construct(
        private string $uri = '',
        private string $user = '',
        #[\SensitiveParameter]
        private string $pass = '',
        private \LDAP\Connection|null $conn = null,
        private array $globalOptions = [
            // LdapOptions::DEBUG_LEVEL => 7
        ],
        private array $connectOptions = [
            LdapOptions::PROTOCOL_VERSION => 3,
            LdapOptions::REFERRALS => 0
        ]
    ) {
    }


    /**
     * @param string $uri
     * @return self
     */
    public function setUri(string $uri): self
    {
        $this->uri = $uri;
        $this->conn = null;
        return $this;
    }


    /**
     * @param string $user
     * @return self
     */
    public function setUser(string $user): self
    {
        $this->user = $user;
        $this->conn = null;
        return $this;
    }


    /**
     * @param string $pass
     * @return self
     */
    public function setPass(string $pass): self
    {
        $this->pass = $pass;
        $this->conn = null;
        return $this;
    }


    /**
     * @param \LDAP\Connection|null $conn
     * @return self
     */
    public function setConnection(\LDAP\Connection|null $conn): self
    {
        $this->conn = $conn;
        return $this;
    }


    /**
     * @return \LDAP\Connection
     */
    public function getConnection(): \LDAP\Connection
    {
        $this->conn ??= $this->createConnection();
        return $this->conn;
    }


    /**
     * @return \LDAP\Connection
     */
    protected function createConnection(): \LDAP\Connection
    {
        foreach ($this->globalOptions as $option => $value) {
            if (ldap_set_option(null, $option, $value) !== true) {
                throw new LdapException(["ldap_set_option() failed: %s", [JSON::encode([
                    'ldap' => null,
                    'option' => $option,
                    'value' => $value
                ])]]);
            }
        }

        $conn = ldap_connect($this->uri);
        if (!$conn instanceof \LDAP\Connection) {
            throw new LdapException(["ldap_connect() failed: \$uri => : %s", [JSON::encode([
                'uri' => $this->uri
            ])]]);
        }

        foreach ($this->connectOptions as $option => $value) {
            if (ldap_set_option($conn, $option, $value) !== true) {
                if (ldap_set_option(null, $option, $value) !== true) {
                    throw new LdapException(["ldap_set_option() failed: %s", [JSON::encode([
                        'ldap' => 'resource',
                        'option' => $option,
                        'value' => $value
                    ])]]);
                }
            }
        }

        if ($this->user === '' || $this->pass === '') {
            throw new LdapException("Username/password is blank");
        }

        if (ldap_bind($conn, $this->user, $this->pass) === false) {
            throw new LdapException(["ldap_bind() failed: %s", [JSON::encode([
                'ldap' => 'resource',
                'user' => $this->user
            ])]]);
        }

        return $conn;
    }


    /**
     * @template T
     * @param Query $query
     * @param (callable(mixed $values): T) $create
     * @return iterable<T>
     */
    public function query(
        Query $query,
        callable $create
    ): iterable {
        return $query->execute($this, $create);
    }
}
