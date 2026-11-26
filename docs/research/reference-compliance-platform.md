# What the reference compliance platform offers

Research for issue #21 (map #2, milestone 7: the AI skills *detect* and *align*).
Researched 2026-11-26. The platform is not named here on purpose; citations take the form "reference platform: <page title or section>".

## Answer

The reference compliance platform is built around **one catalogue of third-party services** that drives everything else: the privacy policy, the cookie policy, the cookie banner categories and script blocking.

- **Catalogue.** About 1,900 to 2,000+ prebuilt service clauses (public pages give counts of 1,700, "over 1900" and "2000+"), each written by its legal team. Each clause is filed under a **purpose ("macro area")** such as *Analytics*, *Managing contacts and sending messages* or *Displaying content from external platforms*. One service can appear under several purposes, for example Hotjar under both *Analytics* and *Heat mapping and session recording*.
- **Per-service record.** Each record holds a service name and **provider (legal entity)**, a short description, **"Personal Data processed"** (a controlled vocabulary such as *Trackers; Usage Data; email address*), **"Place of processing"** (country), a link to the provider's privacy policy, an optional **Opt Out** link, and in newer versions the retention and transfer basis. Each purpose also carries a fixed explanatory paragraph that appears once above its services.
- **Purposes to consent categories.** For the banner, purposes roll up into five consent categories with fixed ids: 1 Necessary (strictly necessary), 2 Functionality (basic interactions), 3 Experience (enhancement), 4 Measurement (analytics), 5 Marketing (targeting and advertising). The banner reads these categories from the generated cookie policy, and scripts are tagged with them for blocking.
- **Scanner.** A remote crawler loads the site and finds scripts, iframes, cookies and other trackers. It then matches them against the catalogue and sorts results into **Perfect matches** (high confidence, add in one click), **Suggestions** (possible, needs a human to confirm) and **Ignored** (dismissed by the user and never reported again). It runs on demand and periodically, and it emails the owner when something is found that the policy does not cover. A separate cookie scanner reports each cookie's name, value and expiry. The platform itself documents the limits: it only sees what is on the scanned pages, it can attribute widget-injected scripts to the wrong source, and it can report leftovers from services that were removed long ago.
- **Policy updates.** Adding a service, whether by hand, from scanner results, or through its MCP connector (e.g. "I just added Mailchimp to my site. Add it to my policy."), inserts the clause under its purpose heading. The privacy and cookie policies are hosted remotely, so embedded copies change immediately with no redeploy. The cookie policy and the banner's category list are derived from the same selection. Legal text is also updated centrally when the law changes, and those changes are listed in a public "Legal Changelog".

**Takeaways for milestone 7.** *detect* should behave like the scanner: match evidence (script URLs, iframes, cookies, package dependencies) against a catalogue, and report findings in three bands: confirmed, suggested and ignored. *align* should behave like adding a service: one catalogue entry (provider, purpose, data categories, place of processing, policy and opt-out links, cookies, consent category) produces the cookie declaration, the script-registry entry and the legal-page paragraph together. The platform's purpose list and its five consent categories make a sensible base vocabulary for the package's own catalogue.

## Details

### 1. Service catalogue and its organisation

Sources: reference platform: "How to Add Services to Your Privacy Policy"; "How to Add a Custom Service and Customize to Your Needs"; "Privacy and Cookie Policy Generator – Legal Changelog"; "Recent Integrations into the Privacy Policy Generator"; WordPress plugin readme; generated policies published by third-party sites.

- **Size:** "over 1900 clauses", described on other pages as "1700 third-party integrations and clauses" or "2000+ pre-crafted clauses in up to 27 languages". The numbers differ because the pages were written at different times.
- **How a user picks services:** the generator has a searchable list of services. The user adds services by name, or accepts them from scanner results, or creates a **custom service** (paid plans). A custom service lets the user pick a purpose from a drop-down of "over 40 purposes". If no purpose is chosen, the service defaults to "No specific purpose" and appears under "Further information about Personal Data". The user also chooses whether the service shows on the privacy policy, the cookie policy, or both.
- **Generated document structure.** The section "Detailed information on the processing of Personal Data" says "Personal Data is collected for the following purposes and using the following services". Under it come:
  - a **purpose heading (macro area)**, followed by a fixed explanatory paragraph, e.g. for *Analytics*: "The services contained in this section enable the Owner to monitor and analyze web traffic and can be used to keep track of User behavior.";
  - one **service entry** per service: `Service name (Provider legal entity)`, a description, `Personal Data processed: …`, `Place of processing: <country> – Privacy Policy – Opt Out`.
- **Categories of personal data:** the platform uses a fixed vocabulary that is reused across services. Seen in public output: *Cookies / Trackers* ("Cookies" was renamed "Trackers" in newer versions), *Usage Data*, *number of Users*, *session statistics*, *email address*, *first name*, *last name*, *phone number*, *billing address*, *payment info*, *password*, *username*, *Data communicated while using the service*, and the fallback *various types of Data as specified in the privacy policy of the service*. The policy also opens with a summary, "Types of Data collected", built from all selected services. Newer policies add CCPA/US-state "categories of personal information" mappings.
- **Providers carry the legal entity and region.** For example, the same product appears as "Google Analytics (Google Inc.)" in older policies and "(Google Ireland Limited)" or "(Google LLC)" in newer ones, and Stripe as "(Stripe Technology Europe Ltd)". In practice the catalogue keeps variants per provider entity and data region.

#### Macro areas (purposes) observed with example services

Collected from documents the platform generated and from its help pages. These are only the purposes that public samples happened to show; the full list of more than 40 purposes is not public (see below).

| Macro area (purpose heading) | Example services seen |
|---|---|
| Analytics | Google Analytics / Google Analytics 4, PostHog, Mixpanel, KISSmetrics, Hotjar Form Analysis & Conversion Funnels, Facebook Ads conversion tracking (Meta pixel), Google Ads conversion tracking, LinkedIn conversion tracking, HubSpot Analytics, "Analytics collected directly" |
| Heat mapping and session recording | Hotjar Heat Maps & Recordings, Crazy Egg, Inspectlet. Microsoft Clarity is catalogued, and the platform has a dedicated help page on it ("Microsoft Clarity and the GDPR – How to be compliant"; data: Usage Data, Trackers, country, device information, session duration), but the public snippet did not reliably show its section heading. |
| Managing contacts and sending messages (email marketing) | Mailchimp, MailerLite, Mailgun, Postmark, Drip, ActiveCampaign, HubSpot Email |
| Managing landing and invitation pages | MailerLite Landing Pages |
| Contacting the User | Contact form, Mailing list or newsletter, Phone contact (all "this Website/Application") |
| Interaction with live chat platforms | Freshchat Widget, LiveChat Widget, Crisp Widget, Intercom |
| Managing support and contact requests | Intercom (moved here in a 2025 changelog entry) |
| User database management | HubSpot CRM, Intercom, FullContact |
| Displaying content from external platforms | Google Fonts, Google Maps widget, YouTube video widget, Gravatar, GitHub button and social widgets, MyFonts |
| Interaction with external social networks and platforms | Facebook Like button and social widgets, Twitter Tweet button, AddThis, Instagram widget |
| Content commenting | Disqus |
| Advertising | Google AdSense, Microsoft Advertising, IAB TCF vendors (a batch was added in a changelog entry) |
| Remarketing and behavioral targeting | Google Ads Remarketing, Facebook Custom Audience / Remarketing, LinkedIn Website Retargeting, Twitter Tailored Audiences, AdRoll, Reddit Ad Targeting |
| Content performance and features testing (A/B testing) | Google Website Optimizer, Optimizely |
| Handling payments | Stripe, PayPal, Braintree, WorldPay, Sage Pay, Mollie, iDEAL |
| Hosting and backend infrastructure | AWS, DigitalOcean, Linode, GitHub Pages |
| Traffic optimization and distribution | Cloudflare |
| SPAM protection / SPAM and bots protection | Google reCAPTCHA, Wordfence |
| Infrastructure monitoring | Pingdom, Rollbar |
| Registration and authentication | GitHub OAuth, social logins |
| Registration and authentication provided directly by this Website | Direct registration |
| Access to third-party accounts | Access to the Facebook account |
| Handling activities related to productivity | Google Workspace |
| Tag Management | Google Tag Manager |

Rows with thin evidence (*Infrastructure monitoring*, *Tag Management*, Optimizely under A/B testing, *Access to third-party accounts*) rest on one or two samples or on search excerpts; verify them with account access.

Further purpose headings appear in the platform's help or changelog, but no service examples were captured for them: *Backup saving and management*, *Location-based interactions*, *Device permissions for Personal Data access*, *Managing data collection and online surveys*, *Commercial affiliation*, *Beta Testing*, *Social features*, *Webinars / web conferencing*, *Appointment scheduling*.

**Messaging (WhatsApp).** No public sample showed a WhatsApp clause with its heading. From the catalogue's design it would most likely fall under *Contacting the User*, *Interaction with live chat platforms* or *Managing contacts and sending messages*. **Unverified:** this needs account access to confirm.

#### Purposes rolled up into consent categories

Sources: reference platform: "Cookie Solution advanced configuration" (`perPurposeConsent`, `purposes`); WordPress plugin source (cookie-blocking class).

| id | Banner label | Example services mapped in the public plugin source |
|---|---|---|
| 1 | Necessary (strictly necessary) | none blocked; hosting, backup, SPAM protection purposes |
| 2 | Functionality (basic interactions & functionalities) | Google APIs, Google Custom Search, Freshchat, UserVoice, Olark, PayPal checkout |
| 3 | Experience (experience enhancement) | YouTube, Vimeo, Google Maps, Twitter/X widgets, LinkedIn, Pinterest, Disqus, CodePen, AddThis, Facebook Like iframes |
| 4 | Measurement (analytics) | Google Analytics, gtag, Segment, Mixpanel, KISSmetrics, Optimizely, Pingdom RUM, ShareThis, comScore |
| 5 | Marketing (targeting & advertising) | AdSense, Google Publisher Tag, Google Ads conversion, Criteo, Outbrain, AdRoll, Cxense, Twitter ads, Meta pixel (`connect.facebook.net`) |

The banner shows a toggle per category ("Categories shown in the consent modal are automatically detected and passed from your cookie policy"). The categories can be overridden with the `purposes` option. Blocked scripts are tagged with a `data-…-purposes="<ids>"` attribute so they can be re-enabled per category. The public plugin matches scripts with a **static substring-to-category map** (script URLs and iframe hosts). Newer "automatic blocking" builds that map from the site's own cookie-policy classification and the scan results.

### 2. Site scanner and cookie scanner

Sources: reference platform: "How to use the Site Scanner from within the Generator"; "Cookie Scanner: find out what cookies are running on your site"; "How to Identify the Cookies Your Site Installs in Browsers"; "Prior Blocking of Cookies: Automatic Blocking"; support article "Scanner results are incorrect"; WordPress plugin source (scanner service).

- **Mechanism:** the scan runs on the platform's servers. A client sends the site URL plus a flag to also detect existing legal documents. The scan is asynchronous: the client starts it, then polls for progress until it reports "completed". Scans are triggered on install, by the user, and on a schedule ("periodic scans … alerts you if it detects something that should be added").
- **What it detects:** "cookies, services, scripts, iframes and other tracking methods". Each detection is matched to catalogue services. The plugin also turns the results into an overall compliance percentage based on which documents are active: privacy policy, cookie banner, consent database and terms.
- **Classification of findings:** *Perfect matches* (identified with high confidence, ready to add), *Suggestions* (may be present; the user confirms) and *Ignored* (the user hides them with an eye icon; they are then left out of the policy, the scan emails and the compliance report, and can be restored).
- **Cookie scanner:** produces a report of each cookie's name, value, expiration date "and more". The details of identified cookies are inserted into the cookie policy automatically.
- **Documented limitations:** it only sees technologies integrated directly into the scanned pages. Scripts injected by widgets can make results misleading. It "can find services that have been uninstalled for a while due to remains of scripts or cookies". It is "a one-size-fits-all tool that isn't specifically tuned to your domain". The platform still says some processing, such as sending newsletters, must be added by hand because a crawler cannot see it.

### 3. Consent features relevant to this package

Sources: reference platform: WordPress plugin readme; "Cookie Solution advanced configuration"; "Prior Blocking of Cookies: Automatic Blocking".

- Prior blocking of scripts and iframes until consent, then **asynchronous re-activation without a page reload**.
- Per-category consent (`perPurposeConsent`), the five categories above, and custom script/iframe entry for anything the catalogue misses.
- Google Consent Mode (basic and advanced), IAB TCF, geolocation that applies prior blocking only to EU visitors, a CCPA/CPRA "Do Not Sell or Share" notice, AMP support, and bot detection that serves crawlers a clean page.
- A "cookie database" that fills in cookie lifetimes automatically.
- A **Consent Database** for form consents. Each record holds a user id, timestamp, proof, the consenting action and the legal documents shown at the time, through a REST API and a JS SDK.

### 4. How legal documents update when a service is added

Sources: reference platform: "How to Add Services to Your Privacy Policy"; "How to connect [the platform] to Claude and ChatGPT"; "Privacy and Cookie Policy Generator – Legal Changelog"; WordPress plugin readme.

1. A service is added by search, from a scanner match, as a custom service, or, more recently, through the platform's **MCP connector** (remote MCP with OAuth, about 28 tools, including one that adds a catalogue service to a site's policy; it needs an active policy first).
2. The clause is placed under its purpose heading. The privacy policy's "Types of Data collected" summary, the cookie policy (if the service uses trackers) and the banner's category list are all regenerated from the same selection.
3. Documents are **hosted and embedded** (script, iframe or white-label), so the change is live everywhere immediately. Changing a service's category in the cookie-policy generator updates the cookie policy automatically.
4. Legal text written by the platform is updated centrally when the law or a provider changes. The public changelog lists, by week, new services and updated clauses (e.g. 2025: Reddit Ad Targeting, Google GeoChart, Wordfence, WebinarGeek, Mollie, iDEAL, Intercom moved to support and contact requests).
5. Documents start as **drafts** until the owner's details are confirmed. Confirming the owner finalises them, and the connector notes that this cannot be undone.

### 5. Implications for *detect* and *align*

- Model the package catalogue on the platform's per-service record: `name`, `provider` (legal entity), `purpose` (macro area), `consent_category` (1-5 or this package's equivalent), `data_categories[]` (controlled vocabulary), `place_of_processing`, `privacy_policy_url`, `opt_out_url`, `cookies[]` (name, duration), and the evidence used for detection (script hosts, iframe hosts, cookie names, Composer/npm package names).
- *detect* can do better than the platform's crawler because it reads source code. It can see server-side integrations such as Mailchimp or Stripe SDKs, outbound HTTP calls and mail drivers, which a crawler cannot. It should still keep the three-band report and a persistent "ignored" list so dismissed findings do not come back.
- *align* should generate all derived artefacts from the single catalogue entry, like the platform does: the cookie declaration, the script-registry/blocking entry and the legal-page paragraph under the right purpose heading. It should also update the "Types of Data collected" summary.

## Not visible publicly

- **The full service catalogue** (the 1,900-2,000+ clauses) and the **complete list of 40+ purposes**. They only appear inside the generator behind login. The table above was rebuilt from published policies and help pages, so it is a sample, not the whole catalogue.
- **The exact per-service field schema** behind the generator, including any internal ids, per-service tracker/cookie lists, legal-basis fields and CCPA category mappings, and **the official mapping from each purpose to the five consent categories**. Only the old static mapping in the open-source WordPress plugin is public.
- **The scanner's matching rules** (signatures, confidence thresholds, crawl depth) and the format of its results. The scan API is internal.
- **Confirmed macro areas for some requested examples:** WhatsApp (messaging) and the exact heading for Microsoft Clarity were not seen in any public sample.
- **The full MCP connector tool list and schemas.** Public pages give the tool count (about 28) but list only some of the tools.
- The platform's own help pages refused automated fetching during this research (HTTP 503). The facts above come from search-indexed excerpts of those pages, the platform's open-source WordPress plugin, and documents it generated that third parties published.

The user can provide account access to the platform. With it, the full catalogue, the purpose list, the custom-service form fields and real scanner output can be captured to fill these gaps.
