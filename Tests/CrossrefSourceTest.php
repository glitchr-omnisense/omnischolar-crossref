<?php

namespace Omnischolar\Crossref\Tests;

use Omnischolar\Crossref\CrossrefSourceFactory;
use Omnischolar\Exception\NotSupportedException;
use Omnischolar\Exception\ProviderException;
use Omnischolar\Model\Contributor;
use Omnischolar\Model\Identifier;
use Omnischolar\Model\Scheme;
use Omnischolar\Model\Work;
use Omnischolar\Model\WorkType;
use Omnischolar\Source\Query;
use Omnischolar\Source\SourceInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * Fixtures recorded from api.crossref.org: on 2026-10-04, works-orcid.json
 * (works?filter=orcid:0000-0002-9127-1687, Juan Maldacena, newest first); on
 * 2026-11-13, work-doi.json (works/10.1140/epjd/s10053-022-00549-6) and
 * works-name.json (works?query.author=Alain Aspect, by relevance: the first twenty,
 * all signed with his name; the other people a fuzzy query brings are in
 * testANameIsMatchedByItsFirstGivenNameOrItsInitial).
 */
final class CrossrefSourceTest extends TestCase
{
    /** @var list<array{string, array<string, string>, array<string, list<string>>}> */
    private array $calls = [];

    private function source(?MockResponse $response = null): SourceInterface
    {
        $http = new MockHttpClient(function (string $method, string $url, array $options) use ($response): MockResponse {
            parse_str((string) parse_url($url, \PHP_URL_QUERY), $query);
            $path = rawurldecode((string) parse_url($url, \PHP_URL_PATH));
            $this->calls[] = [$path, $query, $options['normalized_headers'] ?? []];
            if ($response) {
                return $response;
            }
            $fixture = match (true) {
                '/works/10.1140/epjd/s10053-022-00549-6' === $path => 'work-doi.json',
                '/works' === $path && 'orcid:0000-0002-9127-1687' === ($query['filter'] ?? null) => 'works-orcid.json',
                '/works' === $path && 'Alain Aspect' === ($query['query_author'] ?? $query['query.author'] ?? null) => 'works-name.json',
                default => null,
            };

            return $fixture ? new MockResponse((string) file_get_contents(__DIR__.'/Fixtures/'.$fixture)) : new MockResponse('Resource not found.', ['http_code' => 404]);
        });

        return (new CrossrefSourceFactory($http))->create(['mailto' => 'support@glitchr.io']);
    }

    public function testTheReferenceMetadataOfADoi(): void
    {
        $work = $this->source()->work('doi:10.1140/EPJD/S10053-022-00549-6');

        self::assertSame('Bichromatic state-dependent disordered potential for Anderson localization of ultracold atoms', $work->title);
        self::assertSame(WorkType::ARTICLE, $work->type);
        self::assertSame('The European Physical Journal D', $work->venue->name);
        self::assertSame('Eur. Phys. J. D', $work->venue->abbreviation);
        self::assertSame(['1434-6060', '1434-6079'], $work->venue->issn);
        self::assertSame('Springer Science and Business Media LLC', $work->publisher);
        self::assertSame(2022, $work->year);
        self::assertSame('2022-11', $work->date);
        self::assertSame(['76', '11', null], [$work->volume, $work->issue, $work->pages], 'an article number, no pages');
        self::assertSame('https://creativecommons.org/licenses/by/4.0', $work->license);
        self::assertStringStartsWith('Abstract The ability to load ultracold atoms', $work->abstract, 'the JATS tags stripped');
        self::assertSame('Lecoutre', $work->authors[0]->family);
        self::assertSame('0000-0003-1461-9747', $work->authors[7]->orcid());
        self::assertContains('Aspect', array_map(static fn (Contributor $c) => $c->family, $work->authors));
        self::assertSame('crossref', $work->source);

        [, $query, $headers] = $this->calls[0];
        self::assertSame('support@glitchr.io', $query['mailto']);
        self::assertStringContainsString('mailto:support@glitchr.io', $headers['user-agent'][0]);
    }

    public function testTheWorksDepositedWithAnOrcid(): void
    {
        $page = $this->source()->works(Identifier::orcid('0000-0002-9127-1687'), new Query(limit: 10));

        self::assertSame(8, $page->total);
        self::assertCount(8, $page);
        self::assertTrue($page->isLast());
        self::assertSame('Real observers solving some imaginary problems', $page->works[0]->title, 'newest first');
        foreach ($page as $work) {
            self::assertContains('0000-0002-9127-1687', array_map(static fn (Contributor $c) => $c->orcid(), $work->authors));
        }
        [, $query] = $this->calls[0];
        self::assertSame('published', $query['sort']);
        self::assertSame('desc', $query['order']);
        self::assertArrayNotHasKey('offset', $query);
    }

    public function testByNameOnlyTheWorksSignedWithIt(): void
    {
        $works = $this->source()->works('Alain Aspect', new Query(limit: 20))->works;

        self::assertCount(20, $works);
        self::assertSame(['10.1051/978-2-7598-2265-2.c021', '10.1117/12.273744', '10.1515/9783110447989-004'], array_map(static fn (Work $w) => $w->doi(), \array_slice($works, 0, 3)));
        foreach ($works as $work) {
            self::assertContains('Aspect', array_map(static fn (Contributor $c) => $c->family, $work->authors));
        }
        $chapter = $works[2];
        self::assertSame(WorkType::CHAPTER, $chapter->type);
        self::assertSame('Einführung: John Bell und die zweite Quantenrevolution', $chapter->title);
        self::assertSame(['9783110447903', '9783110447989'], $chapter->isbns(), 'print and electronic');
        self::assertSame('score', $this->calls[0][1]['sort'], 'a fuzzy query, by relevance');
    }

    public function testANameIsMatchedByItsFirstGivenNameOrItsInitial(): void
    {
        $item = static fn (string $given, string $doi) => ['DOI' => $doi, 'type' => 'journal-article', 'title' => ['T'], 'author' => [['given' => $given, 'family' => 'Exemple']], 'container-title' => ['Optics &amp; Photonics News']];
        $body = json_encode(['status' => 'ok', 'message' => ['total-results' => 4, 'items' => [
            $item('Jeanne', '10.1234/a'), $item('J.', '10.1234/b'), $item('Julie', '10.1234/c'), $item('Jacques J.', '10.1234/d'),
        ]]]);
        $works = $this->source(new MockResponse((string) $body))->works('Jeanne Exemple')->works;

        self::assertSame(['10.1234/a', '10.1234/b'], array_map(static fn (Work $w) => $w->doi(), $works));
        self::assertSame('Optics & Photonics News', $works[0]->venue->name, 'the entity Crossref leaves decoded');
    }

    public function testFiltersAndPages(): void
    {
        $page = $this->source(new MockResponse('{"status":"ok","message":{"total-results":250,"items":[{"DOI":"10.1/x","type":"book-chapter","title":["A chapter"],"container-title":["A book"],"ISBN":["9781412996600"]}]}}'))
            ->search(new Query(text: 'quantum optics', author: 'Exemple', from: 2020, to: 2024, types: [WorkType::CHAPTER], limit: 100, cursor: '100'));

        [, $query] = $this->calls[0];
        self::assertSame('from-pub-date:2020,until-pub-date:2024-12-31,type:book-chapter,type:book-section,type:book-part,type:reference-entry', $query['filter']);
        self::assertSame('quantum optics', $query['query_bibliographic'] ?? $query['query.bibliographic']);
        self::assertSame('100', $query['offset']);
        self::assertSame('200', $page->next, 'an offset: Crossref\'s cursors do not sort by date');
        self::assertSame(WorkType::CHAPTER, $page->works[0]->type);
        self::assertSame('A book', $page->works[0]->venue->name);
    }

    public function testWhatCrossrefDoesNot(): void
    {
        $source = $this->source();
        self::assertNull($source->work('10.1140/unknown'));
        try {
            $source->author('0000-0002-9127-1687');
            self::fail('no author profiles');
        } catch (NotSupportedException) {
        }
        $this->expectException(ProviderException::class);
        $this->source(new MockResponse('{"status":"failed"}', ['http_code' => 400]))->search(new Query(text: 'x'));
    }

    /** The throttle every factory takes (SourceFactory): seconds left between two calls. */
    public function testTheThrottleLeavesItsSeconds(): void
    {
        $http = new MockHttpClient(static fn (): MockResponse => new MockResponse((string) file_get_contents(__DIR__.'/Fixtures/work-doi.json')));
        $source = (new CrossrefSourceFactory($http))->create(['throttle' => 0.3]);
        $start = microtime(true);
        $source->work('doi:10.1140/EPJD/S10053-022-00549-6');
        $source->work('doi:10.1140/EPJD/S10053-022-00549-6');
        self::assertGreaterThanOrEqual(0.29, microtime(true) - $start);
    }
}
