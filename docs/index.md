# omnischolar/crossref

Crossref for [omnisense/omnischolar](https://github.com/glitchr-omnisense/omnischolar): the
reference metadata of any work with a Crossref DOI - title, authors and their ORCIDs, journal or
book, volume, pages, publisher, ISBNs, licence, the citation count Crossref knows - the works an
ORCID was deposited with, the works of a name, and a bibliographic search. The source calls
Crossref's REST API, without a key:

| Operation | Request |
|---|---|
| `work($doi)` | `GET https://api.crossref.org/works/<doi>?mailto=` |
| `works($orcid)` | `GET .../works?filter=orcid:<orcid>,...&rows=&offset=&sort=&order=&select=&mailto=` |
| `works($name)` | `GET .../works?query.author=<name>&sort=score&order=desc...`, filtered here |
| `search($query)` | `GET .../works?query.bibliographic=&query.author=...` |
| `author()` | not supported: Crossref has no author profiles |

The base address is `CrossrefSource::BASE_URI` (`https://api.crossref.org/`). There is no
sandbox.

## Installation

```sh
composer require omnischolar/crossref
```

Requires PHP 8.2, `omnisense/omnischolar` (1.x) and `symfony/http-client` (6.4, 7 or 8). In a
Symfony application with `OmnischolarBundle`, the factory is registered as soon as the package is
installed.

## Credentials

None. A `mailto` puts the calls in Crossref's "polite" pool (three calls a second, as Crossref's
documentation said in 2026); without it, the public pool.

## Options

| Option | Type | Default | Required | |
|---|---|---|---|---|
| `mailto` | string | `null` | no | sent with every call as `mailto`, and in the `User-Agent` |
| `user_agent` | string | `omnischolar/1.x (+https://github.com/glitchr-omnisense/omnischolar)` and the `mailto` | no | |
| `base_uri` | string | `https://api.crossref.org/` | no | |
| `throttle` | float | `0.0` | no | seconds left between two calls of the source |

## Capabilities

`capabilities()`: `works`, `work`, `search`, `citations`, `abstracts`.

| Operation | Supported | Reads |
|---|---|---|
| `author()` | no (`does not know authors`) | |
| `works()` | yes | an ORCID (the works deposited with it), or a name |
| `work()` | yes | a DOI (bare, `doi:`, or its doi.org address) |
| `search()` | yes | `text` and `title` (`query.bibliographic`), `author` (`query.author`) |

## Behaviour

**Lists.** `from` → `filter=from-pub-date:<year>`, `to` → `until-pub-date:<year>-12-31`, `types`
→ `type:` with Crossref's types for each kind (`journal-article`; `book`, `monograph`,
`edited-book`, `reference-book`, `proceedings`; `book-chapter`, `book-section`, `book-part`,
`reference-entry`; `proceedings-article`; `posted-content`; `dissertation`; `report`,
`report-component`, `standard`; `dataset`; `peer-review`; `other`, `component`); `domains` is
refused; `openAccess` is not read. `sort`: `newest` → `published desc`, `oldest` → `published
asc`, `cited` → `is-referenced-by-count desc`, `relevance` → `score desc`. `rows` = `limit` 1 to
1000; the cursor is an offset (Crossref's cursors cannot sort by date), and paging stops at 10 000
(`MAX_OFFSET`). Lists ask only the fields the models read (`select`); `work()` reads the whole
record.

**Works by name.** Crossref's author query is fuzzy: its answers come by relevance and are kept
only when one of their authors has the family name asked and the same first given name - or its
initial, when the work gives initials only (`K. Nakatani`, not `Kenji Nakatani`). `Page::$total`
is then `null`.

**A work.** Title and subtitle; type from Crossref's (`posted-content` is a preprint unless its
`subtype` says otherwise); authors and editors with given and family names, ORCID and
affiliations; year and date from `issued` (else `published`); venue from the container title (a
journal, a book for a chapter, a conference for a proceedings article, a book series for a book, a
repository for a preprint; its ISSNs, publisher and abbreviation) or the event's name; publisher,
volume, issue, pages, edition; abstract (JATS tags stripped); language; the publisher's landing
page as `url`; licence; citation count (`is-referenced-by-count`); subjects as keywords;
identifiers: the DOI and every ISBN (print and electronic). No open-access status, no PDF.

## Example

```php
<?php

require __DIR__.'/vendor/autoload.php';

use Omnischolar\Crossref\CrossrefSourceFactory;
use Symfony\Component\HttpClient\HttpClient;

$crossref = (new CrossrefSourceFactory(HttpClient::create()))->create(['mailto' => getenv('OMNISCHOLAR_MAILTO') ?: null]);

$w = $crossref->work('10.1039/D4SC04973J');
echo $w->title, "\n", $w->venue?->name, ' ', $w->volume, '(', $w->issue, ') ', $w->pages, '; ', $w->publisher, '; cited ', $w->citations, '; ', $w->license, "\n";
foreach ($w->authors as $a) {
    echo '  ', $a->sortName(), ' ', $a->orcid() ?? '', "\n";
}
```

```
Acid-sensitive photoswitches: towards catalytic on-demand release of stored light energy
Chemical Science 15(39) 16034-16039; Royal Society of Chemistry (RSC); cited 8; http://creativecommons.org/licenses/by/3.0/
  Chocron, Léa 
  Baggi, Nicolò 0000-0003-4743-5151
  Ribeiro, Enrique 0000-0001-5795-7418
  Goetz, Vincent 0000-0001-5700-6487
  Yu, Pei 0000-0003-1801-1579
  Nakatani, Keitaro 0009-0005-1387-7295
  Métivier, Rémi 0000-0001-5612-8327
```

(called live on 2026-10-26 at 14:18 UTC, from the core's harness image, without a `mailto`)

In Symfony:

```yaml
omnischolar:
    sources:
        crossref: { factory: crossref, options: { mailto: '%env(OMNISCHOLAR_MAILTO)%' } }
```

## Errors

| Case | Result |
|---|---|
| an unknown DOI | `null` |
| `author()`; `work()` of anything but a DOI; an `Identifier` other than an ORCID given to `works()`; `domains` | `NotSupportedException` |
| another 4xx | `ProviderException` |
| a 429 | `RateLimitedException` |
| a 5xx, a timeout | `UnavailableException` |

## Verified, and not

Tested offline: `Tests/CrossrefSourceTest.php` (6 tests), on `MockHttpClient` with answers recorded
on 2026-10-04 - a work by DOI (10.1039/d4sc04973j), the eight works deposited with Juan
Maldacena's ORCID, the answer to `query.author=Monica Neagoy` (four of hers among others). Called
live on 2026-10-04 and on 2026-10-26 (the example above).

Limits of the service: works by name rest on a fuzzy query - a name shared with other people of
the same family name and first name cannot be told apart; works by ORCID are those whose
publisher deposited the ORCID (some placeholder ORCIDs are used by thousands of unrelated works);
offsets stop at 10 000.

## Responsibility

An independent, unofficial client, not affiliated with Crossref. Whoever runs it complies with
Crossref's terms ([REST API documentation](https://www.crossref.org/documentation/retrieve-metadata/rest-api/)):
see the README's Responsibility section.
