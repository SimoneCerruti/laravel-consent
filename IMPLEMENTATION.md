# laravel-consent: how to build it

Package `simonecerruti/laravel-consent` (name chosen: short, covers cookie banner, consent records and GDPR tooling).
Consent categories, a cookie banner, consent-gated script loading, proof of consent and GDPR data-subject tooling.
The package is **frontend-agnostic**: a headless core (PHP + HTTP endpoints + a dependency-free JS core) with a default
Blade banner that works in any Laravel app, plus thin optional adapters for Livewire, React and Vue. The reference uses
Livewire; here Livewire is only one adapter, never a requirement. Filament UI for editing it is `filament-consent`; custom legal pages
(shipping policy, returns policy, ...) are `laravel-legal-pages`.

Reference: `~/projects/simonecerruti.com`. The banner exists; the proof-of-consent log, versioning rules and GDPR tooling are **NEW** design.

## 1. Source files to extract

| Reference | Goes to | Changes |
| --- | --- | --- |
| `app/Livewire/Components/CookieConsent.php` | actions → `src/Http/Controllers/ConsentController.php`; optional adapter `src/Livewire/ConsentBanner.php` | categories from config instead of hard-coded `analytics/functional/marketing`; all writes go through `ConsentManager`, the controller is the canonical entry point |
| `resources/views/layouts/public/cookie-consent.blade.php` (89 lines) | `resources/views/components/banner.blade.php` (`<x-consent::banner />`) | plain Blade + `<form method="POST">`, no `wire:`/Alpine; texts via translations, colours via CSS variables/Tailwind tokens, no app-specific copy |
| `resources/views/layouts/public/cookie-toggle.blade.php` | `resources/views/components/toggle.blade.php` | `<x-consent::toggle>` |
| `app/Http/Middleware/CookieConsentMiddleware.php` | `src/Http/Middleware/ShareConsent.php` | parse through `ConsentManager`; share `consent` view variable |
| `resources/js/cookie-consent.js` | `resources/js/consent.js` (JS core, built to `public/vendor/consent/consent.js`) | framework-agnostic API + script registry from the payload, not hard-coded Clarity/reCAPTCHA |
| inline bootstrap in `layouts/public/index.blade.php` (`window.__cookieConsent`, `window.__appEnv`, `window._clarityId`) | `<x-consent::bootstrap />` Blade component | serialises `Consent::payload()` and includes the JS core |
| `tests/Http/Middleware/CookieConsentMiddlewareTest.php`, `tests/Livewire/Components/CookieConsentTest.php` | `tests/` | port |
| `app/Livewire/Pages/{CookiePolicy,PrivacyPolicy}.php` | NOT here | legal page rendering belongs to `laravel-legal-pages` |

## 2. How the reference works

Verified against the reference code: see the research note on branch `research/reference-implementation` (`docs/research/reference-implementation.md`).

- Cookie `cookie_consent`: JSON `{version, timestamp, necessary:true, analytics, functional, marketing}` (`version` hard-coded to `1`), 180 days, path `/`, HttpOnly, `Lax`, secure from `session.secure`, queued with `Cookie::queue` from Livewire. The value is **encrypted by `EncryptCookies`** (not excluded), so a tampered value reads as no choice.
- A cookie without a `version` key is treated as "no choice made" and the banner shows; the value itself is not validated (`isset` only).
- The server parses it every request (middleware, parsing duplicated in the Livewire `mount()`) and shares `{given, necessary, analytics, functional, marketing}`. No Blade view uses it to skip scripts: it feeds the inline JS bootstrap and the server-side analytics gating of `TrackVisit`.
- Actions: accept all, reject all, save granular preferences, open preferences, and `open-cookie-banner` (a Livewire event fired from inline `onclick`, opening the summary view) so any link can reopen it. A **withdraw** action exists (forgets the cookie, dispatches `consent-updated`, shows the banner again) but **no view calls it**: users can only reopen the banner and reject, which writes a new cookie.
- The browser event `consent-updated` makes the JS load newly allowed scripts; it re-boots on `livewire:navigated`. Scripts already loaded cannot be unloaded: the JS reloads the page when analytics is revoked after Clarity loaded.
- The only managed script is Clarity, loaded only when `__appEnv === 'production'` and a Clarity id is set. `functional`/`marketing` are only exposed as `data-functional-consent`/`data-marketing-consent` on `<html>`; nothing loads for them.
- reCAPTCHA is **not** handled by the consent JS (its `loadRecaptcha()` is a stub): an inline layout script loads it only on pages marked `data-recaptcha-required`, regardless of consent.
- No CSP is enforced and nothing uses nonces; inline scripts, `onclick` handlers and Alpine expressions would break under a strict CSP.
- Reject does **not** have equal weight (accept is the filled, first button) and the banner is `role="dialog" aria-modal="false"` with no focus trap or Escape handling: the UX requirements in §3 are new work, not behaviour to keep.

## 3. Package design

```
laravel-consent/
  config/consent.php
  database/migrations/        # consent_logs (+ optional consent_versions)
  resources/{views,js,lang}/
  stubs/{react,vue,livewire}/   # copied into the host app by consent:install
  src/
    ConsentServiceProvider.php
    ConsentManager.php
    Contracts/{ResolvesConsent?,StoresConsent}.php
    Data/{ConsentState,ConsentCategory,ManagedScript}.php
    Enums/ConsentDecision.php        # AcceptedAll, RejectedAll, Custom, Withdrawn
    Events/{ConsentGiven,ConsentWithdrawn}.php
    Http/Controllers/ConsentController.php   # GET/POST /consent
    Http/Middleware/ShareConsent.php
    Livewire/ConsentBanner.php       # optional adapter, registered only if Livewire is installed
    Console/InstallCommand.php       # consent:install --stack=blade|livewire|react|vue
    Models/ConsentLog.php
    Console/PruneConsentLogsCommand.php
  tests/
```

1. **Categories** in config: `necessary` (locked on), then user-defined; each has `key`, translated label/description, `default`, and `locked`. Reference defaults: analytics, functional, marketing.
2. **Script registry**: `ManagedScript(key, category, src|inline, attributes, async)`. The reference hard-codes `loadClarity()`; here scripts are declared (`config('consent.scripts')` or `Consent::script(...)` in a provider) and the bootstrap component passes them to the JS, which injects each one only when its category is granted. Server-side `@consent('analytics') ... @endconsent` Blade directive for markup that should not be rendered at all.
3. **`ConsentManager`**: `state(): ConsentState`, `allows(string $category): bool`, `grant(array $categories, ConsentDecision)`, `withdraw()`. Replaces the array shared by the middleware. Implements `visit-analytics`'s `ResolvesConsent` contract when that package is present (the service provider binds it if the interface exists).
4. **Consent version**: `config('consent.version')` (int). If the stored cookie's version is lower, the banner re-appears: needed when a new category or script is added. The reference only checks that a version exists.
5. **Proof of consent (NEW)**: GDPR requires being able to demonstrate consent. `consent_logs`: `id` (uuid), `subject_id` (random uuid stored in the cookie, **not** an identifier of the person), `decision`, `categories` json, `version`, `policy_version`, `ip_hash` (salted, truncated, configurable off), `user_agent` (optional), `user_id` nullable, `created_at`. Written on every decision and withdrawal; retention command `consent:prune --months=` (default 36). Switch `log_decisions` for sites that prefer not to store anything.
6. **Authenticated users (NEW)**: optional sync of the last decision to the user (`consent_preferences` json on a configurable user model) so a returning logged-in visitor on a new device is not asked again; always overridable.
7. **GDPR data-subject tooling (NEW)**: `Consent::exportFor($subject)` and `Consent::eraseFor($subject)` return/remove consent logs; an `ErasesPersonalData` contract that other packages implement (`laravel-visit-analytics` registers purge of `visitor_id`, `ip`, `user_agent`; `laravel-ticketing` registers anonymisation of requester data). `php artisan consent:erase {subject}` runs all registered erasers. This is the integration point of the whole family.
8. **Events**: `ConsentGiven`, `ConsentWithdrawn` carry the state. Listeners in other packages: visit-analytics drops `v_id`; bot-detector log action honours `analytics`.
9. **Global Privacy Control**: `Sec-GPC: 1` pre-selects reject for non-necessary categories when `respect_gpc` is on.
10. **Frontend-agnostic architecture** (three layers, logic never lives in the UI):
    - **Core PHP**: `ConsentManager`, middleware, logs, events (points above).
    - **HTTP API** (package routes, `web` middleware group, prefix configurable, CSRF-protected):
      `GET /consent` returns the payload; `POST /consent` with `decision=accept_all|reject_all|custom|withdraw` (+ `categories[]` for `custom`).
      It answers JSON when `Accept: application/json`, otherwise sets the cookie and `redirect()->back()` (no-JS fallback).
      Required anyway: the cookie is `HttpOnly`, so no client framework can read or write it directly.
    - **`Consent::payload()`**: the single serialisable contract every UI consumes: categories (key, translated label/description, locked),
      translated texts (title, body, button labels), current state, `hasDecided`, version, script registry, endpoint URLs.
      Exposed by `<x-consent::bootstrap />` (JSON in `window.__consent`), by `GET /consent`, and shareable as an Inertia prop
      (`'consent' => Consent::payload()` in `HandleInertiaRequests`; documented, not automatic).
    - **JS core** `consent.js`, zero dependencies: reads the payload; exposes `window.Consent` with `accept()`, `reject()`, `save(categories)`,
      `withdraw()`, `open()`, `state()`, `allows(cat)`, `subscribe(fn)`; posts to the API with `X-CSRF-TOKEN`; injects granted scripts;
      dispatches `consent:updated` and listens for `consent:open` (replaces `consent-updated` / `open-cookie-banner`).
      Progressive enhancement: it intercepts the banner's `<form>` submit and does it via `fetch`, no reload.
      Published to `public/vendor/consent/` (no Vite needed); also importable as an ES module from the host's bundle.
11. **UI layer**:
    - **Default: `<x-consent::banner />`**, plain Blade, placed in the root layout. Works in pure Blade apps, Livewire apps and Inertia
      React/Vue apps alike, because every Laravel app has a Blade root template (`app.blade.php`); the banner lives outside the SPA root.
      Without JS: form POST + redirect back; the middleware reads the cookie on the next request, banner hidden, `@consent` renders.
      Customise with `vendor:publish --tag=consent-views` or swap the view via `config('consent.banner.view')`.
    - **Native components**: `php artisan consent:install --stack=react|vue|livewire` copies into the app (Breeze-style, the host owns them):
      React `useConsent()` hook / Vue `useConsent()` composable (~30 lines over the JS core) + an example banner component;
      Livewire a component calling `ConsentManager` directly. Omitting `<x-consent::banner />` disables the default.
    - **Cached pages** (full-page cache, static HTML): `config('consent.client_state') = true` renders the banner hidden and lets the JS core
      fetch state and a fresh CSRF token from `GET /consent`, so a cached page never carries the first visitor's state or a stale token.
    - Considered and rejected for v0.1: a Web Component `<consent-banner>`: needs JS (breaks the no-JS requirement), Shadow DOM fights
      Tailwind theming, and the root-layout Blade banner already covers every stack. Revisit only if a banner must mount inside the SPA tree without an adapter.
12. **Banner UX** requirements (mostly new: of these, the reference only has unticked toggles): reject must be as prominent as accept (equal visual weight), no pre-ticked toggles except `necessary`, a persistent "cookie settings" link (`<x-consent::settings-link />` dispatching `open-cookie-banner`), accessible dialog (focus trap, `aria-modal`, escape to close only after a choice exists), works without JS for the server-rendered state.
    Guaranteed in the components the package ships (default banner and stubs); custom UIs cannot be enforced, so the README states the
    requirements explicitly and the payload carries no "primary" button hint.
13. **Styling**: Tailwind classes in the reference. Publish the views; also support a CSS-variable theme (`--consent-bg`, `--consent-accent`) so `filament-consent` can customise without editing views. The stubs use the same variables.
14. **Dependencies**: no frontend framework required. `livewire/livewire` and `inertiajs/inertia-laravel` are `suggest` only; the Livewire adapter is registered only when Livewire is installed.

## 4. Cookie declaration and policy generation (NEW, optional)

`consent:scan` lists cookies set by the app (`Cookie::queue` calls cannot be scanned reliably, so cookies are *declared* in config:
name, category, duration, purpose, provider). The declaration feeds the cookie policy table in `laravel-legal-pages` (`<x-consent::cookie-table />`), so the policy cannot drift from reality.
The reference's cookies to declare: `cookie_consent`, `vs_id`, `v_id`, session, XSRF.

## 5. Integration map

| Package | Uses |
| --- | --- |
| `laravel-visit-analytics` | `allows('analytics')`, `ConsentWithdrawn`, eraser |
| `laravel-bot-detector` | log action honours consent for raw IP |
| `filament-pan` | optional script `pan` in `analytics` when `requires_consent` |
| `laravel-legal-pages` | policy versions, cookie table, banner re-prompt on policy change |
| `filament-consent` | edits categories, texts, appearance, shows logs |
| `laravel-ticketing` | eraser |

## 6. Tests

Port the middleware test; port the Livewire test against the controller (and keep a thin one for the Livewire adapter). New: `POST /consent` JSON vs redirect responses, CSRF required, `GET /consent` payload shape (snapshot), no-JS flow (form post → cookie → banner hidden on next request), `client_state` renders the banner hidden, `consent:install` copies the stubs, version bump re-prompts, withdrawal logs and forgets the cookie, GPC, `@consent` directive, log written with hashed IP only, prune, erasers invoked, banner renders equal-weight accept/reject buttons (view test).

## 7. Order of work

1. `ConsentState`, `ConsentManager`, cookie read/write, middleware, tests.
2. HTTP API (`ConsentController`, routes), `Consent::payload()`, script registry, Blade directive.
3. JS core `consent.js`, `<x-consent::bootstrap />`, default Blade banner (no-JS + enhanced), `client_state` mode.
4. Logs, versioning, events, GPC.
5. Adapters: `consent:install` with React/Vue stubs and the Livewire component.
6. Erasers/export, cookie declarations, README (legal disclaimer: tooling, not legal advice; banner UX requirements for custom UIs), `v0.1.0`.
7. Switch simonecerruti.com (Livewire adapter or default Blade banner).
