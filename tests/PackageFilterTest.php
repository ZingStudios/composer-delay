<?php

declare(strict_types=1);

namespace Zingstudios\ComposerDelay\Tests;

use Composer\Package\Package;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Zingstudios\ComposerDelay\Config;
use Zingstudios\ComposerDelay\PackageFilter;

final class PackageFilterTest extends TestCase
{
    private DateTimeImmutable $now;

    protected function setUp(): void
    {
        $this->now = new DateTimeImmutable('2026-05-27 12:00:00');
    }

    public function testTooNewPackageIsBlocked(): void
    {
        $filter = new PackageFilter(new Config(days: 3, exclude: []));
        $pkg = $this->makePackage('vendor/foo', '1.0.0', $this->now->modify('-1 hour'));

        $result = $filter->filter([$pkg], $this->now);

        $this->assertSame([], $result['kept']);
        $this->assertCount(1, $result['blocked']);
        $this->assertSame(PackageFilter::REASON_TOO_NEW, $result['blocked'][0]['reason']);
        $this->assertStringContainsString('1 hour ago', $result['blocked'][0]['message']);
    }

    public function testOldEnoughPackageIsKept(): void
    {
        $filter = new PackageFilter(new Config(days: 3, exclude: []));
        $pkg = $this->makePackage('vendor/foo', '1.0.0', $this->now->modify('-10 days'));

        $result = $filter->filter([$pkg], $this->now);

        $this->assertCount(1, $result['kept']);
        $this->assertSame([], $result['blocked']);
    }

    public function testBoundaryAtExactlyThresholdIsKept(): void
    {
        $filter = new PackageFilter(new Config(days: 3, exclude: []));
        $pkg = $this->makePackage('vendor/foo', '1.0.0', $this->now->modify('-3 days'));

        $result = $filter->filter([$pkg], $this->now);

        $this->assertCount(1, $result['kept']);
        $this->assertSame([], $result['blocked']);
    }

    public function testExcludedPackageIsKeptEvenIfTooNew(): void
    {
        $filter = new PackageFilter(new Config(days: 3, exclude: ['vendor/foo']));
        $pkg = $this->makePackage('vendor/foo', '1.0.0', $this->now->modify('-1 hour'));

        $result = $filter->filter([$pkg], $this->now);

        $this->assertCount(1, $result['kept']);
        $this->assertSame([], $result['blocked']);
    }

    public function testExcludeGlobMatches(): void
    {
        $filter = new PackageFilter(new Config(days: 3, exclude: ['symfony/*']));
        $pkg = $this->makePackage('symfony/console', '7.0.0', $this->now->modify('-1 hour'));

        $result = $filter->filter([$pkg], $this->now);

        $this->assertCount(1, $result['kept']);
        $this->assertSame([], $result['blocked']);
    }

    public function testExcludeIsCaseInsensitive(): void
    {
        $filter = new PackageFilter(new Config(days: 3, exclude: ['Vendor/Foo']));
        $pkg = $this->makePackage('vendor/foo', '1.0.0', $this->now->modify('-1 hour'));

        $result = $filter->filter([$pkg], $this->now);

        $this->assertCount(1, $result['kept']);
    }

    public function testNullReleaseDateIsBlocked(): void
    {
        $filter = new PackageFilter(new Config(days: 3, exclude: []));
        $pkg = $this->makePackage('vendor/foo', '1.0.0', null);

        $result = $filter->filter([$pkg], $this->now);

        $this->assertSame([], $result['kept']);
        $this->assertCount(1, $result['blocked']);
        $this->assertSame(PackageFilter::REASON_NO_DATE, $result['blocked'][0]['reason']);
    }

    public function testNullReleaseDateIsKeptWhenExcluded(): void
    {
        $filter = new PackageFilter(new Config(days: 3, exclude: ['vendor/foo']));
        $pkg = $this->makePackage('vendor/foo', '1.0.0', null);

        $result = $filter->filter([$pkg], $this->now);

        $this->assertCount(1, $result['kept']);
        $this->assertSame([], $result['blocked']);
    }

    public function testZeroDaysIsNoOp(): void
    {
        $filter = new PackageFilter(new Config(days: 0, exclude: []));
        $tooNew = $this->makePackage('vendor/foo', '1.0.0', $this->now->modify('-1 minute'));
        $noDate = $this->makePackage('vendor/bar', '1.0.0', null);

        $result = $filter->filter([$tooNew, $noDate], $this->now);

        $this->assertCount(2, $result['kept']);
        $this->assertSame([], $result['blocked']);
    }

    public function testMixedSetKeepsOldBlocksNew(): void
    {
        $filter = new PackageFilter(new Config(days: 3, exclude: []));
        $old = $this->makePackage('vendor/foo', '1.0.0', $this->now->modify('-30 days'));
        $new = $this->makePackage('vendor/foo', '2.0.0', $this->now->modify('-2 hours'));

        $result = $filter->filter([$old, $new], $this->now);

        $this->assertCount(1, $result['kept']);
        $this->assertSame('1.0.0.0', $result['kept'][0]->getVersion());
        $this->assertCount(1, $result['blocked']);
        $this->assertSame('2.0.0.0', $result['blocked'][0]['package']->getVersion());
    }

    public function testPlatformPackagesAreSilentlyKept(): void
    {
        $filter = new PackageFilter(new Config(days: 3, exclude: []));
        $names = [
            'php',
            'php-64bit',
            'ext-mbstring',
            'ext-pdo_sqlite',
            'lib-openssl',
            'composer',
            'composer-plugin-api',
            'composer-runtime-api',
        ];
        $packages = array_map(fn(string $n) => $this->makePackage($n, '1.0.0', null), $names);

        $result = $filter->filter($packages, $this->now);

        $this->assertCount(count($names), $result['kept']);
        $this->assertSame([], $result['blocked']);
    }

    public function testRootPackageIsSilentlyKept(): void
    {
        $filter = new PackageFilter(new Config(days: 3, exclude: []));
        $pkg = $this->makePackage('__root__', '1.0.0', null);

        $result = $filter->filter([$pkg], $this->now);

        $this->assertCount(1, $result['kept']);
        $this->assertSame([], $result['blocked']);
    }

    private function makePackage(string $name, string $prettyVersion, ?DateTimeImmutable $releaseDate): Package
    {
        $normalized = $prettyVersion . '.0';
        $pkg = new Package($name, $normalized, $prettyVersion);
        if ($releaseDate !== null) {
            $pkg->setReleaseDate($releaseDate);
        }
        return $pkg;
    }
}
