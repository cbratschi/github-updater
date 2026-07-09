---
name: github-updater-maintenance
description: Maintain the GitHub Updater WordPress plugin fork in this repository. Use for updater provider changes, Bitbucket/GitHub/GitLab/Gitea download fixes, WordPress and PHP compatibility work, Intelephense or PHPDoc cleanup, changelog/version bumps, local release zip builds, tags, and GitHub release preparation.
---

# GitHub Updater Maintenance

## Overview

Use this skill when working on this repository's WordPress updater fork. Start by reading `AGENTS.md`, then apply the workflow below to keep changes compatible with PHP 7.4, WordPress updater behavior, and the project's release conventions.

## Repository Map

- `github-updater.php`: plugin bootstrap, WordPress headers, version metadata.
- `src/GitHub_Updater/`: namespaced runtime code.
- `src/GitHub_Updater/API/`: provider-specific API implementations for GitHub, Bitbucket, GitLab, Gitea, zip files, and language packs.
- `src/GitHub_Updater/Traits/`: shared updater/authentication helpers.
- `tests/`, `phpunit.xml`, `bin/install-wp-tests.sh`: WordPress PHPUnit setup.
- `stubs/intelephense-wordpress.php`: local editor stubs for WordPress types.
- `CHANGES.md`: release history; keep `#### [unreleased]` first.

## Change Workflow

1. Run `git status --short --branch` before editing and preserve unrelated user changes.
2. Search with `rg` and avoid scanning `vendor/` unless dependency code is directly relevant.
3. Keep changes narrow to the affected provider, trait, or bootstrap path.
4. Preserve WordPress hooks, filters, option keys, plugin/theme headers, and release asset naming.
5. Keep PHP 7.4 compatibility unless the project constraints are explicitly updated.

## Type And PHPDoc Cleanup

- Prefer specific WordPress and project types over weakening annotations to `mixed`, `array`, or generic `object`.
- Use fully qualified global WordPress types in namespaced PHPDoc where needed, such as `\WP_Error`, `\WP_Theme`, `\Plugin_Upgrader`, and `\Theme_Upgrader`.
- Do not remove `@deprecated` tags while addressing editor warnings.
- Do not add dummy `unset()` calls for unused parameters; use accurate PHPDoc and signatures that match WordPress callbacks.
- When Intelephense reports undefined WordPress symbols, prefer improving `stubs/intelephense-wordpress.php` or PHPDoc imports instead of weakening source types.

## Validation

Run `php -l` on touched PHP files. For wider PHP changes, run:

```bash
find . -path ./vendor -prune -o -name '*.php' -print0 | xargs -0 -n 1 php -l
```

Run PHPUnit when the WordPress test suite is installed:

```bash
phpunit --config=phpunit.xml
```

## Release Checklist

1. Confirm the working tree and current branch.
2. Bump `Version` in `github-updater.php`.
3. Add a dated `CHANGES.md` entry below `#### [unreleased]`.
4. Commit the metadata change when the user asks for a release commit.
5. Create a bare version tag, for example `git tag 9.9.31`.
6. Build the local zip:

```bash
git archive -o /tmp/github-updater-<version>.zip --prefix=github-updater/ <version>
```

7. Push the branch and tag only when requested.
8. Create the GitHub release with asset `github-updater-<version>.zip` and verify the uploaded asset digest when possible.
