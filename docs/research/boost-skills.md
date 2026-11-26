# How Laravel Boost ships package skills

Research for issue #26 (map #2, milestone 7: AI compliance skills; milestone 8: usage skill).
Sources checked on 2026-11-26: `laravel/boost` at commit `c1c27ed` (just after tag `v2.10.4`), `laravel/docs` `13.x/boost.md`, `laravel/package-skeleton` at `70251f1`, the Agent Skills spec, and real packages (`mongodb/laravel-mongodb`, `spatie/laravel-activitylog`).

## Answer

- **Discovery is by convention only.** Boost looks for `vendor/<package>/resources/boost/skills/*/` in every **direct** Composer (or npm) dependency that is not a first-party Laravel package. You do not need a `composer.json` key, service provider hook or dependency on `laravel/boost`. Transitive dependencies are skipped on purpose.
- **One folder per skill.** Each subfolder of `resources/boost/skills/` holding a `SKILL.md` (or `SKILL.blade.php`) with YAML frontmatter that sets both `name` and `description` becomes one skill. A package can ship as many skills as it likes, so the two milestone 7 compliance skills and the milestone 8 usage skill can be sibling folders.
- **Install is opt-in per package.** `php artisan boost:install` lists the packages that ship guidelines or skills and the user ticks them. The choice is saved in `boost.json` (`packages`). Boost then **copies** each skill folder (every file in it, with `.md` files reformatted) into each selected agent's skills directory as `<agent skills path>/<frontmatter name>/`. Examples: `.claude/skills/` for Claude Code and `.agents/skills/` for Codex, OpenCode, Amp and Zed.
- **Updates are copies, with no versioning.** `boost:update` (usually in `post-update-cmd`) wipes and rewrites each tracked skill folder from the current vendor copy. It removes skills that no longer exist, and in an interactive run it offers packages it has not seen before. Third-party skills have no version field and no per-major-version folders, so whatever is in `vendor/` wins. The host app should not edit installed copies. It overrides a skill by name in `.ai/skills/<name>/` or turns it off with `boost.skills.exclude`.
- **Boost itself has few limits.** It requires only `name` and `description`, and the name must be path-safe. Follow the Agent Skills spec anyway: `name` up to 64 characters in lowercase-hyphen form, matching the folder name; `description` up to 1024 characters; `SKILL.md` under 500 lines (under about 5k tokens); detail goes in `references/`, `scripts/` or `assets/`, which Boost copies along. Ship one short `resources/boost/guidelines/core.blade.php` as well. It is always loaded and points the agent at the skills.
- **Packaging gotcha.** `resources/boost/**` must be in the dist archive. Our `.gitattributes` keeps it, because it does not export-ignore `resources/` or `*.md`. Keep it that way.

## Details

### 1. Discovery

- `ThirdPartyPackage::boostDirectories()` takes every PHP and JS package from Roster that is `isDirect()` and not `PackageRegistry::isFirstParty()`, then maps it to `resources/boost/{guidelines|skills}`. Source: [`src/Install/ThirdPartyPackage.php` L63-79](https://github.com/laravel/boost/blob/c1c27ed/src/Install/ThirdPartyPackage.php#L63-L79). A code comment says: "Transitive dependencies are excluded so an indirect package cannot inject guidelines."
- The path is `<package path>/resources/boost/<subpath>`, and it is only used when it is a directory. Source: [`src/Support/PackageRegistry.php` L84-93](https://github.com/laravel/boost/blob/c1c27ed/src/Support/PackageRegistry.php#L84-L93).
- Inside it, `SkillComposer::discoverSkillsFromDirectory()` globs the **immediate subdirectories** and parses each one. Results are keyed by frontmatter `name`, not by folder name. Source: [`src/Install/SkillComposer.php` L205-215](https://github.com/laravel/boost/blob/c1c27ed/src/Install/SkillComposer.php#L205-L215).
- The skill file is `SKILL.blade.php` or `SKILL.md`, and Blade wins when both exist. Source: [L258-269](https://github.com/laravel/boost/blob/c1c27ed/src/Install/SkillComposer.php#L258-L269).
- You need no Composer metadata. The docs say only "add a `resources/boost/skills/{skill-name}/SKILL.md` file to your package" ([laravel/docs 13.x boost.md, "Third-Party Package Skills" L293-298](https://github.com/laravel/docs/blob/13.x/boost.md#third-party-package-skills)). Consequence for us: a host app that installs `laravel-consent` only as a transitive dependency does not get our skills.

### 2. SKILL.md format and frontmatter

- `parseSkillFrontmatter()` strips leading HTML comments, then needs a `---` … `---` YAML mapping. Source: [`SkillComposer.php` L276-299](https://github.com/laravel/boost/blob/c1c27ed/src/Install/SkillComposer.php#L276-L299).
- If `name` or `description` is missing, the skill is skipped. If there is other frontmatter, the skill is also recorded as a parse failure, which `boost:install` and `boost:update` report. Source: [L241-247](https://github.com/laravel/boost/blob/c1c27ed/src/Install/SkillComposer.php#L241-L247).
- Boost ignores every other key. `license` and `metadata.author` are the house style used by Boost's own skills, the skeleton and Spatie. MongoDB uses `metadata.version`.
- `SkillWriter::isValidSkillName()` only rejects `..`, `/`, `\`, NUL and names made of nothing but dots or whitespace. Source: [`src/Install/SkillWriter.php` L345-353](https://github.com/laravel/boost/blob/c1c27ed/src/Install/SkillWriter.php#L345-L353).
- The tighter limits come from the Agent Skills spec, which Boost's docs adopt explicitly. The docs say skills "support the Agent Skills format… must include required frontmatter (`name` and `description`) and can optionally include scripts, templates, and reference materials." The spec ([agentskills.io/specification](https://agentskills.io/specification)) sets these rules:
  - `name`: 1-64 characters, `a-z0-9-`, no leading or trailing hyphen, and it must match the parent directory.
  - `description`: 1-1024 characters, saying what the skill does and when to use it.
  - Progressive disclosure: about 100 tokens of metadata are always loaded, the body should stay under 5000 tokens, and `SKILL.md` should stay under 500 lines.
- `SKILL.blade.php` is rendered through Boost's Blade pipeline with a `$assist` (`GuidelineAssist`) variable, which supports conditionals per host app. Boost's own `.ai/livewire/4/skill/livewire-development/SKILL.blade.php` uses this. Plain `SKILL.md` is the safer choice for a third-party package: `InstallCommand` L163-173 warns about vendor Blade files "built for an older Boost version".

### 3. Installation into the host app

- **`boost:install`.** `selectThirdPartyPackages()` shows a multiselect labelled "Which third-party AI guidelines/skills would you like to install?", listing each package with "(guidelines, skills)". In a non-interactive run it reuses the `packages` already saved in `boost.json`. Source: [`src/Console/InstallCommand.php` L263-288](https://github.com/laravel/boost/blob/c1c27ed/src/Console/InstallCommand.php#L263-L288). The selection is passed as `GuidelineConfig::aiGuidelines` and filters third-party skills ([`SkillComposer.php` L126-139](https://github.com/laravel/boost/blob/c1c27ed/src/Install/SkillComposer.php#L126-L139)).
- **Merge order.** Precedence runs from Boost core, integration and first-party skills, to third-party skills, then `config('boost.skills.exclude')`, then user skills from `.ai/skills/*` and `.ai/<pkg>/skill`. Source: [`SkillComposer.php` L47-62](https://github.com/laravel/boost/blob/c1c27ed/src/Install/SkillComposer.php#L47-L62). Because skills are keyed by name, a third-party skill whose name matches a Boost skill replaces it. A user skill with the same name replaces ours. Use a package prefix such as `laravel-consent-*`.
- **Writing.** `installSkills()` syncs skills to every selected agent that implements `SupportsSkills` ([`InstallCommand.php` L472-510](https://github.com/laravel/boost/blob/c1c27ed/src/Console/InstallCommand.php#L472-L510)). `SkillWriter::write()` targets `base_path(<agent skillsPath>/<skill name>)`. A non-custom (vendor) skill is **copied**, with the target deleted first. Custom `.ai/skills` skills are symlinked when possible. Source: [`SkillWriter.php` L33-89](https://github.com/laravel/boost/blob/c1c27ed/src/Install/SkillWriter.php#L33-L89).
- **Default agent skill directories.** Each one can be changed with `boost.agents.<agent>.skills_path` (see `skillsPath()` in `src/Install/Agents/*.php`, for example [`ClaudeCode.php` L66-69](https://github.com/laravel/boost/blob/c1c27ed/src/Install/Agents/ClaudeCode.php#L66-L69)):

  | Agent | Directory |
  | --- | --- |
  | Claude Code | `.claude/skills` |
  | Codex, OpenCode, Amp, Zed, Antigravity | `.agents/skills` |
  | Copilot | `.github/skills` |
  | Cursor | `.cursor/skills` |
  | Junie | `.junie/skills` |
  | Kiro | `.kiro/skills` |
  | Factory | `.factory/skills` |
  | Grok Build | `.grok/skills` |
  | Pi | `.pi/skills` |

- **Tracking.** The names of installed skills are saved in `boost.json` under `skills`. The selected packages are saved under `packages` (`src/Support/Config.php` L26-67).

### 4. Updates and versioning

- **What `boost:update` does.** It reruns `boost:install --no-interaction --guidelines --skills` silently. Before that, unless `--no-discover` is passed, it offers new third-party packages, but only in an interactive run that is not a Composer script, which it detects through `COMPOSER_DEV_MODE`. Source: [`src/Console/UpdateCommand.php` L29-115](https://github.com/laravel/boost/blob/c1c27ed/src/Console/UpdateCommand.php#L29-L115). The docs recommend `"post-update-cmd": ["@php artisan boost:update --ansi"]` ([boost.md L93-118](https://github.com/laravel/docs/blob/13.x/boost.md#keeping-boost-resources-updated)).
- **Stale skills.** `SkillWriter::sync()` deletes skills that were tracked before and are no longer discovered, then rewrites all current ones. Source: [`SkillWriter.php` L118-124](https://github.com/laravel/boost/blob/c1c27ed/src/Install/SkillWriter.php#L118-L124). Renaming or removing one of our skills therefore cleans up after itself on the next update.
- **No version selection for third-party skills.** Boost-bundled skills can live in `.ai/<pkg>/<major>/skill/`, picked by the installed major version ([`SkillComposer.php` L189-200](https://github.com/laravel/boost/blob/c1c27ed/src/Install/SkillComposer.php#L189-L200)). Vendor `resources/boost/skills` is read flat. Our skill content is versioned only by the package release in `vendor/`. A `metadata.version` field is informational.
- **What the host app can change.** The docs suggest it can gitignore the generated files, because `boost:install` and `boost:update` regenerate them (boost.md L56). Hand edits to installed copies are lost. To change a skill, the host app copies it to `.ai/skills/<same-name>/` ("Overriding Skills", boost.md L286-291). To drop it, it adds the name to `config/boost.php` → `skills.exclude` ([config L50-64](https://github.com/laravel/boost/blob/c1c27ed/config/boost.php#L50-L64)).

### 5. Guidelines vs skills

- **Guidelines.** Package guidelines come from `resources/boost/guidelines/**/*.{blade.php,md}`, which excludes `skill` folders. They are merged into the always-loaded guideline file (`CLAUDE.md`, `AGENTS.md` and so on) inside `<laravel-boost-guidelines>`. Source: [`src/Install/GuidelineComposer.php` L268-330](https://github.com/laravel/boost/blob/c1c27ed/src/Install/GuidelineComposer.php#L268-L330). The docs convention is `resources/boost/guidelines/core.blade.php` (boost.md L219-241).
- **Skills.** They load on demand. The docs table (boost.md L322-340) puts it this way: guidelines are "upfront, always present / broad", skills are "on-demand, when relevant / focused". When skills are enabled, Boost's foundation guideline tells agents "You MUST activate the relevant skill" ([`.ai/foundation.blade.php` L18-21](https://github.com/laravel/boost/blob/c1c27ed/.ai/foundation.blade.php#L18-L21)).
- **The pattern real packages use.** Spatie activitylog ships a 5-line `guidelines/core.blade.php` plus one skill. Its guideline says "Always activate the `activitylog-development` skill when…" and "If the skill is unavailable, read `vendor/spatie/laravel-activitylog/resources/boost/skills/activitylog-development/SKILL.md`." MongoDB likewise ships `guidelines/core.blade.php` plus one skill. We should copy this: a tiny guideline that routes to our skills and includes the vendor fallback path.

### 6. Size, structure and extra files

- `copyDirectory()` copies **every file** in the skill folder recursively, dotfiles included, so `references/*.md`, data files (JSON, YAML, CSV) and scripts all arrive. Source: [`SkillWriter.php` L202-266](https://github.com/laravel/boost/blob/c1c27ed/src/Install/SkillWriter.php#L202-L266). How each file type is handled:
  - `.md` files are trimmed and run through `MarkdownFormatter::format()`.
  - `.blade.php` files are rendered to `.md`.
  - Other files are copied byte for byte, keeping their permissions.
- Relative links such as `references/x.md` keep working because the folder layout is preserved.
- Real examples:
  - `mongodb/laravel-mongodb` (branch 5.x): a 179-line, about 9 KB `SKILL.md` with 14 files in `references/`. Its `SKILL.md` has a table of "load `references/…` when…" entries.
  - `spatie/laravel-activitylog`: a single 168-line `SKILL.md`.
  - `Laravel-Backpack/CRUD`: uses a `rules/` subfolder.
- Boost enforces no size limit. Follow the spec budget: a body under 5k tokens and under 500 lines, with detail moved to `references/`. The skeleton's `package-generate-skill` gives the same advice: "Keep the skill small enough for an agent to load and apply quickly."
- **Dist archive.** MongoDB's `.gitattributes` export-ignores `*.md` and has to add `/resources/**/*.md -export-ignore` back, or its skills would disappear from Composer dist installs. Our `.gitattributes` export-ignores only dev paths (`/docs`, `/.agents`, `/.claude`, etc.), so `resources/boost` ships as it is.

### 7. Package-skeleton starting point

- `laravel/package-skeleton` ships `resources/boost/skills/skeleton/SKILL.md` with `name: skeleton-development`. Its `configure.php` L1315 renames that folder to `resources/boost/skills/{slug}-development`. That is where our `resources/boost/skills/laravel-consent-development/SKILL.md` (frontmatter `name: laravel-consent-development`) came from. It is still placeholder text.
- `.agents/skills/package-generate-skill/SKILL.md` is a maintainer-side skill. It is not shipped, because `/.agents` is export-ignored. It describes the update workflow: rebuild the Boost skill from the implementation, not from the docs alone; list the public API only; keep the structure "description, primary goal, workflow, references, examples, anti-patterns". The globs it already uses (`resources/boost/skills/*/SKILL.md`) cover more than one skill folder.

## Implications for milestones 7 and 8

1. Put each skill in its own folder `resources/boost/skills/<name>/SKILL.md`, with a folder name equal to the frontmatter `name`, all prefixed `laravel-consent-`. For example: `laravel-consent-development` (usage, M8) and two compliance skills (M7).
2. Add `resources/boost/guidelines/core.blade.php`: a few bullets that name each skill, say when to activate it, and give the `vendor/simonecerruti/laravel-consent/resources/boost/skills/...` fallback path.
3. Keep each `SKILL.md` under about 500 lines and 5k tokens, and move legal and reference material into `references/*.md` or data files next to it. Boost copies them as they are.
4. Use plain Markdown, not Blade, unless the content truly has to vary per host app.
5. Document for host apps that they must require the package directly, run `boost:install` and tick `simonecerruti/laravel-consent`, and add `boost:update` to `post-update-cmd`. Note that a new skill appears automatically for apps that already selected the package, because it is filtered per package, not per skill.
6. Add a test, for example arch or Pest, that parses every `resources/boost/skills/*/SKILL.md`. It should check that `name` matches the folder, that `name` and `description` respect the length limits and that the file stays within the line budget.
