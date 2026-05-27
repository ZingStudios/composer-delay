<?php

declare(strict_types=1);

namespace Zingstudios\ComposerDelay;

use Composer\Composer;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginEvents;
use Composer\Plugin\PluginInterface;
use Composer\Plugin\PrePoolCreateEvent;
use DateTimeImmutable;

final class Plugin implements PluginInterface, EventSubscriberInterface
{
    private Composer $composer;
    private IOInterface $io;

    public function activate(Composer $composer, IOInterface $io): void
    {
        $this->composer = $composer;
        $this->io = $io;
    }

    public function deactivate(Composer $composer, IOInterface $io): void
    {
    }

    public function uninstall(Composer $composer, IOInterface $io): void
    {
    }

    /**
     * @return array<string, string>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            PluginEvents::PRE_POOL_CREATE => 'onPrePoolCreate',
        ];
    }

    public function onPrePoolCreate(PrePoolCreateEvent $event): void
    {
        $config = Config::fromExtra($this->composer->getPackage()->getExtra());

        if ($config->days === 0) {
            return;
        }

        $filter = new PackageFilter($config);
        $result = $filter->filter($event->getPackages(), new DateTimeImmutable());

        if ($result['blocked'] === []) {
            return;
        }

        $event->setPackages($result['kept']);
        $this->reportBlocked($result['blocked'], $config);
    }

    /**
     * @param list<array{package: \Composer\Package\BasePackage, reason: string, message: string}> $blocked
     */
    private function reportBlocked(array $blocked, Config $config): void
    {
        $count = count($blocked);
        $this->io->writeError(sprintf(
            '<warning>composer-delay: skipped %d package version%s (threshold: %d day%s)</warning>',
            $count,
            $count === 1 ? '' : 's',
            $config->days,
            $config->days === 1 ? '' : 's',
        ));

        foreach ($blocked as $entry) {
            $this->io->writeError(sprintf(
                '  - %s %s — %s',
                $entry['package']->getPrettyName(),
                $entry['package']->getPrettyVersion(),
                $entry['message'],
            ));
        }

        $this->io->writeError(
            '  Older acceptable versions, if any, will be used. Add to'
            . ' extra.composer-delay.exclude in composer.json to allow.'
        );
    }
}
