<?php

namespace Omnischolar\Crossref\Tests;

use Omnischolar\Crossref\CrossrefSourceFactory;
use Omnischolar\Source\HttpSource;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;

/** Crossref's pools as its policy: public by default, polite with a contact address; single records and lists counted apart. */
final class PolicyTest extends TestCase
{
    public function testThePublicPoolByDefaultThePolitePoolWithAMailto(): void
    {
        $public = (new CrossrefSourceFactory(new MockHttpClient()))->create();
        \assert($public instanceof HttpSource);
        $policy = $public->guard()->policy();
        self::assertSame('crossref', $policy->provider);
        self::assertSame(1, $policy->concurrency);
        self::assertSame(5, $public->guard()->remaining('single'));
        self::assertSame(1, $public->guard()->remaining('list'));

        $polite = (new CrossrefSourceFactory(new MockHttpClient()))->create(['mailto' => 'support@glitchr.io']);
        \assert($polite instanceof HttpSource);
        self::assertSame(3, $polite->guard()->policy()->concurrency);
        self::assertSame(10, $polite->guard()->remaining('single'));
        self::assertSame(3, $polite->guard()->remaining('list'));
        self::assertNotNull($polite->guard()->policy()->provenance->verifiedAt);
    }
}
