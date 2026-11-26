# Laravel Consent

This repository is a Laravel package. Keep the package focused, idiomatic, and easy for Laravel developers to install, test, and maintain.

`IMPLEMENTATION.md` is the plan: architecture, layers, request flow, order of work. Read it before planning or implementing a feature.

## Vertical Slices

Work ships as **tracer bullets**: tiny vertical slices, one per pull request, each small enough to review in one sitting.

- A slice cuts one narrow behaviour end to end through every layer it touches: PHP, route, JS, Blade/UI, config, translations, tests. Never a horizontal slice of one layer (all the API, then all the JS, then all the UI).
- A slice adds only code that the slice itself uses and tests. No class, method, config key, option, or file that only a future slice needs: if nothing calls it yet, it waits for the slice that calls it.
- A completed slice is demoable or verifiable on its own and leaves `composer qa` green.
- Prefer the smaller slice when in doubt: part of a feature is a valid slice if it works end to end.
- Planning: break work into slices with `to-tickets`; each ticket is one slice.

## Package Conventions

- Use Laravel-native package APIs and the existing service provider shape before adding abstractions.
- Keep package names, namespaces, Composer metadata, documentation, and examples aligned with `simonecerruti/laravel-consent`. The short slug `consent` names the config key, the view/translation namespaces, routes, commands, and publish tags (`consent-*`).
- Add only the files and dependencies needed for the package behavior being implemented.
- Prefer explicit Laravel package code over helper abstractions unless the extension point is real.
- Concrete classes are `final`.
- Everything other people read is written in English: code, comments, docs, commit messages, branches, issues, comments and PRs on GitHub. Talk to the maintainer in Italian.
- Keep tests focused on observable package behavior through public APIs, service provider wiring, commands, routes, published resources, and documentation promises. Test paths mirror `src/` (`src/Http/Foo.php` -> `tests/Http/FooTest.php`).
- The skeleton placeholders (`config('consent.placeholder')`, `consent:placeholder`, the placeholder view, translation and migration) exist only until the first slice that ships a real one of each: that slice replaces them.

## Quick Commands

- Full validation: `composer qa`
- Static analysis: `composer stan`
- Refactoring check / apply: `composer rector:dry` / `composer rector`
- Formatting check / apply: `composer pint:dry` / `composer pint`
- Type coverage: `composer coverage`
- Pest tests: `composer test`
- Workbench build: `composer build`
- Workbench server: `composer serve`

## Local Skills

- `package-scaffold`: use when adding package capabilities or wiring them through the service provider, including commands, migrations, routes, config, views, translations, assets, middleware, publish tags, workbench files, and console-only behavior.
- `package-testing`: use when adding or changing package tests with Pest 4/5 and Orchestra Testbench.
- `package-release`: use when preparing changelog, release notes, tags, or GitHub release workflow changes.
- `package-compatibility`: use when reviewing code, dependencies, or CI against the PHP and Laravel support matrix.
- `package-generate-skill`: use when updating the bundled Boost skill from the package implementation, README, and examples.

## Agent skills

### Issue tracker

Issues live in GitHub Issues for SimoneCerruti/laravel-consent, managed with the `gh` CLI. Specs and tickets get the `ready-for-agent` label. See `docs/agents/issue-tracker.md`.

### Domain docs

Single-context: one `GLOSSARY.md` and `docs/adr/` at the repo root, created lazily. See `docs/agents/domain.md`.
