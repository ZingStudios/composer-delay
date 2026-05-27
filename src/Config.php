<?php

declare(strict_types=1);

namespace Zingstudios\ComposerDelay;

final class Config
{
    public const DEFAULT_DAYS = 3;

    /** @var list<string> */
    public readonly array $exclude;

    /**
     * @param list<string> $exclude
     */
    public function __construct(
        public readonly int $days,
        array $exclude,
    ) {
        $normalized = [];
        foreach ($exclude as $pattern) {
            $normalized[] = strtolower($pattern);
        }
        $this->exclude = $normalized;
    }

    /**
     * @param array<string, mixed> $extra The root package's full extra array.
     */
    public static function fromExtra(array $extra): self
    {
        $raw = $extra['composer-delay'] ?? [];
        if (!is_array($raw)) {
            $raw = [];
        }

        $days = isset($raw['days']) && is_int($raw['days']) && $raw['days'] >= 0
            ? $raw['days']
            : self::DEFAULT_DAYS;

        $exclude = [];
        if (isset($raw['exclude']) && is_array($raw['exclude'])) {
            foreach ($raw['exclude'] as $pattern) {
                if (is_string($pattern) && $pattern !== '') {
                    $exclude[] = $pattern;
                }
            }
        }

        return new self($days, $exclude);
    }
}
