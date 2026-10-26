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
