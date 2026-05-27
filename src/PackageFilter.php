<?php

declare(strict_types=1);

namespace Zingstudios\ComposerDelay;

use Composer\Package\BasePackage;
use DateTimeImmutable;
use DateTimeInterface;

final class PackageFilter
{
    public const REASON_TOO_NEW = 'too-new';
    public const REASON_NO_DATE = 'no-date';

    public function __construct(private readonly Config $config)
    {
    }

    /**
     * @param iterable<BasePackage> $packages
     * @return array{kept: list<BasePackage>, blocked: list<array{package: BasePackage, reason: string, message: string}>}
     */
    public function filter(iterable $packages, DateTimeImmutable $now): array
    {
        $kept = [];
        $blocked = [];

        $thresholdSeconds = $this->config->days * 86400;

        foreach ($packages as $package) {
            if ($this->isExcluded($package->getName())) {
                $kept[] = $package;
                continue;
            }

            if ($this->config->days === 0) {
                $kept[] = $package;
                continue;
            }

            $releaseDate = $package->getReleaseDate();

            if (!$releaseDate instanceof DateTimeInterface) {
                $blocked[] = [
                    'package' => $package,
                    'reason' => self::REASON_NO_DATE,
                    'message' => 'no release date available',
                ];
                continue;
            }

            $ageSeconds = $now->getTimestamp() - $releaseDate->getTimestamp();
            if ($ageSeconds < $thresholdSeconds) {
                $blocked[] = [
                    'package' => $package,
                    'reason' => self::REASON_TOO_NEW,
                    'message' => sprintf('released %s ago', self::formatAge($ageSeconds)),
                ];
                continue;
            }

            $kept[] = $package;
        }

        return ['kept' => $kept, 'blocked' => $blocked];
    }

    private function isExcluded(string $packageName): bool
    {
        $name = strtolower($packageName);
        foreach ($this->config->exclude as $pattern) {
            if (fnmatch($pattern, $name)) {
                return true;
            }
        }
        return false;
    }

    private static function formatAge(int $seconds): string
    {
        if ($seconds < 60) {
            return $seconds . ' second' . ($seconds === 1 ? '' : 's');
        }
        if ($seconds < 3600) {
            $m = intdiv($seconds, 60);
            return $m . ' minute' . ($m === 1 ? '' : 's');
        }
        if ($seconds < 86400) {
            $h = intdiv($seconds, 3600);
            return $h . ' hour' . ($h === 1 ? '' : 's');
        }
        $d = intdiv($seconds, 86400);
        return $d . ' day' . ($d === 1 ? '' : 's');
    }
}
