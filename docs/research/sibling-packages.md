# Sibling packages: state and contracts

Research for [#13](https://github.com/SimoneCerruti/laravel-consent/issues/13). Snapshot taken 2026-11-26.

Question: what is the state of the sibling packages under `~/projects` and on GitHub? Does a `ResolvesConsent`
contract exist, and what shape does it have? What personal data does each package store that an eraser would touch?
Does `laravel-legal-pages` already model policy versions or legal pages?

## Answer

Only `laravel-bot-detector` exists as code. Every other sibling is a single `IMPLEMENTATION.md` plan in a local
folder, with no git history and no GitHub repository. So **no `ResolvesConsent` contract exists anywhere yet**. It is
only specified in the visit-analytics plan.

| Package | Exists? | Maturity | `ResolvesConsent` | Personal data an eraser would touch | Consent-related events | Notes for laravel-consent |
| --- | --- | --- | --- | --- | --- | --- |
| `laravel-visit-analytics` | Plan only (`~/projects/laravel-visit-analytics/IMPLEMENTATION.md`); no repo on GitHub | Not started. Reference code lives in `simonecerruti.com` | **Planned here** (owner of the interface): `Contracts\ResolvesConsent::allows(string $category = 'analytics'): bool`; default implementation reads the `cookie_consent` cookie | `visit_sessions.visitor_id`, `ip`, `user_agent` (stored only with consent); the `v_id` cookie | Listens to `ConsentWithdrawn` (drops `v_id`; optional `purge_on_withdrawal` nulls visitor_id/ip/ua) | Main consumer. Implements `ErasesPersonalData` |
| `laravel-ticketing` | Plan only; no repo | Not started, no reference code | Not used | Requester name, email and IP on tickets and messages (guest requesters are name+email); GitHub issues are out of scope | None planned on consent | Implements `ErasesPersonalData` (anonymise, keep the record) |
| `laravel-legal-pages` | Plan only; no repo | Not started. Reference has only 3 fixed Livewire pages | Not used | `legal_acceptances` (polymorphic `acceptor`, `ip_hash`). **Not listed as an eraser** in any plan | Fires `LegalDocumentPublished`; can bump `consent.version` (opt-in) | **Yes, it plans policy versions**: `LegalDocument` + `LegalDocumentVersion(version int, effective_at, published_at)` + `LegalAcceptance` |
| `filament-consent` | Plan only; no repo | Not started; blocked on laravel-consent v0.2 | Not used | None of its own; shows `consent_logs` (no IP) | Offers "bump consent version" when a policy is published | Needs a `ConsentSettingsRepository` contract **in laravel-consent** |
| `laravel-bot-detector` | Yes: [GitHub](https://github.com/SimoneCerruti/laravel-bot-detector), local clone, Packagist | `v0.1.0` (pre-release, 2026-11-24), 37 commits | No reference to consent at all | No database tables. Writes log lines (HMAC'd IP, raw user agent) and cache keys (hashed IP+UA, DNS cache per raw IP) | `BotDetected` (not consent-related) | The planned "log honours consent for raw IP" is **not implemented**. Raw IP is a config flag, off by default |

The plan-only siblings above also have plan-only Filament counterparts: `filament-pan`, `filament-visit-analytics` and `filament-ticketing`.
Each is also just an `IMPLEMENTATION.md`.

### What this means for laravel-consent

1. **The contract cannot be "implemented when present" the way IMPLEMENTATION.md §3.3 says.** PHP cannot implement an
   interface conditionally on a class that does not exist (`IMPLEMENTATION.md:60`). Use a service-provider binding
   guarded by `interface_exists(\SimoneCerruti\VisitAnalytics\Contracts\ResolvesConsent::class)` that maps to a small
   adapter or closure delegating to `ConsentManager::allows()`. The adapter must not `implements` the interface at the
   top level. The alternative is for laravel-consent to own a `ResolvesConsent` contract and have visit-analytics
   depend on it. Since visit-analytics is not written yet, either choice is still open. The namespace
   `SimoneCerruti\VisitAnalytics\…` is inferred from bot-detector's `SimoneCerruti\BotDetector\…` convention. No plan
   states it.
2. **The shape to match is `allows(string $category = 'analytics'): bool`.** It already equals the planned
   `ConsentManager::allows(string $category): bool` (`IMPLEMENTATION.md:60`), so the adapter is a one-liner.
3. **`ErasesPersonalData` belongs to laravel-consent** (`IMPLEMENTATION.md:64`, `laravel-ticketing/IMPLEMENTATION.md:59`).
   Its known implementers and data are listed in the table. `legal_acceptances` is a gap: it holds personal data, but
   no plan registers it as an eraser. Erasure may also conflict with its purpose as proof of acceptance.
4. **`policy_version` in `consent_logs` should point at legal-pages' `LegalDocumentVersion`.** Legal-pages versions
   each document separately (privacy and cookie each have their own `version` int). A single scalar `policy_version`
   (`IMPLEMENTATION.md:62`) is therefore ambiguous. A nullable reference to the cookie-policy version id, or a
   config-driven int that legal-pages bumps, are the two options.
5. **Events:** `ConsentGiven` / `ConsentWithdrawn` (`IMPLEMENTATION.md:65`) have one planned listener
   (visit-analytics). The bot-detector listener in §3.8 / §5 does not exist and would need new code in bot-detector.

## Details

### laravel-visit-analytics

- Local: `~/projects/laravel-visit-analytics/` contains only `IMPLEMENTATION.md` (88 lines); not a git repo. GitHub:
  `gh api repos/SimoneCerruti/laravel-visit-analytics` returns 404; it is absent from `gh repo list SimoneCerruti`.
- Reference implementation: `~/projects/simonecerruti.com/app/Http/Middleware/TrackVisit.php`. It reads consent from
  `view()->shared('cookieConsent')` (lines 144-147). That value is set by
  `app/Http/Middleware/CookieConsentMiddleware.php:17-47` from the JSON cookie `cookie_consent`, which carries
  `version`, `analytics`, `functional` and `marketing`.
- `ResolvesConsent`: `laravel-visit-analytics/IMPLEMENTATION.md:35-38`. It defines
  `Contracts\ResolvesConsent: allows(string $category = 'analytics'): bool`. The default reads the configurable
  `cookie_consent` cookie. laravel-consent's provider rebinds the contract. `consent.required` (default true) treats
  everyone as consented when false. Skeleton: `src/Contracts/ResolvesConsent.php` and
  `src/Support/CookieConsentCookieResolver.php` (lines 56, 60).
- Personal data (`IMPLEMENTATION.md:26-28`; reference migration
  `simonecerruti.com/database/migrations/2026_05_28_153125_create_visit_sessions_table.php:13,27,28,31`):
  - `visit_sessions.visitor_id` (uuid; the join key for one person across sessions);
  - `ip`;
  - `user_agent`;
  - `consent_analytics` (bool snapshot).

  `page_views` carries no direct identifier (path, route, locale, timestamp), but it is linked to the session. Cookies:
  `vs_id` (30 min) and `v_id` (365 days, consent only).
- Integration (`IMPLEMENTATION.md:68-73`):
  - asks `allows('analytics')`;
  - listens to `ConsentWithdrawn` to delete `v_id` and, with `purge_on_withdrawal`, nulls visitor_id/ip/user_agent;
  - proof of consent stays in laravel-consent.

  Its own retention is `visit-analytics:prune` (400 days) and `visit-analytics:anonymize` (IP/UA after 30 days), at
  lines 44 and 46. It treats GPC/DNT as no consent.

### laravel-ticketing

- Local: `~/projects/laravel-ticketing/IMPLEMENTATION.md` only (77 lines), titled "future package, no reference
  code". No GitHub repo.
- Personal data (`IMPLEMENTATION.md:32-33`):
  - `Ticket` requester (morph: user, or guest with name+email);
  - `TicketMessage` author and body;
  - attachments;
  - GitHub-linked issues. The plan strips requester data from these by default (lines 47, 55).
- GDPR (`IMPLEMENTATION.md:57-61`):
  - implements laravel-consent's `ErasesPersonalData`, anonymising requester name/email/IP on tickets and messages and
    keeping the operational record (`ticketing:anonymize --months=`);
  - uses legal-pages' acceptance checkbox;
  - GitHub issues are explicitly not erased.

### laravel-legal-pages

- Local: `~/projects/laravel-legal-pages/IMPLEMENTATION.md` only (54 lines), titled "future package, partial
  reference". No GitHub repo. The reference site only renders three fixed Livewire pages, with "No versioning, no
  custom pages, no acceptance tracking" (lines 6-7).
- Planned model (`IMPLEMENTATION.md:19, 33-38`):
  - `LegalDocument` (`slug`, `type` enum Privacy/Cookie/Terms/Shipping/Returns/Refund/Custom, translatable `title`,
    `requires_acceptance`, `show_in_footer`, `sort`);
  - `LegalDocumentVersion` (`version` int, translatable `body`, `effective_at`, `published_at`, `change_summary`;
    exactly one current version, older ones still viewable);
  - `LegalAcceptance` (polymorphic `acceptor`, document version id, `accepted_at`, `ip_hash`).
- Consent link:
  - `{cookie_table}` placeholder renders laravel-consent's cookie declarations (line 35);
  - `LegalDocumentPublished` for cookie/privacy documents can bump `consent.version` when
    `bump_consent_version_on_publish` is set (default false, line 37).
- Personal data: `legal_acceptances` (acceptor + `ip_hash`). Neither this plan nor `IMPLEMENTATION.md:64` registers
  it with `ErasesPersonalData`.

### filament-consent

- Local: `~/projects/filament-consent/IMPLEMENTATION.md` only (56 lines), titled "future package, no reference code".
  No GitHub repo.
- It requires laravel-consent v0.2 to expose a `ConsentSettingsRepository` contract with a config default, which
  Filament rebinds to the `consent_settings`, `consent_categories` and `consent_scripts` tables (lines 13-18, 43, 53).
- It reads `ConsentLog` read-only, without IP (line 30), and edits cookie declarations that feed legal-pages (line 37).
  It stores no personal data of its own.

### laravel-bot-detector

- Real package: [github.com/SimoneCerruti/laravel-bot-detector](https://github.com/SimoneCerruti/laravel-bot-detector)
  (public). Release `v0.1.0` is marked pre-release (2026-11-24) and is on Packagist as
  `simonecerruti/laravel-bot-detector`. Namespace `SimoneCerruti\BotDetector`. The local clone is at
  `~/projects/laravel-bot-detector` with 37 commits, `main` in sync with origin.
- No mention of consent anywhere in `src/` or `config/`, and no `ResolvesConsent` reference.
- Personal data it handles (no migrations, no tables):
  - `src/Reactions/Log.php:17-36` writes `ip` (HMAC-SHA256 keyed by `app.key` unless
    `bot-detector.log.raw_ip`), the raw `user_agent`, path and route to a log channel. The flag is in
    `config/bot-detector.php:116-125` (env `BOT_DETECTOR_LOG_RAW_IP`, default false).
  - `src/Reactions/Throttle.php:37-39` uses a cache key that hashes IP+UA.
  - `src/GoodBotVerifier.php:35` uses a DNS cache key containing the raw IP.

  None of this is something a database eraser can reach. Log files fall under the host's log retention.
- Events: `src/Events/BotDetected.php` (detection, request, dry_run), dispatched in
  `src/Http/Middleware/DetectBots.php:51`. It has nothing to do with consent.
- The laravel-consent plan says "bot-detector log action honours `analytics`" (`IMPLEMENTATION.md:65`, `:111`). This
  is not implemented. With the HMAC default the question only matters when `raw_ip` is on, and it needs a change in
  bot-detector, which would have to reach laravel-consent through a contract it does not depend on today.

### Other siblings seen

`~/projects/filament-pan`, `filament-visit-analytics` and `filament-ticketing` are also plan-only. `filament-pan` wants
a `pan` script registered in the `analytics` category when `requires_consent` is true
(`filament-pan/IMPLEMENTATION.md:53-56`). `filament-visit-analytics` wants a consent-rate stat from `ConsentLog`
(`filament-visit-analytics/IMPLEMENTATION.md:44-49`).

## Sources

- `gh repo list SimoneCerruti --limit 100`: of the siblings, only `laravel-bot-detector` and `laravel-consent` exist.
- `gh api repos/SimoneCerruti/{laravel-visit-analytics,laravel-ticketing,laravel-legal-pages,filament-consent}`: 404.
- `gh release list -R SimoneCerruti/laravel-bot-detector`: `v0.1.0`, pre-release.
- `https://repo.packagist.org/p2/simonecerruti/laravel-bot-detector.json`: package published.
- `grep -r "ResolvesConsent\|ErasesPersonalData" ~/projects` (excluding vendor) matches only three plan files:
  laravel-consent, laravel-visit-analytics and laravel-ticketing.
