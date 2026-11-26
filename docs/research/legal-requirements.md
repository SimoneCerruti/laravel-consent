# Legal requirements for consent banners

Research for issue #4. Question: which legal requirements constrain the banner and the stored choice for an EU site, with the Italian DPA (Garante) guidance in mind, and which of them are binding versus good practice?

> **Not legal advice.** This is engineering research to shape package defaults. It summarises primary sources as read on 2026-11-26. Site owners remain responsible for their own compliance and should check with counsel and their national DPA.

## Answer

Weight labels used below:

- **Law**: statute text (ePrivacy Directive Art. 5(3), GDPR, the Italian Privacy Code art. 122 that transposes Art. 5(3)).
- **DPA guidance**: an official interpretation that regulators enforce (Garante 2021 guidelines, EDPB guidelines). It is not statute, but in practice a site that departs from it should expect enforcement.
- **Taskforce minimum**: the EDPB Cookie Banner Taskforce report. Its own disclaimer calls it a "minimum threshold", a common denominator of DPAs, and says each case is assessed individually.
- **Good practice / proposal**: recommendations or draft legislation, not binding today.

| Topic | Requirement | Weight | Consequence for the package |
|---|---|---|---|
| Prior consent | Non-exempt storage or access on the device needs prior consent. Only transmission or "strictly necessary" storage for a service the user explicitly asked for is exempt. This covers cookies, local storage, pixels and JS-triggered requests, not just cookies. | Law (ePD 5(3)); DPA guidance (EDPB 2/2023 §3.1) | Nothing non-necessary renders or loads before a choice. Keep the server-side `@consent` gate and the JS registry. Make `necessary` mean "strictly necessary" in the docs, not just "important". |
| Close with "X" | Closing the banner with the X keeps the default settings, so no non-technical cookies are set. It is not consent. | DPA guidance (Garante §7.1(i)) | An X (or Escape) on the first layer records a **reject**, not "no decision". The Garante also asks that the banner say so. This changes §3.12 of IMPLEMENTATION.md, which allows Escape "only after a choice exists". |
| Reject on the first layer | A reject option must sit next to accept. Leaving reject out of every layer that has an accept button is an infringement for a vast majority of DPAs. A reject *link* buried in body text, or placed outside the banner, is not valid. | Taskforce minimum (§3 paras 8, §5 para 14); DPA guidance (Garante §7.1) | The first layer has three controls: Accept all, Reject all, Settings. Reject is a `<button>` inside the banner, never a text link. |
| Equal prominence | Commands and text of equal size, emphasis and colour, equally easy to see and use. EU level: no fixed colour standard, case-by-case review; unreadable contrast on the reject button is manifestly misleading. | DPA guidance (Garante §6.2); Taskforce minimum (§6 paras 17–18) | The shipped views give Accept and Reject the same component and classes. The payload carries no "primary" hint (already planned). The README states the rule for custom UIs. |
| No pre-ticked boxes | Pre-ticked boxes, silence or inactivity are not consent. | Law (GDPR recital 32); DPA guidance (EDPB 05/2020 paras 79, 81); Taskforce minimum (§4 para 10) | Every optional category defaults to `false` and cannot be configured to `true`. The config `default` key should be dropped for non-necessary categories, or validated to `false`. |
| Scrolling / browsing | Scrolling, swiping or continued browsing is never consent. | DPA guidance (EDPB 05/2020 para 86; Garante §6.1) | No "implied consent" mode, no auto-accept on scroll or navigation. Do not add one. |
| Granularity | Consent per purpose. No bundle that forces an all-or-nothing choice. | Law (GDPR art. 4(11), 7(2), recital 43); DPA guidance (EDPB 05/2020 paras 42–44; Garante §7.1) | Per-category toggles on the second layer (already planned). Categories must match real purposes. |
| Cookie wall | Making access conditional on consent is not freely given consent. | DPA guidance (EDPB 05/2020 paras 39–41); Garante §6.1 (unlawful unless an equivalent alternative exists, case by case) | The banner must not block content. If a modal with `aria-modal` is used, the page stays usable after Reject. Ship no "wall" mode. |
| Re-prompt after reject | After a refusal, do not ask again for at least 6 months, unless the processing conditions change significantly (for example new third parties) or the site cannot know a choice exists (cookies deleted). | DPA guidance (Garante §6.2, §7.1) | The stored choice must last at least 6 months for rejects. The current 180-day cookie meets the floor. A `config('consent.version')` bump is the legitimate "conditions changed" trigger. Validate a minimum of 6 months (about 182 days) if the lifetime becomes configurable. |
| Re-prompt after accept | No fixed legal lifetime for consent. A substantial change in processing invalidates it. Refreshing at appropriate intervals is a best practice. | Law silent; DPA guidance (EDPB 05/2020 para 110); Good practice (para 111) | Re-ask when `version` increases (already planned) and when the cookie expires. A 6 to 12 month lifetime is defensible; 6 months matches the Garante reject interval. |
| Storing the choice | The choice may be stored in a technical cookie, which itself needs no consent. Preference cookies are exempt. | DPA guidance (Garante §6.2/§7.1; Taskforce §8 para 30 citing WP29 Opinion 04/2012) | The `cookie_consent` cookie is `necessary`. List it in the cookie declaration. |
| Proof | The controller must be able to demonstrate consent. Keep records of choices. Record enough to show when, how and what information was shown, but collect no more data than needed. A reference to "the site was configured correctly" is not enough. Keep proof only as long as the processing lasts, then only as needed for legal claims. | Law (GDPR art. 7(1), 5(2)); DPA guidance (EDPB 05/2020 paras 104–108; Garante §6.2/§7.1) | `consent_logs` with decision, categories, `version` and `policy_version` (which identifies the banner text shown) fits. Keep `ip_hash` optional. Tie pruning to processing duration plus a claims window (the default 36 months is a policy choice, not a legal figure). Keep a copy or hash of each banner and policy text by `policy_version`, because the log alone cannot show what was displayed. |
| Proof vs. identifiers on reject | In the Digital Omnibus opinion, the EDPB and EDPS say recording a refusal should use a generic flag common to all refusers, not a unique identifier. | Good practice / proposal context (EDPB-EDPS Joint Opinion 2/2026 para 106) | Open question for the log design: on Reject, store no `subject_id` (or a constant one) in the cookie, and log refusals without a per-visitor ID. Keep the random UUID only for accepted or custom choices, where proof is legally required. |
| Withdrawal | Withdrawal is possible at any time, as easy as giving consent, through the same interface, without detriment. A specific widget cannot be imposed, but an easily accessible solution is mandatory once consent is collected, such as a persistent icon or a link in a visible, standard place. The Garante asks for a footer link such as "rivedi le tue scelte sui cookie". | Law (GDPR art. 7(3)); DPA guidance (EDPB 05/2020 paras 113–116; Garante §7.1); Taskforce minimum (§9 paras 32–35) | Ship `<x-consent::settings-link />` and document putting it in the footer of every page. Reopening the banner and choosing Reject must be one click, like Accept. Withdrawal stops loading scripts on the next page. Document that a reload is needed and that third-party cookies already set should be expired where possible. |
| Legitimate interest | Legitimate interest is never a basis for storage or access under Art. 5(3). The only route without consent is the "strictly necessary" exemption. A controller also cannot fall back on legitimate interest when consent fails. | Law (ePD 5(3) has no LI basis); DPA guidance (Garante §5; Taskforce §7 para 24; EDPB 05/2020 para 123) | Drop the "legitimate interest" script class from the reference design. A script loaded without consent must be classified `necessary` and justified as strictly necessary for a service the user requested. reCAPTCHA-type scripts that read or store device data need either that justification, documented by the site owner, or consent. Do not ship a `legitimate_interest` category. |
| Analytics | First- and third-party analytics may be treated like technical cookies only under strict conditions: aggregate statistics only, IPv4 masked at least in the fourth octet, no combination with other data by the third party. | DPA guidance, Italy only (Garante §7.2) | Keep `analytics` a consent category by default. Optionally document an "exempt analytics" setup for Italian sites that meet §7.2, as the site owner's call, not a package default. |
| Global Privacy Control | No EU instrument currently gives GPC (`Sec-GPC: 1`) legal effect as refusal or objection. GDPR art. 21(5) allows objection "by automated means using technical specifications", but cookie storage is governed by consent under Art. 5(3), not objection. The Digital Omnibus proposal (proposed GDPR art. 88b) would oblige controllers to respect machine-readable choices, with standards still to be written. As of the latest public Council documents (June 2026), it is still being negotiated. | Not binding today; proposal | Keep `respect_gpc` opt-in, but treat the signal as a **refusal** (store reject, do not show the banner), never as consent. Do not treat the absence of GPC as consent. Keep the parser isolated so a future standardised signal can be swapped in. |

## Details

### 1. Scope: ePrivacy Art. 5(3) and GDPR

- **ePrivacy Directive 2002/58/EC Art. 5(3)** (as amended by 2009/136/EC): storing information, or gaining access to information already stored, in a user's terminal equipment "is only allowed on condition that the subscriber or user concerned has given his or her consent, having been provided with clear and comprehensive information". Exemptions: storage or access "for the sole purpose of carrying out the transmission of a communication" or "strictly necessary in order for the provider of an information society service explicitly requested by the subscriber or user to provide the service". Source: https://eur-lex.europa.eu/eli/dir/2002/58/oj (consolidated text). In Italy this is art. 122 of the Privacy Code (D.lgs. 196/2003), quoted in Garante guidelines §5.
- **GDPR art. 4(11)**: consent is "any freely given, specific, informed and unambiguous indication of the data subject's wishes by which he or she, by a statement or by a clear affirmative action, signifies agreement". **Art. 7(1)**: the controller "shall be able to demonstrate" consent. **Art. 7(3)**: withdrawal at any time; "It shall be as easy to withdraw as to give consent." **Recital 32**: "Silence, pre-ticked boxes or inactivity should not therefore constitute consent." Source: https://eur-lex.europa.eu/eli/reg/2016/679/oj. (EUR-Lex blocked automated fetches during this research; these quotations are the well-known official wording and should be checked against the link.)
- **The GDPR consent standard applies to Art. 5(3) consent**: Taskforce report §1 paras 2–3 and §9 para 33 (ePD consent refers to both GDPR art. 4 and art. 7).
- **Not only cookies**: EDPB Guidelines 2/2023 on the technical scope of Art. 5(3) ePD (v2.0, adopted 7 October 2024) apply Art. 5(3) to URL and pixel tracking (§3.1, paras 50–51: distribution of a tracking pixel "does constitute storage, at the very least through the caching mechanism", and for dynamically built pixels the distributed JavaScript is the instruction), to JavaScript that makes the browser send information (para 33), and to local storage once information leaves the device (para 44). Source: https://www.edpb.europa.eu/system/files/2024-10/edpb_guidelines_202302_technical_scope_art_53_eprivacydirective_v2_en_0.pdf
  - Consequence: the script registry must gate pixels, tag managers and any script that reads `localStorage` or device properties, not only cookie setters.

### 2. Italian Garante, "Linee guida cookie e altri strumenti di tracciamento" (10 June 2021)

Doc. web n. 9677876, published in Gazzetta Ufficiale n. 163 of 9 July 2021. Controllers had six months from publication to comply. Source (Italian text, English version on the same page): https://www.garanteprivacy.it/home/docweb/-/docweb-display/docweb/9677876

- **§5, legitimate interest**: "In nessun caso sarà pertanto possibile invocare" the controller's legitimate interest for cookies and tracking tools.
- **§6.1, scrolling and cookie wall**: scrolling down "è inadatto in sé alla raccolta" of consent. It can only be part of a wider process that produces a recordable, documentable choice. A cookie wall is unlawful unless an equivalent service is offered without consent (assessed case by case).
- **§6.2, repeating the request after a refusal**: the choice "dovrà essere debitamente registrata e la prestazione del consenso non più nuovamente sollecitata". The banner may come back only:
  1. "quando mutino significativamente una o più condizioni del trattamento" (for example, the third parties change);
  2. when the site cannot know that a cookie was already stored (for example, the user deleted cookies);
  3. "quando siano trascorsi almeno 6 mesi dalla precedente presentazione del banner".
  §6.2 also requires "comandi e di caratteri di uguali dimensioni, enfasi e colori" that are equally easy to see and use.
- **§7.1, the consent mechanism**:
  - The banner contains an X command; closing with it means "il permanere delle impostazioni di default", so no cookies other than technical ones. The banner must state this.
  - Minimal information, a link to the full notice, an "accept all" command, and access to a granular choice.
  - Consent is only ever an opt-in positive action, never an opt-out.
  - The controller keeps updated records of the choices. Choices may be stored using "appositi cookie tecnici".
  - The opt-out blocks re-proposal "per un periodo di tempo non inferiore a 6 mesi".
  - Changing choices or withdrawing consent goes through an area reached "attraverso un link da posizionarsi nel footer del sito", with wording such as "rivedi le tue scelte sui cookie".
- **§7.2, analytics**: first- and third-party analytics can be treated like technical cookies only if used for aggregate statistics, with masking of "almeno della quarta componente dell'indirizzo" IPv4, and without the third party combining the data with other information.
- **§8.1**: the second-layer notice must be one click away through a footer link on every page.
- The guidelines do not mention GPC, Do Not Track or other browser signals.

### 3. EDPB Guidelines 05/2020 on consent (v1.1, 4 May 2020)

Source: https://www.edpb.europa.eu/system/files/documents/files/file1/edpb_guidelines_202005_consent_en.pdf

- **Paras 39–41, cookie walls**: access "must not be made conditional on the consent" to storage or access in terminal equipment. Example 6a (a script that blocks content until "Accept cookies") is not valid consent.
- **Paras 42–44, granularity**: data subjects "should be free to choose which purpose they accept, rather than having to consent to a bundle".
- **Para 79**: "The use of pre-ticked opt-in boxes is invalid under the GDPR. Silence or inactivity ... as well as merely proceeding with a service cannot be regarded as an active indication of choice." Para 81 repeats this for opt-out constructions.
- **Para 86 (Example 16)**: "scrolling or swiping through a webpage or similar user activity will not under any circumstances satisfy the requirement of a clear and affirmative action".
- **Paras 87–89**: click fatigue. Browser-setting consent is mentioned as a way to address it, but such settings must meet GDPR conditions (granular per purpose, naming controllers).
- **Paras 104–108, demonstrating consent**: the burden of proof is on the controller. Proof "should not in itself lead to excessive amounts of additional data processing". Keep proof while the processing lasts, then only as strictly necessary for legal obligations or claims (para 107). Online, "a controller could retain information on the session in which consent was expressed, together with documentation of the consent workflow at the time of the session, and a copy of the information that was presented"; "It would not be sufficient to merely refer to a correct configuration of the respective website" (para 108).
- **Para 110**: no time limit for consent in the GDPR. If processing changes considerably, the original consent is no longer valid.
- **Para 111**: "The EDPB recommends as a best practice that consent should be refreshed at appropriate intervals." (good practice)
- **Paras 113–116, withdrawal**: as easy as giving consent and at any time. When consent was given with one click, withdrawal must be equally easy and possible "via the same electronic interface", "without detriment".
- **Paras 121–123**: a controller "cannot swap from consent to other lawful bases", for example by retrospectively using legitimate interest when consent was not valid.

### 4. EDPB Cookie Banner Taskforce report (adopted 17 January 2023)

Source: https://www.edpb.europa.eu/system/files/2023-01/edpb_20230118_report_cookie_banner_taskforce_en.pdf

**Disclaimer** (binding weight): the positions "reflect the common denominator agreed by the SAs" and "a minimum threshold". They do not prejudge case-by-case analysis, and national requirements and guidance "remain fully applicable".

- **Para 7**: by default, no consent-requiring cookies before consent; consent needs a positive action.
- **Para 8 (Type A, no reject on first layer)**: a vast majority of authorities consider the "absence of refuse/reject/not consent options on any layer with a consent button" an infringement. A few authorities disagreed because Art. 5(3) does not explicitly mention a reject option.
- **Para 10 (Type B)**: pre-ticked boxes do not give valid consent.
- **Paras 13–14 (Type C, link design)**: banners must not give the impression consent is needed to access content. A reject that is only a link in body text, or outside the banner frame, without sufficient visual support, is not valid.
- **Paras 17–19 (Types D/E, colours and contrast)**: no general colour or contrast standard can be imposed; case by case. Reject text with contrast so low it is unreadable is manifestly misleading.
- **Para 24 (Type H)**: "the legal basis for the placement/reading of cookies pursuant to Article 5 (3) cannot be the legitimate interests of the controller". Non-compliance with 5(3) also taints subsequent processing.
- **Paras 26–30 (Type I)**: site owners must be able to demonstrate why cookies listed as "essential" are essential. Preference cookies are essential (WP29 Opinion 04/2012).
- **Paras 31–35 (Type K, withdrawal)**: an easily accessible way to withdraw is mandatory (possible to withdraw, at any time, as easy as giving). A hovering icon cannot be imposed; an icon or a link "placed on a visible and standardized place" are examples.

### 5. Global Privacy Control and machine-readable signals

- **No binding EU rule today.** No EDPB guideline or EU statute names GPC. The Garante 2021 guidelines do not address browser signals.
- **GDPR art. 21(5)** (law): in information society services "the data subject may exercise his or her right to object by automated means using technical specifications". This concerns the right to object (for example to processing based on legitimate interest or direct marketing). It does not make a signal into consent, and Art. 5(3) storage needs consent rather than objection anyway.
- **EDPB 05/2020 para 89** (DPA guidance): consent through browser settings is possible only if the settings meet GDPR conditions. A generic signal cannot express *consent* that is specific and informed, but nothing prevents a site from honouring it as a refusal.
- **Digital Omnibus proposal** (interinstitutional file 2025/0360(COD), proposed GDPR art. 88a for terminal equipment and art. 88b for "automated and machine-readable indications" of choices). This is a proposal, not law. The EDPB-EDPS Joint Opinion 2/2026 (adopted 10 February 2026) "strongly welcome[s]" proposed art. 88b (para 108). It recalls that standards "should not be configured to consent by default" (para 113). It notes the proposed rule (art. 88a(4)(c)) not to make a new consent request for six months after a refusal (para 106). It recommends a maximum validity period for consent (para 105). It says refusals should be recorded with "generic information, such as a flag or code, which is common to all data subjects who have refused consent", not a unique identifier (para 106). Source: https://www.edpb.europa.eu/system/files/2026-02/edpb_edps_jointopinion_202602_digitalomnibus_en.pdf. Council Presidency compromise documents of May and June 2026 (for example https://data.consilium.europa.eu/doc/document/ST-9773-2026-INIT/en/pdf, marked LIMITE, not read in full here) show the text still under negotiation, including a move to keep cookie rules in the ePrivacy Directive. No adopted text was found.
- **Consequence**: honouring `Sec-GPC: 1` as "reject all non-necessary" is safe, because it can only reduce processing. Treating it as consent, or treating its absence as consent, is not. Keep the behaviour behind `respect_gpc` and revisit when the Omnibus is adopted and the art. 88b standards exist.

### 6. Changes this implies for IMPLEMENTATION.md

1. §3.12 banner UX: X or Escape on the first layer = Reject, stated in the banner text (Garante §7.1). The reject control is a button inside the banner.
2. §2 / §3.1: optional categories always default to `false`; the "legitimate interest scripts load regardless" pattern goes away (only `necessary`, with a documented justification).
3. §3.4: the cookie lifetime is at least 6 months (current 180 days is fine). A `version` bump is the only early re-prompt, apart from a missing cookie.
4. §3.5: keep a versioned copy of the banner and policy text (`policy_version`) so a log entry shows what was displayed. Reconsider a per-visitor `subject_id` on Reject (generic flag instead). Pruning is tied to processing duration plus a claims window.
5. §3.9: GPC = refusal only, opt-in, isolated so it can follow a future EU standard.
6. §3.12: document the settings link for every page footer as required, not optional.

## Sources

- ePrivacy Directive 2002/58/EC, consolidated: https://eur-lex.europa.eu/eli/dir/2002/58/oj
- GDPR (EU) 2016/679: https://eur-lex.europa.eu/eli/reg/2016/679/oj
- Garante, Linee guida cookie e altri strumenti di tracciamento, 10 June 2021, doc. web 9677876: https://www.garanteprivacy.it/home/docweb/-/docweb-display/docweb/9677876
- EDPB Guidelines 05/2020 on consent v1.1: https://www.edpb.europa.eu/system/files/documents/files/file1/edpb_guidelines_202005_consent_en.pdf
- EDPB Cookie Banner Taskforce report, 17 January 2023: https://www.edpb.europa.eu/system/files/2023-01/edpb_20230118_report_cookie_banner_taskforce_en.pdf
- EDPB Guidelines 2/2023 on the technical scope of Art. 5(3) ePD v2.0: https://www.edpb.europa.eu/system/files/2024-10/edpb_guidelines_202302_technical_scope_art_53_eprivacydirective_v2_en_0.pdf
- EDPB-EDPS Joint Opinion 2/2026 on the Digital Omnibus: https://www.edpb.europa.eu/system/files/2026-02/edpb_edps_jointopinion_202602_digitalomnibus_en.pdf
- Council of the EU, Presidency compromise, 1 June 2026 (ST 9773/26): https://data.consilium.europa.eu/doc/document/ST-9773-2026-INIT/en/pdf
