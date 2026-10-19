# gadget-ldap-php

A small PHP library for reading from an LDAP directory, built on the `ldap` extension. It gives you:

- A connection object that holds the server URI and bind credentials and connects the first time you ask for the handle. Protocol version 3 and no referral chasing are set by default, and you can pass any other `ldap_set_option()` settings.
- A query object that runs a paged search (RFC 2696) and streams every entry through a callback of your choosing. You never see the paging cookies.
- A result object that turns one page of entries into plain arrays keyed by the attributes you asked for.
- Helpers for the two numeric date forms Active Directory hands out: `YYYYMMDDHHMMSS` timestamps and 100-nanosecond intervals since 1601.

A Symfony bundle is included so the connection autowires from three environment variables.

CETLOE at Georgia State University maintains this package. It started as `mjfk23/gadget-ldap`, written by Melody Kimball.

## Requirements

- PHP 8.1 with the `ldap` extension
- Symfony 6.4 components, if you use the bundle

The package pulls in `gsu-cetloe/gadget-core-php` and `gsu-cetloe/symfony-bundle`.

## Setup

Install [Composer](https://getcomposer.org/) if you don't have it, then add the package to your project:

```sh
composer require gsu-cetloe/gadget-ldap-php
```

To work on the library itself, clone the repository and install the development dependencies:

```sh
git clone https://github.com/GSU-CETLOE/gadget-ldap-php.git
cd gadget-ldap-php
composer install
```

Your machine does not have to run PHP 8.1. Composer is told to resolve dependencies as if it were PHP 8.1.0 no matter what version runs it, so the lock file always reflects what the library targets. Before a release, run the suite on an actual 8.1 build.

## Development commands

| Command | What it does |
| --- | --- |
| `composer test` | Runs the PHPUnit suite in `tests/`. Needs a local `slapd`, see [Testing](#testing). |
| `composer phpstan` | Runs PHPStan at level 10 over `src/`, `bundle/`, and `tests/`. |
| `composer phpcs` | Checks `src/`, `bundle/`, and `tests/` against PSR-12. |
| `composer check` | Runs all three in order. Run this before you commit. |

Anything generated, such as the PHPStan and PHPUnit caches and the test server's database, lands in `working/`. Git ignores that directory.

## What's in the box

Everything lives under the `Gadget\Ldap` namespace.

| Class | Purpose |
| --- | --- |
| `Connection` | Holds the URI, bind DN, password, and options. Connects and binds on the first `getConnection()`. |
| `Query` | A base DN, filter, attribute list, and page size. `execute()` runs the paged search. |
| `Result` | One page of search results. Iterates entries as `array<string,string>`. |
| `DateFormat` | Static helpers for Active Directory's numeric timestamps and time intervals. |
| `LdapOptions` | Named constants for the `ldap_set_option()` codes and the paged results control OID. |
| `LdapException` | A `Gadget\Exception\Exception` thrown for every failure in this package. |
| `GadgetLdapBundle` | Registers `Connection` with a Symfony container. |

### Connecting

```php
use Gadget\Ldap\Connection;

$connection = new Connection(
    'ldaps://ad.example.edu',
    'cn=reader,ou=service,dc=example,dc=edu',
    'secret'
);
```

Nothing touches the network until `getConnection()` is called, either by you or by a query. At that point the global options are applied, `ldap_connect()` runs, the connection options are applied, and the bind happens. The handle is kept for later calls. Changing the URI, user, or password with the setters drops the handle, so the next call binds again. You can also hand in a handle you opened yourself with `setConnection()`.

The two option arrays are keyed by the `LDAP_OPT_*` codes. `LdapOptions` names the ones this package cares about. The defaults are:

```php
globalOptions: [],
connectOptions: [
    LdapOptions::PROTOCOL_VERSION => 3,
    LdapOptions::REFERRALS => 0,
]
```

Pass your own arrays to replace them. A connection option that the handle rejects is retried as a global option before the package gives up on it.

Every failure throws `LdapException`. A blank user or password is caught before the bind. The `ldap` extension also raises a PHP warning when a connect, bind, or search fails, so a logger that turns warnings into log lines will see both.

### Searching

```php
use Gadget\Ldap\Query;

$query = new Query(
    base: 'ou=people,dc=example,dc=edu',
    filter: ['(&', '(objectClass=user)', '(department=Math)', ')'],
    attributes: ['sAMAccountName', 'mail', 'whenCreated'],
    pageSize: 500
);

foreach ($connection->query($query, fn(array $values) => new Person($values)) as $person) {
    // ...
}
```

The filter is a string, or a list of strings that are joined with nothing in between, which keeps a long filter readable. `pageSize` defaults to 1000. The result is a generator: pages are fetched from the server one at a time as you iterate, and the paged results control is marked critical, so a server that cannot page refuses the search instead of silently returning everything.

The callback gets one `array<string,string>` per entry and whatever it returns is what the generator yields. Each array has exactly the keys you listed in `attributes`:

- An attribute the entry does not have is `''`.
- A multi-valued attribute is joined with commas, so `member` with three values is one string with two commas in it.

If `ldap_search()` fails, for example because the base DN does not exist or the filter is malformed, the generator throws `LdapException` with the message `Search error`.

If you want the page by page view, `Result` is public. Its `errorCode`, `matchedDN`, `errorMessage`, `referrals`, `controls`, and `cookie` properties are whatever `ldap_parse_result()` reported for that page, and iterating it walks that page's entries.

### Dates

Active Directory stores most timestamps in one of two numeric forms. `DateFormat` turns both into `DateTimeInterface` objects in UTC, or in a timezone you name.

```php
use Gadget\Ldap\DateFormat;

// whenCreated is "20240315123045.0Z" in LDAP. Strip the suffix and cast to int.
DateFormat::formatUTCTimestamp(20240315123045);                      // 2024-03-15 12:30:45 UTC
DateFormat::formatUTCTimestamp(20240315123045, 'America/New_York');  // 2024-03-15 08:30:45 EDT

// lastLogonTimestamp, pwdLastSet, and accountExpires are 100-nanosecond intervals since 1601.
DateFormat::formatTimeInterval(133500000000000000);                  // 2024-01-17 21:20:00 UTC
DateFormat::formatTimeInterval(0);                                   // null
```

`formatUTCTimestamp()` accepts values from `19700101000000` through `20991231235959` and throws `LdapException` for anything outside that range or with an impossible month, day, hour, minute, or second. It does not check the day against the month, so February 31 rolls over to March.

`formatTimeInterval()` returns `null` for any interval at or before the Unix epoch, which covers the `0` that Active Directory uses for "never". It is not affected by the process's default timezone.

`createLocalDateTime()` is the shared piece: it reads a `Y-m-d H:i:s` string as UTC and optionally converts it.

## Symfony bundle

Add the bundle to `config/bundles.php`:

```php
return [
    // ...
    Gadget\Ldap\GadgetLdapBundle::class => ['all' => true],
];
```

The bundle registers `Connection` as an autowired, autoconfigured service and reads its three constructor arguments from the environment:

| Variable | Constructor argument |
| --- | --- |
| `LDAP_URI` | `$uri` |
| `LDAP_USER` | `$user` |
| `LDAP_PASS` | `$pass` |

Any that are unset come through as empty strings, so the first `getConnection()` throws `Username/password is blank` rather than binding anonymously. The other classes in the package are plain value objects and are not registered as services.

## Testing

`composer test` runs the suite. There are no mocks. The tests that touch LDAP start a real OpenLDAP server (`slapd`) on a free loopback port, load it with a handful of `person` entries, and talk to it through the same `ldap_*` calls the library uses. The server runs once per test process and is stopped when PHPUnit exits. Its config, database, and log live under `working/slapd/`.

The fixture looks for the server in this order:

1. The path in the `GADGET_LDAP_SLAPD` environment variable.
2. `/opt/homebrew/opt/openldap/libexec/slapd`, where Homebrew puts it on a Mac.
3. A `slapd` on your `PATH`.

It also needs OpenLDAP's `core.schema`, taken from `GADGET_LDAP_SCHEMA` or `/opt/homebrew/etc/openldap/schema/core.schema`. On a Mac, `brew install openldap` provides both. The `slapd` that ships with macOS does not work because it lacks the `mdb` backend.

When no server can be found, the LDAP tests are skipped with a message that names those two variables. The date, option, exception, and bundle registration tests run everywhere.

[TESTPLAN.md](TESTPLAN.md) has the manual walkthrough.

## License

MIT. See [LICENSE](LICENSE).
