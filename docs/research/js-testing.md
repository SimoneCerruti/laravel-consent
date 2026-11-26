# JS testing in a Laravel package

Research for [#5](https://github.com/SimoneCerruti/laravel-consent/issues/5). Question: how to test the zero-dependency JS core (`consent.js`) and the progressively enhanced Blade banner (IMPLEMENTATION.md §3.10–3.12) inside this package repo. Compares Pest browser testing (Playwright) with Vitest + jsdom/happy-dom on the Testbench workbench. Facts and trade-offs only; the choice is left to a later grilling ticket.

Researched 2026-11-26. Versions current then: pest-plugin-browser v5.2.0 (5.x) / 4.x branch, Vitest 5.1.2, jsdom 31.0.1, happy-dom 21.0.4, Playwright 1.65.1. Local vendor: Pest 5.3.7, Testbench 11.3.0, Laravel 13.44.1.

## Answer

The two options prove different things, so they are not substitutes:

- **Pest browser plugin** drives a real Chromium against the package's Laravel app, served in-process from Testbench. It is the only option here that proves the end-to-end behaviour: Blade output, the published `consent.js`, CSRF, the cookie, and real focus.
- **Vitest + jsdom/happy-dom** unit-tests `consent.js` against a fixture DOM in Node, with `fetch` stubbed. It is cheap, fast and PHP-independent. It cannot prove navigation (the no-JS post), layout, or real focus movement.
- **The no-JS form post needs neither.** It is a plain HTTP flow (POST, cookie, redirect back, banner hidden on the next request), which the existing Pest feature tests already cover (IMPLEMENTATION.md §6).

| | Pest browser plugin (Playwright) | Vitest + jsdom / happy-dom |
| --- | --- | --- |
| What runs | Real Chromium/Firefox/WebKit via Playwright, against the Testbench app served in-process [1][2] | `consent.js` in Node with a simulated DOM [8] |
| New dev deps | `pestphp/pest-plugin-browser` (`^4` for Pest 4, `^5` for Pest 5), npm `playwright`, the browser binaries, `ext-sockets` [1][3][4] | npm `vitest` plus `jsdom` or `happy-dom` (optional peer deps) [9] |
| Needs `package.json` and `node_modules` | Yes. The plugin starts `./node_modules/.bin/playwright run-server` from the directory that holds `package.json` [5] | Yes |
| Runtime | Node 22/24/26 for Playwright [12]; PHP ^8.4 (5.x) or ^8.3 (4.x) [3][4] | Node `^22.12 \|\| ^24 \|\| >=26` (Vitest 5), `^22.22.2 \|\| ^24.15 \|\| >=26` (jsdom 31) [9] |
| PHP/Laravel matrix fit | Two majors: 4.x needs Pest 4 (our L12 lanes), 5.x needs Pest ≥5 and `symfony/process ^8.1`, which Laravel 12 (`^7.2`) cannot install [3][4][6] | None. PHP-independent, so one Node job covers it |
| CI cost | Node + `npm ci` + browser download (cacheable) per lane that runs it. Upstream and the packages surveyed run it on Ubuntu only [7][14][15] | One Ubuntu job, `npm ci` + `vitest run`, as Livewire and Filament do with their JS jobs [14][15] |
| Windows lanes | Not exercised upstream (plugin CI is `ubuntu-latest` only [7]). Open bug: on Windows the run can hang before any browser starts, about 1 run in 4 on the reporter's machine [16] | Not needed. Nothing in it depends on the OS |
| No-JS form post | Possible: `visit()` forwards its options to Playwright `newContext` [17], and Playwright has `javaScriptEnabled: false` [13]. Source-derived, not documented by Pest | Cannot: jsdom does not implement navigation [10] |
| Fetch enhancement | Proves it for real: submit intercepted, `fetch` to the package route with `X-CSRF-TOKEN`, JSON response, no reload, cookie set | Proves the JS logic only: `fetch` stubbed, request shape asserted. Not CSRF or the server's answer |
| Script injection | Proves real loading/execution of a `src` script and `assertScript()` on globals [1] | Proves `<script>` elements get built with the right attributes. Execution of external scripts in jsdom needs `runScripts: "dangerously"` + `resources: "usable"` [10] |
| Focus trap | Real Tab-key focus movement in a laid-out page; `keys()` / `press()` [1] | Weak: no layout in jsdom [10], so visibility-based tabbable checks misbehave there [11], and user-event "simulates events instead of actually triggering them" [8] |
| Debugging | Screenshots, traces, `assertNoJavaScriptErrors()` / `assertNoSmoke()` [1] | Fast watch mode, plain stack traces |
| Maturity signals | Filament patches the plugin in CI for port allocation and action timeouts [15]; open hang/orphan-process bugs [16][18] | jsdom documents its gaps; tabbable calls jsdom "not officially supported" [10][11] |

The packages surveyed split JS tests from browser tests:
- **Livewire** runs Vitest + jsdom on `js/*.spec.js` in one Ubuntu job, and runs browser tests with Dusk through `orchestra/testbench-dusk`, Ubuntu-only and sharded, re-running failures [14].
- **Filament** runs `node --test` on `tests/js/*.test.js` in one Ubuntu job, and Pest browser tests (`pestphp/pest-plugin-browser ^4.1`) inside its PHP matrix, Ubuntu-only [15].
- **spatie/laravel-cookie-consent**, the closest functional peer, has no JS tests. Its banner JS is inline in a Blade view and only PHP tests run [19].

## Details

### 1. Pest browser plugin

**How it serves the package.** `visit()` boots an amphp HTTP server inside the PHP test process, which passes requests to the Laravel HTTP kernel. Requests for a file that exists under `public_path()` are served directly [2]. Because it runs in-process, a test's `RefreshDatabase`, config overrides and fakes apply to what the browser sees. The plugin's own suite is a Testbench package: `TestCase extends Orchestra\Testbench\TestCase` with `RefreshDatabase, WithWorkbench`, and a `testbench.yaml` almost identical to ours [20].

**Serving `consent.js` under Testbench.** `public_path()` is the Testbench skeleton (`vendor/orchestra/testbench-core/laravel/public`). The workbench `asset-publish` build step runs `vendor:publish --force` for `laravel-assets` plus every tag listed under `workbench.assets` in `testbench.yaml` [21]. Two ways to get the file in place:
- give the package's publish tag (e.g. `consent-assets` → `public/vendor/consent`) to `workbench.assets` and run `composer build` before the browser suite;
- or publish it in test setup.

**Install / setup cost** [1]:
- `composer require pestphp/pest-plugin-browser --dev`, `npm install playwright@latest`, `npx playwright install`.
- Gitignore `tests/Browser/Screenshots` and `tests/Browser/Traces`.

**Version fit with our matrix** (`.github/workflows/tests.yml`: L12 + Testbench 10 + Pest 4, L13 + Testbench 11 + Pest 5):

| Plugin | PHP | Pest | symfony/process | Source |
| --- | --- | --- | --- | --- |
| 4.0.0 | ^8.3 | ^4.0 | ^7.3 | [4] |
| 4.3.1 | ^8.3 | ^4.4.5 | ^7.4.8 \| ^8.0.5 | [4] |
| 5.0.0 | ^8.4 | ^5.0 (conflicts with <5) | ^8.1.0 | [3] |
| 5.2.0 | ^8.4 | ^5.2.1 | ^8.1.7 | [3] |

Laravel 12 requires `symfony/process ^7.2`, and Laravel 13 allows `^7.4.5 || ^8.0.5` [6]. A `^4.x || ^5.x` constraint would resolve per lane: 4.x on L12/Pest 4, 5.x on L13/Pest 5. The plugin also requires `ext-sockets` [3][4], which the `extensions:` list in our `tests.yml` does not name. Whether the runners' PHP builds already include it was not verified. The plugin's CI tests PHP 8.4 and 8.5 only, with a `ci/php-8.6` branch open [7]. PHP 8.6 support is not shown.

**API relevant to the banner** [1]:
- `visit('/')`, `press('Accept all')`, `keys(selector, ['{Tab}'])` / `withKeyDown`, `assertScript('window.Consent.allows("analytics")', true)`, `script(...)`;
- `assertNoJavaScriptErrors()` / `assertNoConsoleLogs()` / `assertNoSmoke()`;
- `assertAttribute(...)` for `aria-modal` [17];
- device/dark-mode/locale variants and `offline()` / `online()` [17].

The docs list no dedicated focus assertion. `document.activeElement` can be read with `assertScript`.

**No-JS mode.** Pest's docs do not cover disabling JS. In the source, `visit(string $url, array $options = [])` spreads `$options` into `Browser::newContext([...])` [17]. Playwright's `newContext` accepts `javaScriptEnabled` ("Whether or not to enable JavaScript in the context. Defaults to `true`.") [13]. The same `$options` also reach `goto()` [17]. Whether Playwright accepts the extra key there was not tested. The no-JS flow is already provable without a browser with Pest HTTP tests, so this matters only to run the same scenario in a real browser with JS off.

**CI recipe** [1]:
- `actions/setup-node`, `npm ci`, `npx playwright install --with-deps`;
- `./vendor/bin/pest --ci --parallel --trace`;
- upload screenshots and traces on failure.

Filament adds two things [15]:
- caches `~/.cache/ms-playwright` by `package-lock.json` hash, and installs only Chromium with retries;
- `git apply`s two patches to `vendor/pestphp/pest-plugin-browser`, one for HTTP server port allocation (pending upstream PR #226) and one for Playwright action timeouts (an upstream commit not yet in a 4.x release).

**Effects on our workflow:**
- The plugin throws `PlaywrightNotInstalledException` when the Playwright binary is missing [5]. Lanes that do not install Node/Playwright must therefore exclude browser tests, for example with a separate testsuite or group. Today's `composer test` runs all of `tests/` in parallel.
- Windows lanes would need the browser suite skipped, or would run into the open hang [16], which Pest has not confirmed fixed. The plugin does handle Windows paths (`DIRECTORY_SEPARATOR` in the server command, `PHP_OS` checks) [5], and Playwright supports Windows 11+ / Server 2019+ [12].
- Other open upstream bugs: `playwright run-server` is orphaned after each run on Debian/Ubuntu when output is piped, which hangs the terminal [18]; suites can hang with the browser never navigating [16].

### 2. Vitest + jsdom / happy-dom

**Setup cost:**
- `package.json` with `vitest` and `jsdom` (or `happy-dom`); both are optional peer deps of Vitest [9].
- `environment: 'jsdom'` in `vitest.config`, or per file `// @vitest-environment jsdom` [8].
- Livewire's whole config is six lines (`environment: 'jsdom'`, `globals: true`, an alias) [14].
- `consent.js` has zero dependencies and IMPLEMENTATION.md §3.10 already plans it "importable as an ES module". Tests import it directly. Fixture HTML can be a static copy of the banner markup, or the rendered Blade output saved by a PHP test as a snapshot. The snapshot route keeps the fixture from drifting; it is a design choice, not something a surveyed package does.

**Environments.** Vitest describes happy-dom as "considered to be faster than jsdom, but lacks some API" [8]. jsdom puts two features out of scope [10]:
- **navigation**: assigning `location.href` or following a submit emits a "not implemented" error and nothing changes;
- **layout**: `getBoundingClientRect()`/`offsetTop` return zeros.

External `<script src>` only executes with `runScripts: "dangerously"` and `resources: "usable"` [10].

**What it proves for this package:**
- the state machine (`accept/reject/save/withdraw`, `allows`, `subscribe`, the `consent:updated` event, the `consent:open` listener);
- form-submit interception calling `fetch` with the right URL, body and `X-CSRF-TOKEN` (via `vi.fn`/`vi.stubGlobal`);
- error handling when the response is not ok;
- which `<script>` elements are created for granted categories, with which attributes;
- `client_state` mode fetching `GET /consent`.

It does not prove the server's answer, the real cookie, that the published file is the one tested, or a focus trap. For the focus trap, tabbable says it is "highly recommended (if not essential)" to use `displayCheck: 'none'` under jsdom because of missing layout APIs [11], and simulated Tab presses are not native focus navigation [8].

**CI cost.** One Ubuntu job (`setup-node`, `npm ci`, `vitest run`), independent of the 20-lane PHP matrix, as Livewire's `js-tests` and Filament's `test-js` jobs do [14][15]. No Windows lane is needed because nothing in it is OS-specific.

### 3. Other options seen

- **`node:test`** (Filament): zero npm dev dependencies, pure-logic tests with hand-made stubs [15]. Fits a dependency-free core but has no DOM, so DOM code needs stubs or a DOM library.
- **Vitest Browser Mode**: runs Vitest tests in a real browser via `@vitest/browser-playwright` (recommended) or WebdriverIO. The `preview` provider "can't run tests in CI" [22]. Gives real focus and layout for JS-only tests without a Laravel server. Costs Playwright browsers again.
- **Dusk via `orchestra/testbench-dusk`** (Livewire): ChromeDriver-based PHPUnit browser tests. Livewire runs them sharded with a retry-on-failure fallback [14]. It is a second browser stack beside Pest's own.

### 4. Matrix of claims × approach

| Claim | Pest HTTP feature test | Vitest + jsdom | Pest browser |
| --- | --- | --- | --- |
| No-JS POST → cookie → redirect back → banner hidden | Yes | No | Yes (JS off via context option, unverified) |
| JS intercepts submit, uses `fetch`, no reload | No | Logic only (stubbed fetch) | Yes, end to end |
| Granted category scripts injected, denied not | Payload/registry only | Element creation only | Yes, incl. execution |
| Focus trap, `aria-modal`, Escape only after a choice | Markup only | Unreliable (no layout, simulated keys) | Yes |
| Published asset is the file under test | No | No | Yes |
| Runs on Windows lanes | Yes (today) | Not needed | Not shown upstream; open hang bug |

## Sources

1. Pest docs, Browser Testing: https://pestphp.com/docs/browser-testing
2. `LaravelHttpServer` (amphp server, `public_path()` asset serving, HTTP kernel handoff): https://github.com/pestphp/pest-plugin-browser/blob/v5.2.0/src/Drivers/LaravelHttpServer.php
3. pest-plugin-browser `composer.json` v5.0.0 / v5.2.0: https://github.com/pestphp/pest-plugin-browser/blob/v5.2.0/composer.json , https://github.com/pestphp/pest-plugin-browser/blob/v5.0.0/composer.json
4. pest-plugin-browser `composer.json` v4.0.0 / v4.3.1: https://github.com/pestphp/pest-plugin-browser/blob/v4.0.0/composer.json , https://github.com/pestphp/pest-plugin-browser/blob/v4.3.1/composer.json
5. `ServerManager` / `PlaywrightNpmServer` / `Support/Shell` (Playwright server command, `PackageJsonDirectory::find()`, `PlaywrightNotInstalledException`, Windows checks): https://github.com/pestphp/pest-plugin-browser/blob/v5.2.0/src/ServerManager.php , https://github.com/pestphp/pest-plugin-browser/blob/v5.2.0/src/Playwright/Servers/PlaywrightNpmServer.php
6. laravel/framework `composer.json` (`symfony/process`): https://github.com/laravel/framework/blob/12.x/composer.json , https://github.com/laravel/framework/blob/13.x/composer.json
7. pest-plugin-browser CI (`os: [ubuntu-latest]`, PHP 8.4/8.5, Chromium only): https://github.com/pestphp/pest-plugin-browser/blob/5.x/.github/workflows/tests.yml
8. Vitest, Test Environment: https://vitest.dev/guide/environment.html ; Vitest Browser Mode (on user-event simulating events): https://vitest.dev/guide/browser/
9. npm registry metadata (engines, peer deps): https://registry.npmjs.org/vitest/latest , https://registry.npmjs.org/jsdom/latest , https://registry.npmjs.org/happy-dom/latest , https://registry.npmjs.org/playwright/latest
10. jsdom README ("Unimplemented parts of the web platform", `runScripts`, `resources`): https://github.com/jsdom/jsdom/blob/main/README.md
11. tabbable README, "Testing in JSDom": https://github.com/focus-trap/tabbable#testing-in-jsdom
12. Playwright, system requirements: https://playwright.dev/docs/intro
13. Playwright, `browser.newContext` `javaScriptEnabled`: https://playwright.dev/docs/api/class-browser#browser-new-context
14. Livewire: `vitest.config.js`, `package.json` (vitest, jsdom), `composer.json` (testbench-dusk), CI: https://github.com/livewire/livewire/blob/4.x/vitest.config.js , https://github.com/livewire/livewire/blob/4.x/package.json , https://github.com/livewire/livewire/blob/4.x/composer.json , https://github.com/livewire/livewire/blob/4.x/.github/workflows/test-4.x.yml
15. Filament: `composer.json` (pest-plugin-browser ^4.1), `package.json` (`node --test`), CI with plugin patches and Playwright cache, example browser test: https://github.com/filamentphp/filament/blob/4.x/composer.json , https://github.com/filamentphp/filament/blob/4.x/package.json , https://github.com/filamentphp/filament/blob/4.x/.github/workflows/tests.yml , https://github.com/filamentphp/filament/blob/4.x/tests/src/Forms/AutofocusTest.php
16. pestphp/pest#1941 (Windows hang before browser starts), #1855 (suite hangs, browser never navigating): https://github.com/pestphp/pest/issues/1941 , https://github.com/pestphp/pest/issues/1855
17. `Browsable::visit()` and `PendingAwaitablePage` (options into `newContext`), `MakesElementAssertions`, `InteractsWithNetwork`: https://github.com/pestphp/pest-plugin-browser/blob/v5.2.0/src/Browsable.php , https://github.com/pestphp/pest-plugin-browser/blob/v5.2.0/src/Api/PendingAwaitablePage.php
18. pestphp/pest#1754 (orphaned `playwright run-server` on Debian/Ubuntu): https://github.com/pestphp/pest/issues/1754
19. spatie/laravel-cookie-consent (Ubuntu-only Pest, no JS tests): https://github.com/spatie/laravel-cookie-consent/tree/main/tests , https://github.com/spatie/laravel-cookie-consent/blob/main/.github/workflows/run-tests.yml
20. pest-plugin-browser's own Testbench setup: https://github.com/pestphp/pest-plugin-browser/blob/5.x/tests/TestCase.php , https://github.com/pestphp/pest-plugin-browser/blob/5.x/testbench.yaml
21. orchestra/workbench `AssetPublishCommand` recipe (v11.3.0, read from local `vendor/`): https://github.com/orchestral/workbench/blob/v11.3.0/src/Recipes/AssetPublishCommand.php
22. Vitest Browser Mode providers: https://vitest.dev/guide/browser/
