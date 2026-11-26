# Service detection techniques and open catalogues

Research for [#22](https://github.com/SimoneCerruti/laravel-consent/issues/22) (milestone 7, AI compliance skills: *detect* and *align*).
Question: how can an AI skill find the third-party services and data-collection activities a Laravel app uses, and which open catalogues can map what it finds to services, cookies and purposes?

Facts checked on 2026-11-26. Sizes and dates are snapshots taken that day.

## Answer

1. **Static analysis gives the best results inside a Laravel repo.** Most services announce themselves in places an agent can read without running anything:
   - `composer.lock` and `package-lock.json`
   - `config/services.php` and the `.env` key *names*
   - `<script src>` hosts in Blade views and JS files
   - SDK calls, mail and newsletter drivers
   - forms and validation rules that collect personal data

   Static analysis finds server-side processors (mail, payments, error tracking, search) that no browser crawl can see. It cannot see what a script does once it loads: which cookies it sets, which domains it then calls.
2. **Runtime capture finds what static analysis misses.** A headless browser can record `Set-Cookie`, `document.cookie`, `localStorage` and every network request for each consent state. The open tools that do this follow the same recipe: a Chromium crawl, with each request matched against a filter list and each cookie against a cookie database. Two examples are the EDPS Website Evidence Collector (puppeteer, EasyPrivacy) and the EDPB Website Auditing Tool (built on WEC). Pest browser testing runs on Playwright, but its documented API has no methods for cookies, storage or network requests. Capture therefore needs Playwright itself (`context.cookies()`, `storageState()`, `on('request')`).
3. **Only one of the large open catalogues can be bundled in an MIT package: the Open Cookie Database (Apache-2.0).** The three big tracker databases (DuckDuckGo Tracker Radar, the Disconnect list, the Ghostery TrackerDB behind WhoTracks.me) are all **CC BY-NC-SA 4.0**. That licence bans commercial use and requires derivatives to be shared under the same terms, so none of them can ship inside an MIT package or be relicensed. A skill could at most point the user to them. Domain → service coverage under redistributable terms needs one of these:
   - EasyPrivacy (GPL-3.0+ or CC BY-SA 3.0+, dual licence): flags domains but does not name the service
   - webappanalyzer (GPL-3.0): technology fingerprints, but it is GPL
   - the AdGuard companies DB (CC BY-SA 4.0): derived from WhoTracks.me, see the provenance caveat below
   - a small catalogue maintained in the package itself
4. **Cookie → purpose mapping is the job the OCD fits.** It has 2,288 entries across 359 platforms. Each entry has a category, a description, a retention period, the data controller, a privacy link and a wildcard flag. Those are almost exactly the fields IMPLEMENTATION.md §4 needs for a declared cookie (name, category, duration, purpose, provider). Its five categories (Functional, Personalization, Analytics, Marketing, Security) need a mapping to the package's categories (`necessary`, `analytics`, `functional`, `marketing`, user-defined).

### Catalogue comparison

| Catalogue | Maps | Licence | Bundle in MIT package? | Size (2026-11-26) | Format | Fields of interest | Update cadence |
| --- | --- | --- | --- | --- | --- | --- | --- |
| [Open Cookie Database](https://github.com/jkwakman/Open-Cookie-Database) | cookie / storage key → platform, purpose | Apache-2.0 | **Yes**, with LICENSE and attribution kept | 2,288 rows, 359 platforms, 263 wildcard names; CSV 576 KB | CSV, JSON (grouped by platform), EDPB-WAT JSON | ID, Platform, Category, name, Domain, Description, Retention period, Data Controller, privacy portal URL, Wildcard match | Community PRs merged several times a month (latest 2026-11-06); only one GitHub release (v0.1, 2023) |
| [DuckDuckGo Tracker Radar](https://github.com/duckduckgo/tracker-radar) | third-party domain → owner entity, categories, behaviour | CC BY-NC-SA 4.0 (commercial licence on request) | **No** (NonCommercial + ShareAlike) | 21,365 domain files for the US region alone; 9 regions | one JSON file per domain per region, plus entity files | owner, prevalence, fingerprinting 0–3, cookies, categories, resources (URL regex), `firstPartyCookies`, cnames | Tagged releases about every 2–4 weeks (2026.11.23, 2026.11.10, 2026.10.20) |
| [Disconnect tracking protection](https://github.com/disconnectme/disconnect-tracking-protection) | domain → service → category | CC BY-NC-SA 4.0 (commercial licence by email) | **No** | `services.json` 379 KB, 4,821 domains; categories include Advertising 1,273 services, Analytics 358, FingerprintingInvasive 279 | JSON: `categories.{Category}[{Service: {url: [domains]}}]`, plus `entities.json` | service name, homepage, domains, category | Commits several times a month; PRs not accepted |
| [Ghostery TrackerDB](https://github.com/ghostery/trackerdb) (source of WhoTracks.me metadata) | domain / URL filter → pattern (service) → organization, category | CC BY-NC-SA 4.0 (commercial use: contact sales) | **No** | 3,544 patterns, 2,613 organizations; `trackerdb.json` 3.2 MB | `.eno` source files; release assets `.json`, `.db` (SQLite), `.engine`, `.txt` | name, category, website, organization (country, privacy policy, privacy contact), domains, filters | Releases several times a week |
| [WhoTracks.me](https://github.com/whotracksme/whotracks.me) | site/tracker prevalence statistics | Repo MIT; metadata comes from TrackerDB (CC BY-NC-SA 4.0); no licence stated for the monthly dataset | Code yes; data unclear or No | top 10,000 sites | monthly CSV in a public S3 bucket, SQLite snapshot of TrackerDB | prevalence and reach per tracker and site | Monthly |
| [EasyPrivacy](https://easylist.to/) | URL pattern → "tracking" (no service name) | GPL-3.0+ **or** CC BY-SA 3.0+ (dual) | Yes as a separate file under CC BY-SA, with attribution; not relicensable to MIT | ~56,870 rule lines | Adblock filter syntax | rule only | "Expires: 4 days"; updated several times a day |
| [webappanalyzer](https://github.com/enthec/webappanalyzer) (open fork of the old Wappalyzer rules) | cookies, `scriptSrc`, JS globals, DOM, headers → technology, category | GPL-3.0 | Risky; safer to fetch at runtime than to ship | 7,715 technologies, 109 categories (Analytics, Advertising, Payment processors, Live chat, Tag managers, Cookie compliance, …) | JSON, one file per initial letter | `cookies`, `scriptSrc`, `js`, `dom`, `html`, `headers`, `cats`, `website` | Continuous (commits 2026-11-22) |
| [AdGuard companies DB](https://github.com/AdguardTeam/companiesdb) | tracker domain → company | Declared CC BY-SA 4.0, but its README says it is "basically the Whotracks.me database converted to a simple JSON format" | Licence declared yes, provenance doubtful (upstream metadata is NC-SA) | (not measured) | JSON | tracker, company, category, domains | Continuous (pushed 2026-11-23) |

## 1. Static signals in a Laravel app

These signals come from files any Laravel repo has, so a Boost skill can read them with the agent's normal file tools. Boost's MCP *Application Info* tool already returns the installed ecosystem packages with versions ([Laravel Boost docs](https://laravel.com/docs/12.x/boost#available-mcp-tools)).

| Signal | Where | What it reveals | Limits |
| --- | --- | --- | --- |
| PHP dependencies | `composer.lock` (or `composer show --locked --format=json`, [Composer CLI](https://getcomposer.org/doc/03-cli.md#show-info)) | server-side SDKs: payments, error tracking, mail, search, social login, newsletter | presence ≠ use; dev-only packages must be excluded (`packages-dev`) |
| JS dependencies | `package-lock.json` / `npm ls --json` ([npm ls](https://docs.npmjs.com/cli/commands/npm-ls)) | client SDKs bundled by Vite (analytics, chat, payments, realtime) | bundled SDKs load no `<script src>` from a third-party host, so a script-host scan misses them |
| Third-party service credentials | `config/services.php`; the skeleton documents it as "the de facto location" for "credentials for third party services such as Resend, Postmark, AWS" and ships `postmark`, `resend`, `ses`, `slack` ([laravel/laravel config/services.php](https://github.com/laravel/laravel/blob/13.x/config/services.php)) | processors that never touch the browser | packages may use their own config files (`config/<package>.php`) |
| Environment keys | `.env.example` (key names only; never read secret values) | e.g. `MAIL_MAILER`, `AWS_*`, `*_API_KEY`, `*_DSN` ([laravel/laravel .env.example](https://github.com/laravel/laravel/blob/13.x/.env.example)) | naming is free-form |
| Mail / queue / storage drivers | `config/mail.php`, `queue.php`, `filesystems.php`, `logging.php` | mail transport (SES, Postmark, Resend, SMTP host), SQS, S3, log shipping | values often come from env |
| Script tags and third-party hosts | `resources/views/**/*.blade.php`, `resources/js/**` | `<script src="https://…">`, `<iframe>` embeds (video, maps), pixels `<img src>`, inline snippets (`gtag(`, `fbq(`) | hosts built dynamically by tag managers are invisible statically |
| SDK calls | `app/**` | facade/client calls (`Http::post('https://api.…')`, SDK classes) | needs per-SDK knowledge |
| Personal-data collection | Form Requests and `validate()` rules (`email`, phone, address), migrations (columns like `email`, `ip`, `user_agent`), Blade `<input type="email">` | data-collection *activities* (contact, newsletter, checkout, sign-up) for the privacy policy | purpose and legal basis need a human answer |
| Laravel's own cookies | `config/session.php`: `'cookie' => env('SESSION_COOKIE', Str::slug(env('APP_NAME','laravel')).'-session')` ([session.php](https://github.com/laravel/laravel/blob/13.x/config/session.php)); `XSRF-TOKEN` set by `PreventRequestForgery` ([framework 13.x](https://github.com/laravel/framework/blob/13.x/src/Illuminate/Foundation/Http/Middleware/PreventRequestForgery.php)) | first-party strictly necessary cookies | the session cookie name changes with `APP_NAME`; the OCD lists only the literal `laravel_session` |

Trade-offs: static analysis is deterministic, offline, and covers server-side processors that matter for the privacy policy (the *align* skill), not only cookies. It cannot list the cookies or the downstream domains a third-party script uses at runtime.

## 2. Runtime signals

### What to record

- **Cookies**: HTTP `Set-Cookie` headers and cookies written with `document.cookie`, with name, domain, expiry and whether they are first- or third-party.
- **Web storage**: `localStorage` and `sessionStorage` (and IndexedDB). ePrivacy Art. 5(3) covers any storing of, or access to, information on the terminal device, not only cookies. The EDPB's [Guidelines 2/2023 on the technical scope of Art. 5(3)](https://www.edpb.europa.eu/our-work-tools/documents/public-consultations/2023/guidelines-22023-technical-scope-art-53-eprivacy_en) also cover tracking pixels and URL tracking.
- **Network requests**: every request host, so it can be matched against a domain catalogue.
- **Per consent state**: one run with no decision, one after reject, one after accept. A visit before consent shows what leaks before the banner is answered. The package's own consent cookie can be preset so the crawler skips the banner.

### Tooling facts

| Tool | Engine | What it captures | Licence | Notes |
| --- | --- | --- | --- | --- |
| [Playwright](https://playwright.dev/docs/api/class-browsercontext) | Chromium / Firefox / WebKit | `browserContext.cookies()`; `storageState()` returns cookies and `origins[].localStorage` (IndexedDB only with `indexedDB: true`); `on('request')` / `on('response')`; `route()` to intercept | Apache-2.0 | the most direct API for a crawl script run by the skill |
| [Pest browser testing](https://pestphp.com/docs/browser-testing) (`pestphp/pest-plugin-browser`) | Playwright (`npm install playwright`, `npx playwright install`) | documented API: `script()` / `assertScript()` to run JS; `assertNoConsoleLogs()`, `assertNoJavaScriptErrors()`; no cookie, storage or network methods | MIT | `script('document.cookie')` and `script('JSON.stringify(localStorage)')` work; HttpOnly cookies and request hosts are out of reach (only visible in traces) |
| [Website Evidence Collector](https://code.europa.eu/EDPS/website-evidence-collector) (EDPS) | puppeteer / Chromium | cookies, storage, beacons, third-party requests; `--max`/`--depth` crawl; optional testssl.sh; `--cookie-db` JSON (`name` with glob, `domain` or `*`, `category`) to label cookies | EUPL-1.2 (code); EasyList filter lists under their own licence | v5.0.0 released 2026-11-20; ships EasyPrivacy in `assets/` |
| [EDPB Website Auditing Tool](https://code.europa.eu/edpb/website-auditing-tool) | Electron, built on WEC components | evidence collection, analysis, reports; uses EasyPrivacy and Fanboy's Annoyance list | EUPL-1.2+ | release 3.0.1 (2026-09-28); the OCD ships an EDPB-format JSON for its knowledge base |
| [Tracker Radar Collector](https://github.com/duckduckgo/tracker-radar-collector) | puppeteer | modular collectors (`requests`, `cookies`, API calls, `cookiepopups` with autoconsent `optIn`/`optOut`) | Apache-2.0 | produces the data behind Tracker Radar |
| [OpenWPM](https://github.com/mozilla/OpenWPM) | Firefox | research-grade web privacy measurement | GPL-3.0 (LICENSE file) | heavy; built for large studies |
| [CookieBlock](https://github.com/dibollinger/CookieBlock) | browser extension | classifies each cookie into Necessary / Functionality / Analytics / Advertising with an XGBoost model | MIT (extension, [classifier](https://github.com/dibollinger/CookieBlock-Consent-Classifier)); [crawler](https://github.com/dibollinger/CookieBlock-Consent-Crawler) `crawler/` GPL-3.0 | training labels came from cookie notices of consent-management platforms; last push 2023-12 |

Trade-offs: runtime capture shows what really happens, including cookies set by tag managers and SDKs. It needs a running app, a Node/Playwright install, routes worth visiting (logged-in pages, checkout), and seeded data. A crawl of a local dev environment can also differ from production: env-gated scripts, test keys.

## 3. Catalogue details

### Open Cookie Database

- Licence: Apache License 2.0 ([LICENSE](https://github.com/jkwakman/Open-Cookie-Database/blob/master/LICENSE)). Apache-2.0 allows redistribution and modification. Redistributors must keep the licence text and attribution notices, and mark modified files.
- Files: `open-cookie-database.csv`, `open-cookie-database.json`, `open-cookie-database-edpb.json` ([README](https://github.com/jkwakman/Open-Cookie-Database/blob/master/README.md)). CSV header: `ID,Platform,Category,Cookie / Data Key name,Domain,Description,Retention period,Data Controller,User Privacy & GDPR Rights Portals,Wildcard match`.
- Categories in the README: Functional, Personalization, Analytics, Marketing, Security. Snapshot counts: Functional 994, Marketing 864, Analytics 391, Security 24, Necessary 9 (a value not in the README), Personalization 6.
- Wildcards: `1` in the last column means the name is a pattern (e.g. `_gac_1234`). The README does not define a regex syntax, so matching rules are left to the consumer.
- Covers "Cookie / Data Key name", so it also contains localStorage and sessionStorage keys (e.g. the 2026-10-20 commit "Add 4 session-storage items").
- Laravel entries: `laravel_session` (Functional) and `XSRF-TOKEN` (Security, no platform).

### DuckDuckGo Tracker Radar

- Licence: "Creative Commons Attribution-NonCommercial-ShareAlike 4.0 International … If you'd like to license the list for commercial use, please reach out" ([README](https://github.com/duckduckgo/tracker-radar/blob/main/README.md), [LICENSE](https://github.com/duckduckgo/tracker-radar/blob/main/LICENSE)).
- Data model ([DATA_MODEL.md](https://github.com/duckduckgo/tracker-radar/blob/main/docs/DATA_MODEL.md)):
  - fields: `domain`, `owner` (name, url, privacyPolicy, displayName), `prevalence`, `fingerprinting` 0–3, `cookies`, `categories`, `resources[]` (URL regex, `firstPartyCookies` with ttl and uniqueness), `cnames`
  - regions: US, AU, CA, CH, DE, FR, GB, NL, NO
- Categories ([CATEGORIES.md](https://github.com/duckduckgo/tracker-radar/blob/main/docs/CATEGORIES.md)) include Analytics, Advertising, Session Replay, Embedded Content, Online Payment, Federated Login, Tag Manager, Social Network, CDN.
- `firstPartyCookies` is the only open source in this list that ties a third-party *script* to the first-party *cookie names* it sets. It comes with the NC-SA licence.

### Disconnect

- Licence: CC BY-NC-SA 4.0, also embedded as the `license` key of `services.json` ([LICENSE](https://github.com/disconnectme/disconnect-tracking-protection/blob/master/LICENSE)). "Please email … if you'd like to license the lists for commercial use"; "Pull requests are not reviewed and will be closed" ([README](https://github.com/disconnectme/disconnect-tracking-protection/blob/master/README.md)).
- Used by Firefox Enhanced Tracking Protection through [mozilla-services/shavar-prod-lists](https://github.com/mozilla-services/shavar-prod-lists), which keeps a copy of `services.json`.
- No cookie names and no purposes beyond the category.

### Ghostery TrackerDB / WhoTracks.me

- TrackerDB: "published under CC-BY-NC-SA-4.0 license which means it is free to use for non-commercial purposes. If you want to use it in your business, please contact" Ghostery ([README](https://github.com/ghostery/trackerdb/blob/main/README.md)).
- Entities in TrackerDB:
  - **categories**: Advertising, Audio/Video Player, Consent Management, Customer Interaction, Hosting, Site Analytics, Social Media, Utilities, …
  - **organizations**: country, privacy policy URL, privacy contact
  - **patterns**: domains plus Adblock-style filters (e.g. [google_analytics.eno](https://github.com/ghostery/trackerdb/blob/main/db/patterns/google_analytics.eno))

  It also ships a JS SDK and CLI (`npx @ghostery/trackerdb <url>`).
- WhoTracks.me: the repository is MIT ([LICENSE.md](https://github.com/whotracksme/whotracks.me/blob/master/LICENSE.md)). Data is published monthly to `s3://data.whotracks.me`. "all meta data … is maintained" in TrackerDB ([data README](https://github.com/whotracksme/whotracks.me/blob/master/whotracksme/data/Readme.md)). Neither the data README nor the [site](https://www.ghostery.com/whotracksme/) states a licence for the monthly dataset.

### EasyPrivacy

- Dual licence: "GNU General Public License version 3 … or (at your option) any later version, and Creative Commons Attribution-ShareAlike 3.0 Unported, or (at your option) any later version"; "The EasyList authors (https://easylist.to/) should be attributed" ([licence page](https://easylist.to/pages/licence.html)).
- List header on 2026-11-26: `Version: 202611261757`, `Expires: 4 days (update frequency)` ([easyprivacy.txt](https://easylist.to/easylist/easyprivacy.txt)).
- Says *that* a URL tracks, not *who* runs it or *why*. WEC and the EDPB tool rely on it.

### webappanalyzer

- GPL-3.0 ([repo](https://github.com/enthec/webappanalyzer)). It continues the Wappalyzer rules after Wappalyzer "went private in August 2023".
- Fingerprints match on `cookies`, `scriptSrc`, `js` globals, `dom`, `html` and headers. Static analysis of Blade output is one of the uses. The category list includes Analytics, Advertising, Payment processors, Live chat, Tag managers, A/B Testing, Cookie compliance.

## 4. Licence analysis for an MIT package

- **CC BY-NC-SA 4.0** ([legal code](https://creativecommons.org/licenses/by-nc-sa/4.0/legalcode.en)):
  - Licensed rights are granted "for NonCommercial purposes only". NonCommercial means "not primarily intended for or directed towards commercial advantage or monetary compensation".
  - Adapted material must be shared under the same or a compatible licence.
  - An MIT package grants commercial use to everyone, so NC-SA data cannot be included or relicensed in it.
  - A host app that is a business site would itself need a commercial licence to use the data.
  - This applies to Tracker Radar, Disconnect and TrackerDB.
- **Apache-2.0** (OCD): compatible with shipping inside an MIT package as a separately licensed data file. The package must keep the `LICENSE`/attribution and state any changes.
- **CC BY-SA 3.0/4.0** (EasyPrivacy option, AdGuard companies DB): can be redistributed with attribution. Adapted versions stay BY-SA, so the data file keeps its own licence and cannot become MIT. AdGuard's BY-SA claim sits on data its README says comes from WhoTracks.me, whose metadata is NC-SA.
- **GPL-3.0** (webappanalyzer, EasyPrivacy option, OpenWPM): copyleft; bundling GPL data or code with an MIT package raises combined-work questions. Fetching at runtime, at the user's choice, avoids redistribution.
- **EUPL-1.2** (WEC, EDPB WAT): copyleft for the tools. The skill can *invoke* them as external CLIs without redistributing them.

## 5. Options for the skills (trade-offs only)

| Option | Gains | Costs |
| --- | --- | --- |
| Static-only *detect* (lockfiles, config, env key names, Blade/JS hosts, form rules) | no runtime deps; deterministic; covers server-side processors for the privacy policy | misses cookies set by scripts; needs a service → signal mapping |
| Static + optional Playwright crawl | real cookies, storage and request hosts per consent state | Node + browsers required; needs a served app and seeded routes; dev ≠ prod |
| Bundle OCD (Apache-2.0) | offline cookie → purpose/provider/retention lookup in the declaration's shape | 2,288 entries; Laravel session name drift; category mapping needed; snapshot ages unless refreshed |
| Package-maintained domain/SDK catalogue (MIT, written from vendor docs) | licence-clean domain → service mapping | maintenance burden; small coverage |
| Reference NC-SA catalogues (Tracker Radar, Disconnect, TrackerDB) without bundling | widest domain coverage | licence prevents bundling; use is limited to non-commercial purposes unless the user has a commercial licence |
| Shell out to WEC (`--cookie-db` fed from OCD) | proven EDPS crawler, reports, EasyPrivacy matching | npm install from the code.europa.eu registry; Node ≥ 24.22.1; heavy |
