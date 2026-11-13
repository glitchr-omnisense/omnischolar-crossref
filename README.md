# omnischolar/crossref

Crossref for [omnisense/omnischolar](https://github.com/glitchr-omnisense/omnischolar): the reference
metadata of any work with a Crossref DOI - title, authors and their ORCIDs, journal or book,
volume, pages, publisher, ISBNs, licence, the citation count Crossref knows - the works deposited
with an ORCID, the works signed with a name, a bibliographic search. No key; with a `mailto`,
the calls go to Crossref's polite pool.

```php
$crossref = (new CrossrefSourceFactory($http))->create(['mailto' => 'support@glitchr.io']);
$crossref->work('10.1039/d4sc04973j');
$crossref->works('0000-0002-9127-1687');       // deposited with that ORCID
$crossref->works('Monica Neagoy');             // signed with that name
```

```yaml
omnischolar:
    sources:
        crossref: { factory: crossref, options: { mailto: 'support@glitchr.io' } }
```

The client is written directly on Symfony's HttpClient: the API is plain JSON, and a client
library (renanbr/crossref-client) would add a dependency without removing code.

See [docs/](docs/index.md).

## Install

```sh
composer require omnischolar/crossref
```

It brings the family's core, `omnisense/omnischolar`, and `omnisense/contracts` with it. No account and no key; a `mailto` of yours puts the calls in Crossref's polite pool ([`policy.json`](policy.json)). `$http` is `HttpClient::create()` in plain PHP.

## Usage policy

Declared as data in [`policy.json`](policy.json) (read at https://www.crossref.org/documentation/retrieve-metadata/rest-api/access-and-authentication/ on 2026-11-10), and honoured on every request by the
core: a limit reached waits when its window ends within `max_wait` seconds, else the request is not
sent ([Usage policies](https://github.com/glitchr-omnisense/omnischolar/blob/1.x/docs/policies.md)).

- Limits: 5 a second for single; 1 a second for list; at most 1 in flight.
- Licence: not stated; commercial use: allowed.
- Credit: not required.
- Needs: nothing.
- With a `mailto`: the polite pool, 10 single records and 3 lists a second, 3 in flight (`CrossrefSource::usagePolicy()`).

## Responsibility

This package is an independent, unofficial client: it is not affiliated with, endorsed by or
sponsored by Crossref. It reaches their service on behalf of whoever runs it, and it does not
redistribute their data beyond the short excerpts recorded as test fixtures. Its MIT licence covers
this package's code only and grants no right to the service or to its data.

Whoever installs and runs this package is responsible for complying with the Crossref REST API's
terms ([REST API
documentation](https://www.crossref.org/documentation/retrieve-metadata/rest-api/)), for obtaining
whatever they require (an account, a key, a licence or subscription, an attribution or a citation, a
rate limit to respect), and for any use made of what it returns. The authors publish it free of
charge, make no commercial use of it, and accept no liability for how it is used (see
[LICENSE](LICENSE)).

License: MIT since 2026-10-09; earlier versions remain published under LGPL-3.0-or-later.
