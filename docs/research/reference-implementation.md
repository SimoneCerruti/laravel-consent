# Research: reference implementation behaviour

Ticket: [#3 Reference implementation behaviour](https://github.com/SimoneCerruti/laravel-consent/issues/3).
Source examined: `~/projects/simonecerruti.com` at commit `99b8c4bd8e663a0b046541a72594450eb2dd7c47` (2026-11-16),
with its locked dependencies: `laravel/framework` v13.38.0, `livewire/livewire` v4.4.8, `bepsvpt/secure-headers` 9.1.1 (`composer.lock`).
Paths below are relative to that repo unless prefixed with `vendor/` (also inside that repo) or `IMPLEMENTATION.md` (this repo).

## Answer

- **`cookie_consent` is encrypted.** The app never excludes it from `EncryptCookies`: there is no `encryptCookies(except: ...)`
  or `EncryptCookies::except()` call anywhere in `app/`, `bootstrap/`, `config/` or `routes/`, and `bootstrap/app.php:23-37` only
  appends middleware to the default `web` group. That default group starts with `EncryptCookies` then `AddQueuedCookiesToResponse`
  (`vendor/laravel/framework/src/Illuminate/Foundation/Configuration/Middleware.php:485-487`). So the browser holds an encrypted,
  HMAC-prefixed value; the app only ever sees plaintext JSON, and a tampered or foreign value is decrypted to `null` and treated as
  "no choice" (`vendor/.../Cookie/Middleware/EncryptCookies.php:83-97, 111-116, 175-192`; `vendor/.../Cookie/CookieValuePrefix.php:14-16, 38-44`).
  Neither the browser JS nor any non-Laravel reader can read it, which is fine because it is also HttpOnly.
- **The JS loader is a separate Vite entry, bootstrapped by an inline script with hand-built globals.** `resources/js/cookie-consent.js`
  is an IIFE listed as its own Vite input (`vite.config.js:12`, built to `public/build/assets/cookie-consent-*.js`) and included with
  `@vite('resources/js/cookie-consent.js')` at the end of `<body>` (`resources/views/layouts/public/index.blade.php:115`).
  State comes from an inline `<script>` in `<head>` that writes `window.__cookieConsent` key by key from the shared `$cookieConsent`
  array, plus `window.__appEnv` and `window._clarityId` (`index.blade.php:30-41`). The JS boots on `DOMContentLoaded` and on
  `livewire:navigated`, and listens on `document` for the Livewire browser event `consent-updated` (`cookie-consent.js:120-148`).
  The only script it actually injects is Microsoft Clarity, and only when `analytics` is granted **and** `__appEnv === 'production'`
  (`cookie-consent.js:55-73, 91-93`).
- **There is no CSP and no nonce handling.** The `secure-headers` CSP is disabled with the comment "Disabled until a policy covering
  Livewire, Filament, reCAPTCHA and inline scripts is written and tested" (`config/secure-headers.php:619-621`). The only policy is a
  `<meta http-equiv="Content-Security-Policy" content="upgrade-insecure-requests">` (`index.blade.php:16`). No `nonce` appears in
  `app/` or `resources/`, and `Vite::useCspNonce()` is not called (`app/Providers/AppServiceProvider.php:71` only calls
  `Vite::useAggressivePrefetching()`). The consent flow depends on several things a strict CSP would block (see "CSP and nonces").
- **Tests pin down cookie parsing and the Livewire state machine, nothing else.** 6 middleware tests (no cookie, valid cookie,
  missing `version`, malformed JSON; guest and authenticated) and 9 Livewire tests (banner shown, actions set state + dispatch
  `consent-updated`, cookie queued, withdraw clears it, render markers). Not covered: cookie attributes (lifetime, HttpOnly,
  SameSite, Secure), the JSON written, the banner hidden when a valid cookie exists, the inline bootstrap, the JS, and the event
  wiring of `open-cookie-banner` (the test calls the method directly).
- **IMPLEMENTATION.md §1/§2 has several errors and omissions.** Most important: `withdrawConsent()` is unreachable from the UI
  (no view calls it); the shared array is not used by Blade to skip scripts server-side (its only server consumer is `TrackVisit`);
  reCAPTCHA is not loaded by the consent JS at all (an inline layout loader does it, page-gated); Clarity is also gated on the
  environment; the shared array has a `given` key the plan omits; test paths and the banner's line count are wrong; the banner does not
  give reject the same weight as accept and is not a modal dialog. Full list under "Corrections to IMPLEMENTATION.md".

## The cookie

Written by `CookieConsent::saveAndClose()` (`app/Livewire/Components/CookieConsent.php:139-172`):

- Name `cookie_consent`, lifetime `60 * 24 * 180` minutes = 180 days (`CookieConsent.php:18-20`).
- Value: JSON `{version: 1, timestamp: ISO-8601, necessary: true, analytics, functional, marketing}`; `version` is the literal `1`
  (`CookieConsent.php:140-147`).
- `Cookie::queue(name, value, minutes, '/', null, config('session.secure', false), true /* httpOnly */, false /* raw */, 'Lax')`
  (`CookieConsent.php:150-160`).
- Queued from a Livewire **update** request. That works because Livewire registers its update route with the `web` group
  (`vendor/livewire/livewire/src/Mechanisms/HandleRequests/HandleRequests.php:29-30`), so `AddQueuedCookiesToResponse` and
  `EncryptCookies` wrap the XHR response.
- Withdraw queues `Cookie::forget('cookie_consent')` (`CookieConsent.php:123`).

Read in two independent places, with duplicated logic:

- `CookieConsentMiddleware` (`app/Http/Middleware/CookieConsentMiddleware.php:17-53`), appended to `web` after `SecureHeadersMiddleware`
  and before `TrackVisit` (`bootstrap/app.php:32-36`). It runs inside `EncryptCookies`, so it sees the decrypted value.
- `CookieConsent::mount()` (`CookieConsent.php:49-72`) re-reads `request()->cookie()` itself to decide `$show`.

Validity rule in both: `json_decode` succeeds, result is an array, and `isset($prefs['version'])` (`CookieConsentMiddleware.php:28-35`,
`CookieConsent.php:52-60`). The value of `version` is never compared, `timestamp` and `necessary` are ignored on read, and a missing
category key is read as `false`.

Shared view variable `cookieConsent` (`CookieConsentMiddleware.php:18-24, 36-47`): `{given, necessary: true, analytics, functional, marketing}`.
Consumers:

- the inline bootstrap in `index.blade.php:30-41`;
- `TrackVisit::hasAnalyticsConsent()` (`app/Http/Middleware/TrackVisit.php:144-148`), which gates IP, user agent and `visitor_id`
  collection and the visitor cookie (tested in `tests/Http/Middleware/TrackVisitTest.php:113-144, 231-239`). This works only because
  `CookieConsentMiddleware` runs before `TrackVisit` (`bootstrap/app.php:34-35`; `docs/analytics/flow.md:34`).

No Blade template uses `$cookieConsent` to omit a third-party `<script>` tag (grep of `resources/views` finds it only in `index.blade.php:32-36`).

## The JS loader and bootstrap

Build: plain IIFE, own Vite entry (`vite.config.js:12`); manifest entry `resources/js/cookie-consent.js` → `assets/cookie-consent-*.js`
(`public/build/manifest.json`). Loaded as a module via `@vite` at the end of `<body>` (`index.blade.php:115`), after `@filamentScripts`
(which carries Livewire/Alpine) and before the Livewire component tag (`index.blade.php:77-118`).

Bootstrap (`index.blade.php:30-41`): a non-nonced inline script that builds `window.__cookieConsent` with one Blade ternary per key
(not `@json`), and sets `window.__appEnv = '{{ app()->environment() }}'` and `window._clarityId = '{{ config('services.clarity.id') }}'`.

Runtime (`resources/js/cookie-consent.js`):

- State defaults to all-false when `window.__cookieConsent` is missing (`:30-36`).
- `boot(consent)` (`:89-112`): loads Clarity if `analytics && __appEnv === 'production'`; sets/removes `data-functional-consent` and
  `data-marketing-consent` on `<html>`; calls `loadRecaptcha()`, which only sets a flag and logs (`:79-85`).
- Clarity loader (`:55-73`): once per page (`_loaded.clarity`), logs an error and gives up if `_clarityId` is empty, else runs the
  vendor snippet that inserts `https://www.clarity.ms/tag/<id>` before the first `<script>`.
- `consent-updated` listener on `document` (`:120-136`): takes `e.detail[0]` (falls back to `e.detail`), merges into state, re-runs
  `boot`, and calls `window.location.reload()` if analytics is now false but Clarity was loaded. It does not update `given`.
- Boots on `DOMContentLoaded` (registering the listener then) and again on `livewire:navigated` (`:140-148`).
- Logs only when `__appEnv === 'local'` (`:46-50`).

Why `e.detail[0]`: `$this->dispatch('consent-updated', [...])` passes the array as a variadic positional param
(`vendor/livewire/livewire/src/Features/SupportEvents/HandlesEvents.php:15-21`), and the Livewire client dispatches a bubbling
`CustomEvent` on the component's root element with `detail = params` (`vendor/livewire/livewire/dist/livewire.esm.js:14312-14314, 14353-14360`),
so `document` receives it with `detail = [ {...} ]`.

Reopening: the footer and the cookie policy page call `Livewire.dispatch('open-cookie-banner')` from inline `onclick` attributes
(`resources/views/layouts/public/footer.blade.php:30`, `resources/views/pages/legal/cookie-policy.blade.php:222`); the component listens with
`#[On('open-cookie-banner')]` (`CookieConsent.php:108-112`), which shows the banner in summary mode.

reCAPTCHA is outside the consent system: an inline `<script data-navigate-once>` in the layout removes any previous reCAPTCHA script,
badge and iframe, then injects `api.js` only if the page has `[data-recaptcha-required]` (`index.blade.php:78-114`), which only the
contact form sets (`resources/views/pages/contacts/components/contact-form.blade.php:1`).

## CSP and nonces

- `SecureHeadersMiddleware` is in the `web` group (`bootstrap/app.php:33`) but `csp.enable` is `false` (`config/secure-headers.php:619-621`).
  The package supports nonces (`vendor/bepsvpt/secure-headers/src/SecureHeaders.php:156-158, 306-312`) but nothing calls it.
- The only CSP is `upgrade-insecure-requests` in a meta tag (`index.blade.php:16`).
- Things a nonce-based CSP would break today: the inline bootstrap (`index.blade.php:30-41`) and reCAPTCHA loader (`:78-114`) have no
  nonce; `onclick="Livewire.dispatch(...)"` attributes (`footer.blade.php:30`, `cookie-policy.blade.php:222`) need `unsafe-hashes` or
  removal; the banner and toggle use Alpine expressions (`x-init`, `x-bind`, `x-on`) (`cookie-consent.blade.php:5-6`,
  `cookie-toggle.blade.php:8-10`), which the standard Alpine build evaluates with `new Function` (needs `unsafe-eval`); the dynamically
  inserted Clarity tag carries no nonce, so it would need `'strict-dynamic'` or a nonce copied by the loader.

Implication for the package (not a reference behaviour): `<x-consent::bootstrap />` should accept a nonce (or read `Vite::cspNonce()`),
emit the payload as `<script type="application/json">` rather than executable JS, and the JS core should copy its own nonce onto every
script it injects; the default banner should use no inline handlers.

## What the tests pin down

`tests/Http/Middleware/CookieConsentMiddlewareTest.php` (registers a `web` test route that returns `view()->shared('cookieConsent')`, `:12-14`):

- no cookie → exact `{given:false, necessary:true, analytics:false, functional:false, marketing:false}` (`:17-27`);
- valid cookie → exact parsed shape with `given:true` (`:29-46`);
- cookie without `version` → `given:false` (`:48-54`);
- malformed JSON → `given:false`, no exception (`:56-62`);
- same results for an authenticated user (`:65-82`).

`withCookie()` encrypts by default (`vendor/.../Foundation/Testing/Concerns/MakesHttpRequests.php:58, 730-737`), so these tests also pass
through real decryption; the helper `getAnalyticsConsentCookieValue()` is `{version:1, analytics:true}` (`tests/Pest.php:357-359`).

`tests/Livewire/Components/CookieConsentTest.php`:

- banner shown with no cookie (`:11-14`);
- after `acceptAll`, `openBanner()` shows the banner with preferences closed (`:16-23`); the method is called directly, the event is not dispatched;
- summary renders `cookie-banner is-visible` and the three `wire:click` actions, no preferences panel (`:25-32`);
- preferences panel renders the `is-open` class, three toggles, "Strictly necessary", "coming soon", `savePreferences` (`:34-45`);
- `acceptAll`, `rejectAll`, `savePreferences` set the category booleans, hide the banner and dispatch `consent-updated` (`:48-83`);
- the cookie is queued on save (`:85-91`);
- `withdrawConsent` shows the banner, clears analytics, dispatches `consent-updated` and queues a cleared cookie (`:94-109`).

Not pinned down anywhere: cookie lifetime, HttpOnly, SameSite, Secure, the JSON payload written, the `consent-updated` payload,
banner hidden when a valid cookie is present (only the middleware side is tested), the layout bootstrap, and any JS behaviour
(no JS test runner in `package.json`).

## Corrections to IMPLEMENTATION.md

§1 (source table):

- Test paths are `tests/Http/Middleware/CookieConsentMiddlewareTest.php` and `tests/Livewire/Components/CookieConsentTest.php`, not
  under `tests/Feature/` (`IMPLEMENTATION.md:22`).
- `cookie-consent.blade.php` has 89 lines, not 162 (`IMPLEMENTATION.md:17`).
- The inline bootstrap also sets `window.__appEnv`, which gates Clarity and logging (`index.blade.php:39`; `cookie-consent.js:47, 91`)
  (`IMPLEMENTATION.md:21`).

§2 (how the reference works):

- Cookie line (`IMPLEMENTATION.md:27`): correct as far as it goes; add that the value is **encrypted by `EncryptCookies`** (not excluded), `version`
  is hard-coded `1`, and the path is `/`. The package must decide explicitly; see "Open points".
- "A cookie with no valid `version`" (`:28`): there is no validity check on the value, only `isset`. Any `version` (0, `"x"`) counts.
- "shares an array so Blade can skip third-party scripts server-side" (`:29`): no Blade view does that. The array feeds the inline JS
  bootstrap and `TrackVisit`'s server-side analytics gating. The array also carries `given`, which the plan never mentions.
  The parsing is duplicated in the Livewire `mount()`.
- Withdraw (`:30`): the behaviour is as described, but **no view calls `withdrawConsent()`**; the code comment "called from Privacy /
  Cookie Policy page" (`CookieConsent.php:114`) is stale. Users can only change preferences via `open-cookie-banner`, and "Reject all"
  then writes a new cookie rather than forgetting it.
- `open-cookie-banner` (`:30`): it is a Livewire event fired via `Livewire.dispatch` from inline `onclick`, so it needs Livewire on the
  page. It opens the summary view, not the preferences panel.
- `consent-updated` (`:31`): correct; add that the JS reloads the page automatically when analytics is revoked after Clarity loaded
  (`cookie-consent.js:129-134`), and that it re-boots on `livewire:navigated`.
- Legitimate interest (`:32`): reCAPTCHA is **not** loaded by the consent JS; `loadRecaptcha()` there is a stub. The real loader is an
  inline layout script that loads it only on pages marked `data-recaptcha-required` and tears it down on navigation.
- Missing: Clarity loads only when `__appEnv === 'production'` and a Clarity id is configured.
- Missing: `functional`/`marketing` consent is exposed to CSS/JS as `data-functional-consent`/`data-marketing-consent` on `<html>`;
  nothing is loaded for them.
- Missing: the CSP situation (disabled; no nonces), see above.

§3.12 (banner UX "to keep from the reference"):

- Reject is **not** equal weight today: accept is a filled accent button pushed right and, on narrow screens, full-width and first
  (`resources/css/cookie.css:16, 30`), while reject is an outlined button (`cookie.css:14`; `resources/css/pages/cookie-consent.css:3`).
  This is a requirement to introduce, not to keep.
- The banner is `role="dialog" aria-modal="false"` with `aria-live="polite"` (`cookie-consent.blade.php:3-4`): no focus trap, no
  escape handling. Also new work.
- No pre-ticked toggles: true, all three properties default to `false` (`CookieConsent.php:35-39`).

## Open points for the package

- **Encrypt or not.** Keeping encryption matches the reference and makes the cookie tamper-evident, but ties it to `APP_KEY` (a key
  rotation without `APP_PREVIOUS_KEYS` silently re-prompts everyone; `EncryptCookies` validates against `getAllKeys()`,
  `EncryptCookies.php:115`) and prevents edge/CDN or cached-page logic from reading it. Excluding it (`EncryptCookies::except`) makes
  it plain JSON readable by the server only (still HttpOnly). Either way the package must test the path through `EncryptCookies`,
  since its middleware runs inside the `web` group.
- **Middleware order** matters for consumers like `TrackVisit`; the package's `ShareConsent` must run before them, or consumers should
  call `ConsentManager` (which can parse lazily) instead of reading a shared view variable.
