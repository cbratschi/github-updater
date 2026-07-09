# Agent Notes

## Project Context

- This repository is a fork of GitHub Updater, a WordPress plugin for updating plugins, themes, and language packs from GitHub, Bitbucket, GitLab, Gitea, and zip files.
- The minimum supported PHP version is 7.4. Keep syntax and type features compatible with PHP 7.4 unless the plugin header and Composer constraints are intentionally changed.
- Runtime code lives under `src/GitHub_Updater/` with the `Fragen\GitHub_Updater` namespace. The plugin bootstrap and WordPress plugin headers are in `github-updater.php`.
- Avoid changing vendored code under `vendor/` unless the task is explicitly about bundled dependencies.

## Working Rules

- Check `git status --short --branch` before editing. Do not unstage, reset, or rewrite user changes unless explicitly asked.
- Preserve existing public headers, hooks, filters, option names, updater metadata, and release asset names; external WordPress sites may depend on them.
- Prefer the existing WordPress APIs and local helper methods over new abstractions.
- Keep PHPDoc types specific where useful, including WordPress types such as `WP_Error`, `WP_Theme`, `Plugin_Upgrader`, and `Theme_Upgrader`.
- Do not remove `@deprecated` tags or add dummy `unset()` calls just to silence editor warnings.
- Follow the surrounding file style for spacing and docblocks. This fork contains older upstream code and local customizations, so avoid broad formatting churn.

## Validation

- For PHP changes, run syntax checks on touched files with `php -l <file>`.
- For broad PHP changes, run a repo syntax pass excluding dependencies, for example:

```bash
find . -path ./vendor -prune -o -name '*.php' -print0 | xargs -0 -n 1 php -l
```

- PHPUnit is configured by `phpunit.xml` and expects the WordPress test suite from `bin/install-wp-tests.sh`. Run it when the local WordPress test environment is available.
- Editor/static-analysis cleanup should preserve meaningful types instead of weakening PHPDoc to generic `mixed` or `array` where the code expects WordPress objects.

## Release Process

- Update the plugin `Version` header in `github-updater.php`.
- Add a dated entry near the top of `CHANGES.md`; keep `#### [unreleased]` at the top.
- Tags use the bare version number, for example `9.9.31`, not `v9.9.31`.
- The release zip should use this archive shape:

```bash
git archive -o /tmp/github-updater-<version>.zip --prefix=github-updater/ <version>
```

- The GitHub release asset should be named `github-updater-<version>.zip`.
