# Test Plan

Walk through this list before tagging a release or merging a change that touches `composer.json`, the analyzers, or the test suite. Run every command from the repository root.

## Setup

- [ ] `composer install` finishes without errors and without asking to change the lock file.
- [ ] `composer validate` reports the manifest is valid.
- [ ] `working/` exists after the first run and is not shown in `git status`.
- [ ] A `slapd` with the `mdb` backend is available at `/opt/homebrew/opt/openldap/libexec/slapd`, on `PATH`, or named by `GADGET_LDAP_SLAPD`, and `core.schema` is at `/opt/homebrew/etc/openldap/schema/core.schema` or named by `GADGET_LDAP_SCHEMA`.

## Automated checks

- [ ] `composer phpcs` reports no errors and no warnings for `src/`, `bundle/`, and `tests/`.
- [ ] `composer phpstan` reports `No errors` at level 10 for `src/`, `bundle/`, and `tests/`.
- [ ] `composer test` passes with no warnings, deprecations, notices, or skipped tests in the output.
- [ ] `composer check` runs all three in order and exits 0.
- [ ] After `composer test`, `pgrep -fl slapd` shows no server left behind and `working/slapd/` is empty.
- [ ] With `GADGET_LDAP_SLAPD=/nonexistent composer test`, the LDAP tests are reported as skipped with a message naming `GADGET_LDAP_SLAPD` and `GADGET_LDAP_SCHEMA`, and the rest still pass.

## Functional coverage

Confirm each area has passing tests under `tests/` that cover the listed behaviors.

- [ ] `Connection`: `getConnection()` binds once and returns the same handle afterwards; `setUri()`, `setUser()`, and `setPass()` return `$this` and drop the cached handle; `setConnection()` injects a handle without binding and `setConnection(null)` forces a new bind; a blank user or password throws `Username/password is blank` before any bind; a wrong password raises the `ldap_bind()` warning and throws `ldap_bind() failed: {json}` naming the user; an unreachable server throws on bind; an invalid URI raises the `ldap_connect()` warning and throws `ldap_connect() failed`; an invalid global option throws `ldap_set_option() failed` with `"ldap":null`; an invalid connect option throws with `"ldap":"resource"`; `query()` runs a `Query` against this connection.
- [ ] `Query`: five entries with a page size of 2 come back in three searches; a page size larger than the result runs one search; a filter given as an array is joined into one filter; the create callback's return value is what the generator yields; a filter that matches nothing yields nothing; a missing base DN and a malformed filter each raise the `ldap_search()` warning and throw `Search error`.
- [ ] `Result`: a successful page has `errorCode` 0, empty `matchedDN` and `errorMessage`, no referrals, and the paged results control; the cookie is set while more pages remain, empty on the last page, and empty when the search was not paged; each entry is an array keyed by the requested attributes; a missing attribute is `''`; multi-valued attributes are joined with commas; only the page the server returned is iterated; an empty result iterates nothing; a result can be iterated more than once.
- [ ] `DateFormat` with the process timezone set to `America/New_York`: `formatUTCTimestamp()` reads the digits as UTC, converts to a named timezone, accepts both range boundaries, and rejects values outside the range or with month 0 or 13, day 0 or 32, hour 24, minute 60, or second 60 with `Invalid timestamp: N, part M`; `formatTimeInterval()` converts a Windows file time to UTC and to a named timezone, is `null` at or before the Unix epoch, and resolves one second after it; `createLocalDateTime()` reads the string as UTC and converts to a named timezone.
- [ ] `LdapOptions`: the four constants equal `LDAP_OPT_DEBUG_LEVEL`, `LDAP_OPT_PROTOCOL_VERSION`, `LDAP_OPT_REFERRALS`, and `LDAP_CONTROL_PAGEDRESULTS`.
- [ ] `LdapException`: extends `Gadget\Exception\Exception`, keeps a plain message, formats `[format, args]` messages, and keeps the previous exception.
- [ ] `GadgetLdapBundle`: extends `Bundle` and exposes `GadgetLdapExtension` under the alias `gadget_ldap`.
- [ ] `GadgetLdapExtension`: registers `Connection` as an autowired, autoconfigured service with `$uri`, `$user`, and `$pass` bound to `LDAP_URI`, `LDAP_USER`, and `LDAP_PASS`; a container compiled with those variables set builds a `Connection` that binds to the test server; a container compiled without them builds a `Connection` whose first `getConnection()` throws `Username/password is blank`.

## Consuming the package

- [ ] In a scratch project, `composer require gsu-cetloe/gadget-ldap-php` installs `gsu-cetloe/gadget-core-php` and `gsu-cetloe/symfony-bundle`, and refuses to install on a PHP without the `ldap` extension.
- [ ] In a scratch Symfony app with `GadgetLdapBundle` enabled and `LDAP_URI`, `LDAP_USER`, and `LDAP_PASS` set in `.env.local`, a service that type-hints `Connection` autowires and a `Query` against a real directory returns entries.
- [ ] Against Active Directory, a search with a page size smaller than the number of matching entries returns every entry exactly once.
- [ ] Against Active Directory, `whenCreated` stripped of its `.0Z` suffix and cast to `int` goes through `formatUTCTimestamp()`, and `lastLogonTimestamp` goes through `formatTimeInterval()`, and both match what the directory tools show.

## Known gaps

- The suite runs against OpenLDAP. Active Directory's paging, referral, and attribute behavior are only covered by the manual steps above.
- `Result` throws an `LdapException` with an empty message when `ldap_parse_result()` fails. No test triggers that path because the fixture server never produces an unparseable result.
- `Connection` retries a rejected connect option as a global option. The suite proves the failure when both reject it, but not the case where only the global call succeeds, because no option on this platform behaves that way.
- Tests run on whatever PHP is installed locally. Composer resolves dependencies as if PHP were 8.1.0, but the behavior checked here is that of the local interpreter.
