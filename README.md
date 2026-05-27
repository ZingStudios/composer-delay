# composer-delay

Composer plugin that refuses to install package versions newer than a configurable threshold (default: 3 days). Similar in spirit to pnpm's `minimumReleaseAge`. Defends against supply-chain attacks where a malicious release is published and pulled before maintainers can react.

## How it works

Subscribes to Composer's `pre-pool-create` event and filters too-new versions out of the candidate pool **before** the dependency solver runs. The solver then picks the newest acceptable version that still satisfies your constraints. If no acceptable version exists, Composer reports a normal "could not find a version matching" error and the plugin's warning above it explains why.

## Install

You can install the plugin either globally (protects every project you work on) or per-project (protects just one project, including for teammates and CI). Both can coexist.

### Globally

```sh
composer global require zingstudios/composer-delay
```

The plugin then applies to every Composer command that user runs.

### Per project

Run these in the project directory:

```sh
composer require --dev zingstudios/composer-delay
```

This commits the repository entry and the dependency into the project's `composer.json`, so teammates and CI pick up the protection automatically on their next `composer install`.

### Allow the plugin to run

Since Composer 2.2, plugins must be explicitly allowed by each project's `composer.json`. Add this to any project you want protected:

```json
{
  "config": {
    "allow-plugins": {
      "zingstudios/composer-delay": true
    }
  }
}
```

If you don't, Composer will prompt the first time it sees the plugin and write your answer into `composer.json`. Make sure to answer "yes" — answering "no" disables the plugin for that project. This applies whether the plugin is installed globally or per-project.

## Configure

In any project's `composer.json`:

```json
{
  "extra": {
    "composer-delay": {
      "days": 3,
      "exclude": [
        "zingstudios/*",
        "symfony/*"
      ]
    }
  }
}
```

- **`days`** — minimum age in days. Defaults to `3`. Set to `0` to disable.
- **`exclude`** — list of package name patterns to skip the check for. Supports `fnmatch` globs (e.g. `vendor/*`). Case-insensitive. Excluded packages are also allowed when their release date is missing.

If a configuration block is absent, the plugin still runs with default settings.

## Behavior notes

- **Fallback is automatic.** If `^2.0` resolves to a too-new `2.5.0`, the plugin removes `2.5.0` from the pool and the solver falls back to the next acceptable version (e.g. `2.4.0`). You'll see a warning, not a failure.
- **Platform packages are always ignored.** PHP itself, `ext-*`, `lib-*`, and Composer's own meta packages (`composer`, `composer-plugin-api`, `composer-runtime-api`), plus the root project, are skipped — they don't come from Packagist and don't carry release dates by design.
- **Missing release dates are treated as blocked.** Path repositories and some VCS configurations report no release date; add those packages to `exclude` to allow them.
- **Lockfile installs can fail.** `composer install` from a lockfile that pins a too-new version will fail, by design. Run `composer update <package>` to re-resolve against the filtered pool.
- **One-off bypass.** Either bump `days: 0` temporarily, add the package to `exclude`, or `composer global remove zingstudios/composer-delay` and reinstall after.

## Development

```sh
composer install
vendor/bin/phpunit
```
