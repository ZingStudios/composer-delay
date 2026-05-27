<?php

declare(strict_types=1);

namespace Zingstudios\ComposerDelay\Tests;

use PHPUnit\Framework\TestCase;
use Zingstudios\ComposerDelay\Config;

final class ConfigTest extends TestCase
{
    public function testDefaultsWhenNoExtra(): void
    {
        $config = Config::fromExtra([]);

        $this->assertSame(3, $config->days);
        $this->assertSame([], $config->exclude);
    }

    public function testReadsDaysAndExclude(): void
    {
        $config = Config::fromExtra([
            'composer-delay' => [
                'days' => 7,
                'exclude' => ['zingstudios/*', 'symfony/console'],
            ],
        ]);

        $this->assertSame(7, $config->days);
        $this->assertSame(['zingstudios/*', 'symfony/console'], $config->exclude);
    }

    public function testIgnoresNonStringExcludeEntries(): void
    {
        $config = Config::fromExtra([
            'composer-delay' => [
                'exclude' => ['valid/pkg', 123, null, '', ['nested']],
            ],
        ]);

        $this->assertSame(['valid/pkg'], $config->exclude);
    }

    public function testNegativeDaysFallsBackToDefault(): void
    {
        $config = Config::fromExtra([
            'composer-delay' => ['days' => -1],
        ]);

        $this->assertSame(3, $config->days);
    }

    public function testZeroDaysIsHonored(): void
    {
        $config = Config::fromExtra([
            'composer-delay' => ['days' => 0],
        ]);

        $this->assertSame(0, $config->days);
    }

    public function testMalformedExtraFallsBackToDefaults(): void
    {
        $config = Config::fromExtra(['composer-delay' => 'not-an-array']);

        $this->assertSame(3, $config->days);
        $this->assertSame([], $config->exclude);
    }
}
