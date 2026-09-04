# Changelog

All notable changes to `jonytonet/ptah` are documented here.

Format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).
Versions follow [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

---

## [1.32.0] - 2026-09-04

### Fixed - clicking a page number returned 500 on every listing

Reported as a bug on the menu screen. It was not one: `forge-pagination` is the
package's only pagination, shared by eleven screens, and it wired its buttons to
`$set('page', N)`. `Livewire\WithPagination` declares no public `page` property —
it keeps the state in `public $paginators = []` and exposes `gotoPage()`,
`nextPage()` and `previousPage()`. So the update landed in
`HandleComponents::updateProperty()`, which rejects any property absent from
`getPublicPropertiesDefinedOnSubclass()`, and the Livewire request died with
`PublicPropertyNotFoundException`.

The menu is simply the first listing a fresh install pushes past twenty rows,
because `ptah:menu-sync` fills the table. Everything else paginated was equally
broken on page two and nobody had got there.

`base-crud.blade.php` had been declaring
`wire:target="...,gotoPage,nextPage,previousPage,..."` on its loading indicator
all along — the rest of the package always assumed those names, and the
pagination view was the piece outside the contract.

The view now also reads the page name off the paginator. The permissions
pages/objects screen has two paginated listings and both used the default
`page`, so moving one moved the other; invisible while every click returned 500.

**Nothing in the suite clicked a page button.** The existing pagination tests all
assert markup, and two of them pinned the defective `$set` itself — one asserting
the absence of `aria-current` on an expression that no longer exists in any
form, which would have passed vacuously; it got an anchor. The new test does not
hardcode an expression at all: it reads whatever `wire:click` the shipped view
emits and CALLS it on a real component. Without the fix, four of its five cases
fail.

### Added - the installed ptah version, discreetly

At the foot of the ACL guide (`/ptah-permission-guide`), outside the tabs so it
answers on any of them. It comes from Composer's lock data via
`Ptah\Support\PtahVersion::current()`, not from a constant in the package: a
constant is a second source of truth that goes stale exactly when it matters,
in the commit right after a release. A released install reports the tag
(`1.32.0`); inside this repo, where ptah is the root package, it reports the
branch. Both answer "which ptah is this?", so neither is hidden.

If Composer cannot answer — Composer 1 with no `InstalledVersions`, a package
required by path and absent from the lock — it degrades to `unknown`. A version
label is decoration; it must never be what breaks a page. `PtahVersion::for()`
exists so that branch is reachable from a test rather than asserted by reading
the source.

`current()` is public, for a host that wants the number somewhere else.

### Changed - AI tools are resolved when a message is sent, one at a time, isolated

The registry was handed ready-made objects, assembled inside the container
closure. That closure runs when `AiChatService` is resolved, and `AiChatService`
is resolved in `AiChatWidget::boot()` — a widget that lives in the authenticated
layout, so it is constructed on every page of the application. Two consequences,
both reported from an ERP running twenty-six domain tools:

**Cost.** Every screen built twenty-six objects and their whole dependency
graphs to serve a chat nobody had opened.

**Blast radius.** That construction happened inside the page's render, so one
tool with a DI cycle or a heavy constructor did not break the chat — it returned
500 for the entire application. A tool is host code the package cannot vet; it
must not be able to take the page down.

`registerClass()` now stores the string and nothing else — not even
`class_exists()`, which would trigger the autoloader. Resolution happens in
`getPrismTools()`, at send time, one tool at a time inside its own try/catch: a
class that no longer exists, a class that does not implement the interface, a
constructor that throws, a dependency that cannot be built — each ends as a log
line carrying THE CLASS NAME and one missing capability for that turn. Without
the name in the log, a broken chat in an app with twenty-six tools has
twenty-six suspects. The resolved list is memoised per request.

An `execute()` that throws stops there too: the model receives
`{"error": true, ...}` and can say that one capability is down, instead of the
whole turn dying over a tool it chose to try.

New optional contract `Ptah\Contracts\AiToolSchemaInterface` closes the rest. A
tool that implements it is described from a static `toolSchema()` and constructed
only if the model actually calls it — describing a tool through the instance
methods requires building it, and every turn sends the full schema list to call
at most one or two. Tools without the interface behave exactly as before.

The two built-in tools go in by class name as well: a privileged eager path for
them would be a path the laziness tests do not cover.

The assembly moved from a closure in the provider to
`AiToolRegistry::fromConfig()`, for a reason worth recording. Under Testbench,
`getEnvironmentSetUp()` runs AFTER the package provider's `register()`, so the
module gate reads false, `AiToolRegistry::class` is never bound, and the
container hands back a bare autowired instance with no tools in it. The first
version of these tests passed green while proving nothing; the sanity assertion
is what caught it. A named factory gives the test the exact code the application
runs.

### Tests

2099 -> 2126.

---

## [1.31.1] - 2026-09-04

### Fixed - the AI config panel and the `primary` alert had no colour in dark

Reported as "no contrast in dark mode" on the AI config screen. It was not poor
contrast: it was **no colour at all**.

The panel painted its header, intro and footer with `dark:text-primary-300` /
`dark:text-primary-200`, and its border with `dark:border-primary-400`. Those are
numeric shades of the brand colour, and the package's own `forge.css` declares
that scale while the `app.css` a host gets from `ptah:install` declares only
three names per family — `--color-primary`, `--color-primary-light`,
`--color-primary-dark`. In a host build those utilities generate no rule at all
(grep of the test app's compiled bundle: zero occurrences), so the text fell back
to inheriting its ancestor's colour, which on a dark panel is dark ink.

`forge-alert` had the identical defect in its `primary` variant — which is also
where `info` maps. The other three variants had already been migrated to
`.ptah-c-alert_title` / `_text` and this one was missed, leaving it as the only
one of four depending on a colour the host does not supply. Four alerts in the
package were affected, three of them in the permissions guide.

Both now paint through a class backed by `var(--ptah-*)`, which ships with the
package. Measured afterwards in a browser with the real background stacking: the
corrected variant reads **identically to the already-proven `success`** one —
19.83:1 title and 13.36:1 body in dark, 5.72:1 in light.

An existing guard was pinning the defective state, and its name said so:
`..._to_success_warn_and_danger_only`, with a docblock asserting that "primary
already passes AA". The ratio was right and the conclusion wrong — it had been
computed for `text-primary-300` as declared in `forge.css`, a colour that never
reaches a host's screen. Updated to require all four variants.

`HostThemeScaleDependencyTest` now forbids the whole class of dependency: no
package view may use a numeric shade of a brand family, because the failure mode
is invisible from inside the package, where the scale does exist. Second instance
of this shape, after the error page calling `@vite` on an entry a host need not
have.

### Added - drag the table sideways to scroll it

A wide listing is genuinely hard to scroll horizontally with a mouse: most have
no horizontal wheel and shift+wheel is folklore. Grabbing the table fixes it, and
a grab cursor appears only when the table actually overflows — offering the
affordance on one that fits would promise a gesture that does nothing.

The work was not the scrolling. The table wrapper is already contested: the
`<th>` owns HTML5 drag for column reorder, the resize handle owns `mousedown`,
and the row may own a click for navigation. So the pan arms only for a mouse
(touch and pen scroll natively, and hijacking that would break the page's own
scrolling on a phone), never on an element that already reacts, only after 5 px
of movement — under that it stays a text selection, because copying a value out
of a cell is something people do — and it swallows exactly one click afterwards,
in the capture phase, or dragging a table with `configLinkLinha` would navigate
to whichever record ended up under the cursor.

Verified in a browser with the code extracted from the shipped file rather than
retyped: 3 px does not scroll and keeps the selection, 80 px scrolls exactly
80 px, and `pointerdown` on a `<th>`, on a button, or by touch arms nothing.

**Vertical dragging is deliberately not included.** The wrapper only has
`overflow-x-auto`, so dragging upward would move the window rather than the
table — which fights the page's own scrollbar and, more importantly, is the
gesture people use to select several rows of text. Making it work would mean
giving the listing its own height, which is a product decision rather than an
addition.

Two tooling corrections came out of it. The new rules first used `:is(a, b, c)`,
which this project's golden-fixture parser does not understand — it splits on
commas and recorded meaningless baseline keys (`a|light|cursor`,
`select)|light|cursor`), one of which could later collide with a real selector
and mask a colour change. Rewritten as selector lists. And a `@media (hover:
none)` block made the baseline record `auto` where the correct value is `grab`,
since that parser is not media-aware; removed, because the JS already refuses to
arm for touch and on a touch-only device there is no cursor to correct.

### Tests

2078 -> 2099.

---

## [1.31.0] - 2026-09-04

The backlog of items left open from real ERP use. Four of the five turned out to
be different — or larger — than reported, because each was measured before being
fixed rather than after.

### Fixed - `ptah:config` could not produce a working select, and duplicated columns

Three defects of one family: several writers, one reader, no shared normaliser.
The fifth instance of this shape after `--style=` and `--filter=`.

- `--column="…:select:options=…"` stored the RAW STRING under `colsSelect`, which
  has to be a `label => value` MAP — both the modal form and both branches of the
  filter panel build their `<option>` list from `array_keys()`/`array_values()`
  of it. `collect()` on a scalar yields `[0 => "…"]`, so a CLI-configured select
  rendered exactly ONE option, labelled `0`, whose value was the whole unparsed
  definition. The command reported success.
- `--filter="…:options=…"` stored its options under `options` — a key the filter
  panel never reads — so every custom select filter rendered empty. Found while
  fixing the first.
- `--column=` APPENDED unconditionally, so re-running it for a field that already
  had a column produced two entries for the same field, with iteration order
  deciding which won.

`Ptah\Support\SelectOptions` is now the single normaliser both parsers funnel
through. It ratifies the two forms the docs already promised rather than
inventing a third: `value:Label` pairs and a bare list (label humanised). `|` is
deliberately not accepted — `badges=` already owns it with the opposite order.

The upsert has a subtlety: merging the whole parsed array let the parser's own
DEFAULTS win, most visibly the humanised `colsNomeLogico`, which silently
overwrote a label set in the visual editor on every CLI call that changed
something else. `ColumnParser` now reports which keys the definition actually
named, and an update merges only those.

`ptah:config:doctor` gains two `--fix` migrations for configs already saved in
the broken shapes. Run against the test app it found three, including one nobody
had planted.

### Fixed - the fallback text scale failed AA against every tinted tone

The reported item was "mod_subttl/phdr_sub at 4.16-4.18:1 in papel/nevoa". **That
is not a defect:** both read `--ptah-text-muted`, every package layout stamps
`data-ptah-text`, and all three scales override it with darker values. The worst
ratio that actually renders is 5.54:1.

Measuring it exposed a real hole one level down. The 4.11:1 figure comes from the
`:root` FALLBACK, used when no text scale is stamped, and those two tokens were
never calibrated against a tinted background: **34 failing pairs in light** (worst
3.62:1) and **12 in dark** (4.04:1). Invisible from inside the package; reachable
from outside it, because a host writing its own layout can stamp a tone without a
scale.

Light now takes the `suave` scale's own values, dark `#a3b0c0`. Zero failing
pairs, worst case 4.61:1. `AppearancePresetContrastTest` gains the case that was
missing — tone stamped WITHOUT a scale — and its dark half found the dark half of
the hole on its first run.

### Fixed - the ratchet did not scan borders, and forge-select had no error state

The reported item was "12 borders in _modal-form and 9 in forge-select". The
guard counted only `bg-` and `text-`, and the real total is **235 fixed-palette
border utilities across 31 views** — the earlier figure was the two files that
had been opened. The ratchet now scans borders, directional forms included, with
existing counts frozen per its own contract for a scope widening.

Taking the two reported files down surfaced a defect: **forge-select had no
visible error state at all.** It set `border-red-400` on a validation error and
that utility never applied — an unlayered rule beats a layered one whatever the
specificity. Measured: an errored trigger and a valid one rendered the same
border. No `aria-invalid` either. forge-input had the identical defect, got fixed,
and the fix was never mirrored. Now 6.10:1 in light and 3.96:1 in dark, and the
two states are distinguishable.

Every border removed was proven inert first — computed colour measured identical
with and without each class, in both scopes and both states.

### Fixed - configured actions were unreachable in the card view

`_cards` never handled `colsTipo === 'action'`. A gap while cards were opt-in; a
real hole since 1.25.0 made them the DEFAULT on a phone, where every custom
action an integrator had configured was simply unreachable.

The block moved into a shared partial rather than being copied — copying would
have meant copying the `javascript:`/`data:`/`vbscript:` href guard, and the
value comes from `crud_configs`, which the visual modal edits.

Extracting it surfaced a defect the original carried: `$col['actionIcon'] ?: …`
guarded against the key being EMPTY but not ABSENT, so an action configured with
no icon raised "Undefined array key" — an ErrorException, i.e. **a 500 on the
whole listing**.

### Added - the toolbar fits two rows on a phone

It was FOUR rows plus the sort bar: the search is `w-full` below `sm` and took one
alone, the New button came before it and took another, and the twelve remaining
controls wrapped into two or three. The first record started halfway down the
screen.

Nothing is permanently hidden and nothing is duplicated. A mobile-only visual
reorder (`order-2 sm:order-1`) puts the search alone on row one and New with the
actions on row two; above `sm` the original order returns and the desktop is
byte-identical. A new `⋯` reveals the secondary controls, which are the SAME DOM
elements — three of them are dropdowns with their own panels, and a menu that
reimplemented those would diverge from them exactly as the table and cards
diverged over actions.

Marked by class rather than a wrapper for a concrete reason: a wrapper would need
`display: contents` on desktop to keep the flex row intact, and such an element
has no `offsetParent` — hiding its children from the Alpine that measures
wrapping to collapse the labels, in the same file.

### Added - space reserved for the AI chat launcher

The launcher is `fixed bottom-6 right-6` with `w-14 h-14`: 56 px of button 24 px
from the edge, 80 px permanently in the corner at `z-50`. The listing reserved
nothing, and since a card's action row is right-aligned, edit/duplicate/delete on
the LAST card always fell underneath it. Invisible on desktop, where the list is
wide; on a phone the button sits over the only column there is.

`.ptah-crud-list-end` reserves it, gated on `.ptah-has-ai-launcher` — reserving
80 px of footer on every host that never enabled the module would trade old debt
for new. The condition became one variable used by BOTH consumers, since separate
copies could leave a host with dead footer and no button, or a button and no
space. Includes `env(safe-area-inset-bottom)` for the iPhone gesture bar.

### Tests

2062 -> 2078, and three existing guards earned their keep rather than being
worked around: `LayoutMigrationLedgerTest` caught 13 sites whose colour changed
under a regenerated baseline, `ThemeChromeOrphanTokenGuardTest` demanded an
obsolete exception be deleted, and `ToolbarControlUniformityTest` failed on a
class it should never have been asserting.

---

## [1.30.1] - 2026-09-04

Documentation only. 1.30.0 shipped the providers and the error handling without
the instructions for either, which is half a feature.

### Docs - `AiAgent.md` covers everything 1.30.0 added

- **The provider table** now lists all twelve options the admin screen offers
  (it had six), with a **streaming** column — because streaming is a provider
  capability, not a setting, and `z.ai` is the one that lacks it.
- **A new section on OpenAI-compatible platforms**, with the endpoint for each
  of Together, Fireworks, Cerebras, SambaNova, vLLM, LM Studio, llama.cpp and
  LocalAI, and an explanation of why `openai` with a custom endpoint does not
  work for them (it targets the Responses API).
- **Per-provider setup blocks** for xAI/Grok, DeepSeek, OpenRouter, Perplexity,
  z.ai and the OpenAI-compatible option — including "select xAI, not OpenAI",
  which is the mistake that made Grok look unsupportable.
- **Troubleshooting rewritten around the new messages**: a table mapping what
  the chat says to what to change, plus how to read the `reason`/`status`/
  `response_body` now in the log, and a dedicated entry for the
  "Unknown error / 400 / 422 from an OpenAI-compatible provider" case.
- **The provider picker** is documented: when it appears, that it starts on the
  default, and that the choice is validated server-side.
- `PTAH_AI_STREAM` and `PTAH_AI_NORMALIZE_TOOL_SCHEMA` added to the `.env` table
  and the config block.
- **Ollama:** an explicit warning not to add `/api` to the endpoint. Prism
  appends `api/chat` itself, so `…:11434/api` becomes `…/api/api/chat` — the
  defect fixed in 1.30.0, now documented so nobody re-creates it by hand.

### Docs - one claim removed for being wrong

The first draft listed **Azure OpenAI** among the platforms the
`openai_compatible` option covers. It does not: the carrier provider
authenticates with `Authorization: Bearer` (verified in Prism's source), while
Azure requires an `api-key` header and an `api-version` query parameter. Azure
is now named explicitly as *not* supported, with the reason — more useful than
silence, since someone would otherwise spend an afternoon on it.

### Tests

2044 -> 2048. `AiAgentDocTest` pins the provider table against
`AiModelConfigList::PROVIDERS` in both directions, requires the non-streaming
provider to be marked as such, and fails when the docs name a `PTAH_*` variable
the config never reads. The provider key map inside the code had already drifted
six providers behind Prism's roster once — a documentation table is at least as
prone to it.

---

## [1.30.0] - 2026-09-04

The AI agent could not be made to talk to Grok (x.ai) at all. Five defects, four
reported from the integration and one found while fixing them — each masking the
next, which is why the whole thing looked unsupportable rather than broken.

Every claim below was checked against Prism's shipped source before any code
changed.

### Fixed - the endpoint a user typed was written to a key nothing reads

`applyConfig()` set `prism.providers.openai.base_url`. **Prism reads `.url`** —
every provider block in its config uses that key, with no `base_url` anywhere.
So the `api_endpoint` saved in `/ptah-ai/models` was silently discarded for
`openai` and `anthropic`, and the request still went to api.openai.com. The 401
that came back even named platform.openai.com, which sent the investigation the
wrong way. Only `ollama` worked, because it happened to be spelled `.url`.

Both hardcoded maps are gone. The paths are derived from the provider name, so a
provider added to Prism upstream works here with no change — which is how the
key map had drifted behind the roster in the first place.

### Fixed - xai was routed through the OpenAI provider

`resolveProvider()` sent every unknown slug to `Provider::OpenAI`, whose handler
posts to `responses` — the Responses API, which x.ai does not implement. The
answer was a bare 422. Prism has had a dedicated `Provider::XAI` posting to
`chat/completions` all along.

The `match` is replaced by `Provider::tryFrom()`, which resolves the whole
roster including the six the list had fallen behind (xai, deepseek, openrouter,
perplexity, z, voyageai) and anything Prism adds later. OpenAI stays the fallback
only for a slug Prism does not know at all. The UI provider list gains xAI,
DeepSeek, OpenRouter and Perplexity, and a test asserts every slug it offers is a
real `Provider` value — a label added without one would silently reintroduce this
exact bug for a new provider.

### Fixed - tools with no arguments broke strict providers

A no-argument tool serialises its empty parameter list as `"properties": []` — a
JSON *array* where JSON Schema requires an *object*. Strict providers reject the
whole request:

    Schema validation failed: /properties: [] is not of type "object"

Both of ptah's built-in tools (`getSystemInfo`, `getCurrentDateTime`) take no
arguments, so every install talking to a strict provider failed on the package's
own tools. This is a Prism bug, and Prism has already fixed it for **two** of its
providers (Anthropic and OpenRouter emit `new stdClass` when empty) while OpenAI,
XAI, Groq, Mistral and DeepSeek still pass the raw array.

`Ptah\Support\AI\ToolSchemaNormalizer` rewrites the shape on the way out. It
deliberately does **not** sniff the destination host: that would miss every other
strict endpoint (OpenAI's structured mode, a self-hosted vLLM, Together) and need
a list maintained forever. `"properties": []` is invalid for any provider, so the
trigger is the malformed shape itself and a well-formed payload comes back
byte-identical. Gated on the AI module, and disableable with
`ptah.ai_agent.normalize_tool_schema`.

### Fixed - the provider's real complaint was being thrown away

`OpenAI Error [422]: Unknown error`. Prism formats its message from
`error.message` in the body, so a provider that reports the problem anywhere else
yields the literal "Unknown error" — and the schema message above, the entire
diagnosis of the previous item, took hours to find by tapping the HTTP client by
hand.

Nothing needed intercepting: Prism passes the original `RequestException` as
`previous`, so the body was always reachable. It now goes to the log
unconditionally, and is appended to the user-facing message only while
`APP_DEBUG` is on — that message reaches the chat widget, and a body can carry
internal detail an end user has no business seeing. No debug flag was needed.

### Fixed - Ollama's default base url had one path segment too many

Not in the report; found while generalising the maps. Prism's Ollama handlers
post to the **relative** path `api/chat`, and Prism's own default base url has no
`/api`. ptah forced `http://localhost:11434/api`, producing `…/api/api/chat`, so
a default Ollama install failed until the host set `OLLAMA_URL` by hand.

### Added - two platform gaps closed, found by auditing the roster

A sweep of Prism's providers against what the UI offered turned up exactly two
holes:

**`z.ai` (GLM)** was the only text-capable provider not offered — and the only
one with **no Stream handler**. Since `ptah.ai_agent.stream` defaults to true and
Prism's base provider throws `unsupportedProviderAction` from `stream()`,
offering it as-is would have shipped a provider that breaks the chat outright.
So `AiChatService::supportsStreaming()` now asks the resolved provider class
whether it declares its own `stream()`, and the widget consults that before
choosing a path. Detection rather than a hardcoded list, so a provider that
gains or loses streaming in a Prism release is handled with no change here.

**Every OpenAI-compatible platform without a dedicated Prism provider** had no
working option at all — Together, Fireworks, Cerebras, SambaNova, Azure OpenAI,
vLLM, LM Studio, llama.cpp, LocalAI. Prism's `openai` provider posts to
`responses`, which none of them implement, so pointing it at a custom endpoint
produced the same unexplained 422 that made Grok look unsupportable.

The new **OpenAI-compatible (custom endpoint)** option is a ptah alias resolved
onto a Prism provider whose handler speaks plain `chat/completions` with the
vanilla payload. `api_endpoint` is required for it, because there is no sensible
default for somebody else's server. Stated plainly: this rides on another
provider's config block for the duration of a turn, which is less clean than the
rest of this release — the proper fix is upstream (a
`Provider::OpenAICompatible`, or a flag on the OpenAI provider), and the alias
should be deleted when that lands.

A test now walks Prism's roster and fails when a provider ships a Text handler
the UI does not offer, so the next gap is a decision rather than a discovery.

### Changed - provider errors say what to do about them

Every failure used to surface as the translated sentence plus Prism's raw string,
typically `OpenAI Error [422]: Unknown error`. That leaves both audiences stuck: a
user cannot tell whether to retry, and an administrator cannot tell whether to
fix a key, an endpoint, a model name, or nothing at all.

`Ptah\Support\AI\ProviderFailure` classifies the failure once and each class
carries an actionable sentence:

| Cause | What the user is told |
|---|---|
| 401/403 | the credential was rejected — check the API key |
| 404 | the address was not found — check the endpoint |
| DNS/refused/timeout | could not reach the provider — check the endpoint and whether it is running |
| 429 | the provider is throttling — wait and retry |
| model not found | the provider does not serve this model — check the name |
| schema/tool rejection | the request format was refused; **not your fault**, details in the log |
| 5xx | the provider is unavailable — retry shortly |

The raw body never reaches that sentence (it can carry internal detail, and it is
already in the log, which now also records the classified reason and the status);
it is appended to the message only while `APP_DEBUG` is on.

### Added - a provider picker in the chat widget

With more than one active provider configured, the chat panel offers a choice;
the default is pre-selected, so a user who never touches it sees no change. The
control does not render at all for a single provider.

The picker's value is client-writable — that is the feature — so the validation
is on the read side: `AiProviderConfigService::resolveForTurn()` accepts an id
only when it names an **active** config and otherwise falls back to the default.
An administrator switching a provider off is a deliberate act (a rotated key,
stopped billing), and a client still holding the id must not be able to spend
against it; a stale picker in a long-open tab degrades instead of failing. The
list never carries `api_key`, which is an encrypted attribute with no business
being decrypted for a dropdown label.

The `<select>` follows the same resting-field recipe as every other field surface
in the package and is pinned by `FieldSurfaceParityTest` — giving a new control
its own colour pair is exactly how the BaseCrud selects drifted in 1.29.1.

### Tests

1951 -> 2044, including an end-to-end assertion on the real request Prism builds
for x.ai: it goes to `chat/completions`, not `responses`, and the no-argument
tool carries `"properties": {}`. Each fix was also verified by reintroducing it
and watching the right tests fail.

---

## [1.29.2] - 2026-09-03

Documentation only, plus the tests that hold it. Reported from PetPlace while
moving a "my attendances" screen (appointments scoped to the logged-in
professional) from a custom Livewire component to BaseCrud.

### Fixed - the logout label disappeared on hover in dark

Reported from the ERP. Two things went wrong together, which is why neither
looked like a bug on its own:

1. `.ptah-dark … .ptah-user-dropdown a, … button` painted the button
   `var(--ptah-text)` - a LIGHT ink - overriding its `text-danger`.
2. `hover:bg-danger-light` is `#fee2e2`, a near-white wash in **every** scope.

Light ink on a near-white background measured **1.21:1**, so the label simply
vanished. The `button` in that selector was a known quirk, described in the
stylesheet as "left as-is"; it is now removed and that comment corrected.

The sidebar footer logout already had the right recipe (`--ptah-danger-strong` /
`--ptah-danger-lite` plus a per-scope hover wash) - the two are the same
affordance in two places and had drifted. They now share one rule. Measured in
the built stylesheet, both places, both scopes:

| | at rest | on hover |
|---|---|---|
| light | 6.10:1 | 5.35:1 |
| dark | 7.98:1 | 6.72:1 |

The sidebar's light hover moved off the `hover:bg-danger-light` utility onto the
same token-derived wash, so no scope keeps a fixed light tint any more. Three
guards had to be updated rather than worked around, each earning its keep:
`ContrastGuardTest` (now covers both selectors, and refuses to let `button`
declare a colour there again), `ThemeChromeOrphanTokenGuardTest` (the exception
for that selector is obsolete and was deleted, per its own contract), and
`LayoutMigrationLedgerTest` (the site moved from *migrated* to *deleted* with a
reason).

### Fixed - the error pages could throw while explaining an error

The shell called `@vite(['resources/css/app.css'])` behind a check that
`build/manifest.json` **exists**. That is not sufficient: `@vite` throws
`ViteException` when the *entry* is missing, and a host whose stylesheet is
named anything else - renamed, split per area, a different bundler layout - has
a perfectly valid manifest without that key.

The throw then happened while rendering the page that exists to explain a
failure, turning a tidy 500 into Laravel's bare handler: the one outcome this
shell is built to prevent. The manifest entry is now confirmed before Vite is
asked for it, and the page degrades to its own literal fallbacks instead.
Reverting the guard makes the new test fail with the real `ViteException`.

### Docs - `lockedFilters` was undocumented, and it is the security-relevant one

`mount()` has accepted `lockedFilters` for a long time and the docs never
mentioned it, so the natural path was `initialFilter` - which the user can
simply clear. The two look identical on screen right up to the moment someone
presses "clear filters" and sees every row in the table.

`docs/BaseCrud.md` now documents it in the parameter table and in a new section,
**Locking rows to a fixed scope**, with the comparison that actually matters:

| Parameter | Can the user change/clear it? |
|---|---|
| `initialFilter` | **Yes** - it writes into `$filters`, a client-writable property |
| `companyFilter` | No (server-set), `company_id` only |
| `lockedFilters` | **No** - re-applied on every query, `#[Locked]` |

The section also states where the lock is enforced, verified against the code
rather than assumed: `buildBaseQuery()` covers the listing, the totalizadores
and export/print; `scopedQuery()` / `recordInScope()` cover bulk actions, edit,
delete and restore.

### Docs - three corrections found while auditing the parameter table 1:1

- **Every Blade example used `@livewire('ptah::base-crud', ...)`** - 7
  occurrences in `BaseCrud.md` and 2 in `Permissions.md`. The registered alias
  is `ptah-base-crud`; the namespaced form throws
  `ComponentNotFoundException`, confirmed by probing both. Every copy-pasted
  example from the docs failed.
- **`companyFilter`'s documented default named the wrong session key.** The docs
  said `session('company_id', 0)`; `ptah_company_id()` reads the key from
  `config('ptah.permissions.company_session_key')`, whose default is
  `ptah_company_id`. Anyone following the doc and setting `company_id` got
  nothing.
- **`initialFilter` silently discards the operator.** Each triple is read as
  `[$field, , $value]`; the operator applied comes from that column's own filter
  config. `['price', '>', 100]` does not produce `price > 100`.

A dead table-of-contents entry ("Simplified Internal Flow", a section that does
not exist) was removed and the list renumbered. All 40 internal anchors now
resolve.

### Docs - three flows that were only obvious to whoever wrote them

- **Deploying a screen:** a `crud_configs` row is data, not a file, so deploying
  code does not carry it. Documents the export-all -> commit -> import-all
  cycle, and the safe single-config pattern: `ptah:config:import-all {path?}`
  imports every `*.json` in the given directory with an upsert per file and
  never truncates, so a directory holding only the new config ships it without
  reverting anything edited through the visual modal in production.
- **Menu:** `database/seeders/MenuRegistry.php` is canonical and a new screen
  stays invisible until it is synced. Documented with plain `ptah:menu-sync`,
  **not** `--fresh`: the command's own help marks `--fresh` destructive (it
  clears the `menus` table), which is right for a rebuild and wrong for adding
  one screen to a live system, where it discards every row created or reordered
  through the UI.
- **Testing a BaseCrud screen:** the render is data-driven, so a component
  mounted against an empty `crud_configs` renders almost nothing. Documents both
  approaches - seed the config, or assert on the returned rows instead of the
  markup.

### Tests - the scope is inescapable, proven rather than promised

`CrudLockedFiltersScopeTest` covers the four escape routes a client can actually
attempt: filtering the locked column to another owner, clearing every filter,
writing `lockedFilters` over the wire, and asking for an out-of-scope record by
id (IDOR). The totalizador is asserted separately and deliberately - it runs its
own aggregate query, so a lock enforced only on the listing would show a
filtered table above the true total of every row, which is a disclosure of its
own.

Removing the enforcement fails 5 of the 8 tests, checked.

1942 -> 1951. The shared `items` stub table gains a nullable `owner_id`,
additive per that migration's existing convention.

---

## [1.29.1] - 2026-08-31

Three defects in the error pages 1.29.0 had just shipped, all reported from the
ERP within a day, plus the status that was missing from the set.

### Fixed - the error pages ignored a deliberate dark choice

With dark selected in `/profile`, the 403 still rendered white.

The tokens were never the problem: `--ptah-canvas` resolved correctly. Nothing
ever put `.ptah-dark` on `<html>`. The dashboard and auth layouts paint that
class from `ptah::partials.appearance-boot`, a blocking script in `<head>`, and
the error shell has no JS by design, so it inherited none of that and every page
fell to the `:root` light tokens no matter what the user had chosen.

The shell now resolves the appearance itself and stamps the class plus all six
`data-ptah-*` attributes server-side, in the tag. For these pages that beats the
script: nothing is left to resolve after first paint, so a flash is structurally
impossible.

### Fixed - and then the 403 followed the theme while the 404 did not

The first fix read `auth()` and `request()->cookie()` the way every other layout
does. That works only from inside the `web` middleware group, and an error page
cannot assume it is:

| status | path | before |
|---|---|---|
| 403 | thrown by middleware/component inside the group | session + decrypted cookie → themed |
| 404 | an unmatched URI never enters the group | no session, cookie still **encrypted** → white |

`request()->cookie()` returned the raw encrypted payload, `json_decode` failed,
the preference looked absent. The same applies to 503 (maintenance runs before
the group) and to any 500 thrown early in the stack.

Resolution moved to `AppearancePresets::resolveForStandalonePage()`, which does
the decryption `EncryptCookies` would have done — prefix validation included, so
a cookie encrypted under a rotated or foreign key is rejected rather than
half-trusted. Every step is individually guarded: a 500 may be rendering
*because* the database, session store or encrypter is unreachable, and an error
page that throws while explaining an error leaves the user with Laravel's bare
handler.

**The test that was missing is the lesson.** Every appearance test rendered the
view directly, which quietly supplies a fully booted request — session started,
cookies already decrypted. They all passed while the real 404 was broken.
`ErrorPageRealRequestTest` now walks each status through an actual request, in
the harsher environment (no session, encrypted cookie): if it works there it
works inside the group too.

### Fixed - selects and searchdropdowns clashed with the inputs beside them in dark

In the BaseCrud form modal the selects rendered on a different surface than the
text inputs next to them. Measured in the built stylesheet:

| palette | text input | select / searchdropdown |
|---|---|---|
| `meianoite` | `rgb(17,28,51)` | `rgb(8,14,28)` |
| `carvao` | `rgb(39,39,42)` | `rgb(24,24,27)` |
| `grafite` | `rgb(30,41,59)` | `rgb(15,23,42)` |

Light hid it completely, which is why it survived: `--ptah-field` is `#ffffff`
and `--ptah-field-muted` `#f8fafc`, indistinguishable there and ~12 RGB steps
apart in dark. The tokens' own definitions say which is correct - `--ptah-field`
is documented "active/focused input bg", `--ptah-field-muted` "resting
(unfocused) input bg" - and a select sitting untouched is resting. `forge-input`
had it right; `.ptah-c-form_in` and `.ptah-c-form_sel` used the focus token at
rest.

All three now share one recipe. The border is aligned for the same reason plus a
contrast one: `--ptah-line-control` against a white field is ~1.3:1, under the
3:1 floor this stylesheet applies to component boundaries elsewhere.

`FieldSurfaceParityTest` pins the *equality* of the three, which is what no
existing guard could see - both sides used valid tokens, contrast passed, the
palette ceiling passed, nothing was hardcoded. Only their agreement was wrong.

### Added - a themed 405 page

Found by sweeping every status live rather than trusting the list: 403, 404, 419,
429 and 503 had a page and 405 fell through to Laravel's default. It is
reachable with no developer mistake at all - an old form re-submitted after a
route changed verb, or a bookmarked POST - so it is now in the set, in both
languages. No "reload" action on it, deliberately: retrying the same verb on the
same URL produces the same 405.

### Docs

`Configuration.md`'s error-pages section gains the appearance precedence, why the
resolution is server-side here and script-based everywhere else, and what happens
outside the `web` group.

### Tests

1917 -> 1942.

---

## [1.29.0] - 2026-08-29

Three defects reported from the ERP running in production, plus the screens
that were missing behind them.

### Fixed - every plain `<select>` in a BaseCrud form threw HTTP 419, and only in production

`updatedFormData()` declared `string $key`. Livewire passes the changed sub-key
for `formData.status`, but passes **null** when the whole `formData` array is
replaced at once - which is exactly what a plain select's `wire:model` does.
The non-nullable signature turned that into a `TypeError`, and Livewire renders
an unhandled `TypeError` during an update as a bare `419 This page has expired`
once `APP_DEBUG` is off. With debug on it surfaced as a `TypeError` that looked
unrelated to the select, which is how it reached a production host.

Searchdropdowns were never affected: they write through
`selectDropdownOption()`, which always passes an explicit string field. That
asymmetry is what made the report hard to read, and it is now pinned by a test
of its own.

The signature is asserted directly through reflection, because the signature
*is* the bug - the test keeps failing for the real reason even if a later
refactor changes what the method body does with the key.

### Fixed - the BaseCrud form modal ignored the chosen theme

`_modal-form.blade.php` carried **41** fixed-palette Tailwind utilities
(slate/gray/red neutrals, several with no `dark:` counterpart at all) across its
selects, searchdropdown, image field, help text and unsaved-changes dialog. So
changing the tone or accent in `/profile` never reached the form modal - which
is most of what a user actually types into.

All 41 are gone, replaced by `.ptah-c-*` classes backed by `var(--ptah-*)`. No
new colour was introduced; every new class reuses an already-verified token.
The per-file ceiling in `HardcodedPaletteCeilingTest` dropped 41 -> 0 and the
entry was removed from the fixture, so the sweep now fails on the first
hardcoded utility that comes back.

One class the view had been referencing, `.ptah-c-form_hint`, turned out never
to have existed in the CSS at all.

### Added - themed error pages for 403, 404, 419, 429, 500 and 503

The old 403 was a standalone document with its own Tailwind CDN fallback and
~13 hardcoded hex values, so it ignored the theme entirely - the report that
started this. It also carried a per-page `.auto-dark-*` mechanism that would
have had to be copied into every new error page.

All six now share one shell, `ptah::errors.layout`, whose colours chain
`var(--ptah-token, literal-fallback)`. That chain is the whole design: the
token wins when `ptah-components.css` is loaded, so the page follows all six
appearance axes like any other screen; the literal takes over when it is not,
because an error page has to survive the failure that produced it. For the same
reason the shell inlines its own CSS and uses the system font stack - no JS, no
CDN, no webfont, nothing that can fail while the site is already failing.

Each page steps aside when the host has its own
`resources/views/errors/{code}.blade.php`, and when the request expects JSON.
500 additionally steps aside while `APP_DEBUG` is on, so a developer keeps the
stack trace instead of a pretty page hiding it. 403 stays gated behind
`modules.permissions`; the other five are controlled by the new top-level
`errors.enabled` (`PTAH_ERROR_PAGES`), top-level because `mergeConfigFrom` is
shallow and a nested key would never reach a host that already published
`config/ptah.php`.

**The reference id on the 500 page is stamped into the log.** The first version
of this minted it in the renderable - which Laravel runs *after* `report()`, so
the user was handed a code that appeared in no log line anywhere. A reference
support cannot grep is worse than no reference, because it promises a lookup
that cannot succeed. It is now minted inside `buildContextUsing()`, which runs
during `report()`, and merely read back at render time; a test asserts the
string on the page is the same string in the log context. It is deliberately
not the exception message, which can leak a query, a path or a credential to
whoever triggered the error. When an exception is not reported at all, the page
omits the line rather than inventing an id.

Publish them with `php artisan vendor:publish --tag=ptah-errors`.

### Docs

- `Configuration.md` gains an **Error pages (`errors.*`)** section: the key, the
  three non-configurable step-aside rules and why each would be a bug otherwise,
  and how the 500 reference correlates with the log.
- `Permissions.md` said a denied web request got "Laravel's default error page".
  It has not for some time; the row now names Ptah's themed 403 and the host
  override that beats it.

### Tests

1893 -> 1917. The new `ErrorPagesTest` asserts the things that make an error
page dangerous rather than ugly: that every `var(--ptah-*)` usage has a
fallback, that no page pulls a webfont, CDN or script, that 500 never prints
the exception message, and that the reference matches the log.

---

## [1.28.0] - 2026-08-28

Closes the two defects 1.27.0 shipped as *known, not fixed*. Both were found by
**using** the package rather than reading it: setting out to measure what it
costs to configure a screen, the command failed four times - three were my own
syntax, the fourth was the package.

### Fixed - `ptah:config --filter=` had never worked, and the docs said it had

**Four** layers disagreed about what a custom filter looks like (1.27.0's
changelog said three; the interactive wizard was the fourth, found while
fixing):

- `FilterParser` (`--filter=`) emitted `field` / `colsFilterType` /
  `defaultOperator` - which the runtime does accept;
- `FilterWizard` (interactive) emitted `colsFilterField` / `colsFilterLabel` /
  `colsFilterOperator` - which the runtime reads as nothing, so **every filter
  added interactively was inert too**, silently;
- `ConfigSchemaValidator` required `colsNomeFisico`, a key neither writer
  produced, so the command failed validation on **every single call**;
- and `ConfigCommand` wrote all of it to `config['filters']`, a section no
  runtime code reads - the runtime reads `customFilters` - so even a filter
  that had somehow passed validation would never have been applied.

`Ptah\Support\FilterRule` is now the single normaliser every writer funnels
through, in the shape `StyleRule` took for the identical defect in 1.24.0. The
canonical vocabulary is the **runtime's**, not the prettiest one: whatever
`FilterService::processCustomFilters()` reads is what a filter has to look like.

Two smaller things went with it:

- the empty `'filters' => []` seeded into every new config is gone - the same
  trap `'styles' => []` was, a key existing only to be migrated later, inviting
  whoever opens the JSON to write rules that never apply;
- the legacy section is no longer validated. Its rules required
  `colsNomeFisico` while the command that wrote the section produced `field`,
  so validation only ever reported "malformed" against configs the package
  itself had created, on top of the doctor error that already says to migrate.

`ptah:config:doctor` gains **check 8b**: `filters` to `customFilters`,
normalised, under `--fix`.

### Fixed - `advancedSearch()` re-introspected the schema on every call

`getData(search)` emitted 4 queries where plain Eloquent emits 2. The two
extras were schema introspection, repeated per call because `advancedSearch()`
called `Schema::getColumnListing()` directly while a cached `getTableColumns()`
sat in the same class. The relation-search path did the same - under a comment
that **claimed** a per-table cache it did not implement.

Both now go through `columnsForTable()`, static so a relation search caches the
*related* table too. Measured after the fix: call 1 pays 4 queries (the
introspection, once per process), calls 2 and 3 pay **2** - the same as
hand-written Eloquent. On MySQL those two hit `information_schema`, slow on a
database with many tables.

### Added - two guards, both of a kind a green suite cannot produce

- **`ConfigFilterCliTest`** asserts the entire chain rather than a JSON blob:
  the command writes, the section is the one the runtime reads, the legacy
  section is absent, and `FilterService` produces a filter DTO from what was
  stored. It executes the **literal examples** from `Commands.md` and
  `KnownLimitations.md` - a doc example that merely looks right is exactly how
  this went unnoticed for releases.
- **`BaseRepositorySchemaCacheTest`** is a *source* guard, not a runtime one:
  wasted queries succeed, so no assertion about behaviour can see them. It
  fails if `Schema::getColumnListing()` is called anywhere but inside the
  cache. Verified by reintroducing the direct call.

### Upgrade note

**If a screen has a `filters` section, those filters were never applied.**
Running `ptah:config:doctor --fix` moves them to `customFilters` and they
**start filtering** - a listing that shows everything today may show less
tomorrow. Read the doctor's output before running it against production.

The docs that claimed the flag worked are corrected, and `KnownLimitations.md`
now states plainly that `--filter` only began working in this release.

**Suite:** 1889 passing (+8 since 1.27.0), 11378 assertions. PHPStan clean -
it caught a redundant `is_array()` the parameter's own type already guaranteed,
removed rather than suppressed. No migrations.

---

## [1.27.0] - 2026-08-27

The screen that teaches permissions stops teaching code that does not run, and
the verb it teaches starts working. Plus two UI bugs reported from production,
a leaner agent skill, and the first measurements this package has ever
published about its own cost.


### Changed — `read` is now a real gate on BaseCrud, MASTER ratified as global

- **`can_read` now closes the screen.** `BaseCrud::render()` aborts 403 before
  the listing query runs when the CRUD's `permissionIdentifier` is denied
  `read`; export/bulk-export/queued-export/print follow the same rule. Before
  this, `can_read = false` never actually hid anything — the docs and the
  in-app guide taught it as if it did. `can_read` defaults to `true` (schema
  and `RoleService::bindPageObject()`), so no existing grant is affected; only
  a host that explicitly unchecked `read` starts being denied. A CRUD with no
  `permissionIdentifier` configured is unaffected either way.
- **MASTER is ratified as GLOBAL by definition.** `company_id` on a MASTER
  binding was already silently ignored by `PermissionService::queryIsMaster()`
  (never filtered by company); this is now documented as intentional rather
  than fixed, since scoping it would revoke access for every existing MASTER
  binding created under the opposite assumption. New/updated MASTER bindings
  now have `company_id` normalised to `null` at write time (logged), and
  `ptah:config:doctor` flags any pre-existing MASTER binding that still
  carries one, as a security alert.
- **Docs stop promising a `Str::slug()` role-name match** that
  `PermissionService::roleNamesMatch()` dropped five releases ago (identity
  match is case-insensitive/trimmed only — separators are not collapsed).

### Fixed — `/ptah-permission-guide` taught APIs that don't exist

The in-app manual (`Ptah\Livewire\Permission\PermissionGuide`) is a text
screen with no logic of its own, and had drifted out of sync with the code it
documents — four of its code samples would fatal-error if copied verbatim:
`Ptah\Traits\HasPermission` (never existed), `PermissionServiceContract::
can(userId:, key:, action:)` (the real method is `check(mixed $user, string
$objectKey, string $action, ?int $companyId = null)`), `Ptah\Models\Page`
(the class is `Ptah\Models\PtahPage`), and `PTAH_AUDIT_MAX_RECORDS` (does not
exist anywhere in the repository — the real knobs are `audit`/
`audit_denied`/`audit_master`/`audit_retention_days` plus `ptah:audit-prune`).
The screen also never mentioned the qualified-key syntax (`page::obj_key`,
v1.19) or column-level permissions (`colsPermission`, v1.20), taught the
decision flow as a 3-node tree that ended in "redirects to login" (the guest
branch actually resolves `allow_guest`, and the redirect belongs to the
`auth` middleware, not this check), and the FAQ tab was hardcoded in
Portuguese in the view while translated `guide_faq_*` keys existed in both
locales and were never consulted — an `en`-locale user always read
Portuguese.

All of the above is corrected, plus: MASTER's company-blindness (see above),
the real 3-way company-scope resolution, generation-based (instant) cache
invalidation, the 4 real audit conditions, and two new FAQ entries pointing
at `ptah:permission:why` (diagnostics) and explaining the new `read` gate.

### Changed — `/ptah-permission-guide` theming (279 → 0 hardcoded utilities)

The screen also carried 279 fixed-palette utilities with zero `dark:` pairs
(tracked in `docs/KnownLimitations.md` §6 as "scheduled for its own wave" —
this is that wave). It now reuses `<x-forge-page-header>`, `<x-forge-tabs>`,
`<x-forge-card>`, `<x-forge-alert>` and existing `.ptah-c-*` classes, plus 8
new token-driven classes with no new `--ptah-*` token:
`.ptah-c-code`/`.ptah-c-code_cap` (the code-examples tab, which also drops
~130 lines of hand-rolled per-token syntax-highlight spans in favour of a
plain escaped code block), `.ptah-c-step_num` (setup-tab step badges) and
`.ptah-c-guide_node`/`_q`/`_ok`/`_no`/`.ptah-c-guide_conn` (the architecture
and decision-flow diagrams). Its hardcoded-palette-ceiling fixture entry is
removed (count reached 0). A companion sweep fixed 23 fixed-palette
utilities baked directly into the `guide_*` lang strings themselves (`bg-
slate-100` code chips, `text-indigo-600`/`text-purple-700`/`text-blue-700`/
`text-slate-400` spans) — invisible to the view-level ratchet, which never
reads lang files.

### Added

- `tests/Unit/Support/GuidePaletteFreeLangTest.php` — zero-tolerance guard for
  raw Tailwind palette utilities in any `guide_*` lang key, in both locales.
- `tests/Unit/Support/PermissionGuideClaimsTest.php` — pins the 5 corrected
  falsehoods and the 4 newly-taught terms against ever drifting back.
- `tests/Feature/Permission/PermissionGuideContentTest.php` — renders all 4
  tabs in `pt_BR` and `en` for a MASTER user, asserting no raw
  `ptah::ui.`-prefixed key leaks into the output.
- `ConfigDoctorCommand` check 10 — MASTER bindings scoped by `company_id`.

**Compatibility:** no PHP API changes. A host that published/overrode the
guide's view keeps its own copy and does not receive these corrections.

### Fixed — two defects found by a rendered-DOM contrast audit (all 6 tones)

- **Invisible architecture-diagram arrows.** `.ptah-c-guide_conn` declared
  `color` and `background-color` with the same token
  (`--ptah-line-field`) — 1.00:1 in every tone, because the class served two
  jobs (a filled connector `<div>` in the decision-flow diagram, and a text
  arrow glyph `→`/`↔`/`←` in the architecture diagram) with one declaration
  each. `AppearancePresetContrastTest`'s pair helpers could not catch this:
  they prove a token against ANOTHER selector/token or an ambient
  background, never a rule against its own color/background-color pair. The
  4 arrow glyphs are now plain filled connectors (`aria-hidden`, no text
  content), matching the flow diagram's existing idiom; `.ptah-c-guide_conn`
  declares `background-color` only. New guard:
  `tests/Unit/Support/CssNoSelfPairedTokenTest.php` — fails any `.ptah-c-*`
  rule that pairs `color`/`background-color` on the identical token; a
  one-off repo-wide scan confirmed this was the only rule affected.
- **`HardcodedPaletteCeilingTest` was blind to chromatic colors.** Its two
  regexes only ever matched the neutral Tailwind families (gray/slate/zinc/
  neutral/stone); `bg-indigo-50`/`bg-red-50`/`bg-green-100`/`bg-amber-50` and
  188 more chromatic sites across the package's views passed it silently —
  none in the guide this wave fixed, one of them measured at 1.17:1 by the
  same audit. Both patterns now cover the full chromatic palette too; the 45
  affected files' ceilings were raised in the same commit to their
  already-existing (now fully counted) totals — per the ratchet's own
  contract, existing debt is frozen, not silently reduced to zero. See
  `docs/KnownLimitations.md` §6 for the corrected, wider-scope total (1010
  across 45 files, up from 818 — a scope widening, not a regression).

### Fixed - two UI bugs reported from production

- **The menu editor's icon field** was the only one of eight not using
  `<x-forge-input>`, so it reimplemented border and focus by hand with fixed
  blue - visibly wrong beside its siblings on a host whose accent is teal.
- **The AI models screen's modal would not reopen** after being closed, with
  nothing in the console. It kept two sources of truth for the open state
  (`@if($showModal)` plus a literal Alpine `x-data="{ open: true }"`) and
  Livewire's morph preserved the stale one - the modal existed in the DOM and
  stayed invisible. Aligned to the `@entangle` pattern the other three module
  screens already used, with a structural guard against the combination. That
  screen also moved onto `forge-page-header`, `.ptah-module-toolbar` and
  `.ptah-module-table` (fixed palette 9 to 2).

### Changed - the agent skill lost a third of its weight

The skill is loaded on **every** task in a host project, and performance
guidance (caching, queues, indexes, N+1, chunking) was 34% of it while being
needed in a minority of tasks. It moved to `references/performance.md`, read on
demand. **12.2k to 8.2k tokens, nothing deleted.** A size ceiling in
`SkillGuidanceTest` keeps it from re-absorbing the weight, and also asserts the
pointer resolves - a broken pointer is worse than inline text.

### Measured - two claims that were only ever adjectives

**Token economy.** One entity with full CRUD + API, run for real and measured
file by file: **~250 tokens with ptah against ~10,460 without**. The 14
generated files are ~5,071 tokens; a hand-written CRUD screen averages ~5,391,
measured across four of this package's own module screens, which *are*
hand-written CRUDs. **~42x, and the skill's fixed cost pays for itself on the
first entity.** Caveats so nobody over-claims: those module screens may be more
complete than what an agent writes in a hurry, and exploration tokens are
counted on neither side. Halve it and it is still ~20x.

**Runtime cost.** sqlite in memory, 5,000 rows, 20 repetitions, warm-up
discarded:

| operation | queries | time |
|---|---|---|
| plain Eloquent `where+like+order+paginate` | 2 | 7.63 ms |
| `BaseService::getData()` AND + order + paginate | **2** | 6.70 ms |
| `getData()` searchLike | 1 | 12.05 ms |
| `getData()` search (OR across `$fillable`) | 4 | 20.01 ms |
| reading a screen's CrudConfig | 1 | 0.49 ms |

**The abstraction charges no toll** - the main listing path emits the same
queries as hand-written Eloquent, and being config-driven costs one query and
half a millisecond per screen. These numbers now open
`references/performance.md`, so an agent does not optimise the base layer on
suspicion.

### Known, not fixed (found while measuring, scheduled)

- **`ptah:config --filter=` is inoperative**, and the docs say it works. Three
  layers disagree: `FilterParser` emits `field`, `ConfigSchemaValidator`
  requires `colsNomeFisico`, and the runtime reads `customFilters` while the
  command writes `filters`. The literal examples in `Commands.md` and
  `KnownLimitations.md` all fail. Same shape as the `--style=` debt closed in
  1.24.0.
- **`BaseRepository::advancedSearch()` re-introspects the schema on every
  call** - `Schema::getColumnListing()` direct, ignoring the cached
  `getTableColumns()` in the same file, which is why `getData(search)` shows 4
  queries above instead of 2. A sibling method's comment claims a cache it does
  not implement.

**Suite:** 1881 passing (+36 since 1.26.0), 11358 assertions. PHPStan clean.
No migrations.

---

## [1.26.0] — 2026-08-26

The theming wave. The package starts obeying its own rule — *every color
through a token* — after its author switched a production host to the **papel**
light tone and watched parts of the UI stay white. No migrations, no schema
change; two upgrade warnings below deserve reading before `composer update`.

### Changed — 291 fixed-palette utilities migrated to `--ptah-*` tokens

Across the BaseCrud partials (cards, modal form, pagination, print), 17
`forge-*` components and 13 module/admin screens, `bg-white` / `bg-slate-*` /
`text-gray-*` / `dark:` pairs became named `ptah-c-*` classes backed by token
rules in `resources/css/ptah-components.css`. Every surface now follows all 6
appearance axes — the card view under *papel* paints `#fffaf0` instead of
staying white, measured in a real browser across every tone preset.

The migration ran through the full pipeline: an engineering plan (which also
disproved two of our own beliefs — see Fixed below), 18 implementation commits,
two adversarial reviews that found **14 real defects behind a green suite**,
and 6 fix commits each born from a failing test.

### Added — two guards for the two failure classes this wave exposed

- **`HardcodedPaletteCeilingTest`** — a per-file ratchet on fixed-palette
  utilities that may only ever go down, fixture tightened in the same commit
  that reduces a count. It exists because the count **grew** 999 → 1019
  between 1.15.0 and 1.25.0 while the prose rule already forbade it.
- **`AppearancePresetContrastTest` extension** — reads the token *actually
  declared* in each migrated rule and proves the resulting text/background
  pairs across all 6 tone presets. This is the gap that let a 1.00:1 chart
  title and a 1.9:1 switch track ship green.

### Fixed — found by the reviews, invisible to the suite

- **Three migrations were inert in dark mode**: the dashboard layout's legacy
  `<style>` block still overrode them — `!important` on the light/secondary
  buttons, higher-specificity rules on pagination and the page-header back
  link. The legacy rules are deleted (the layout block shrinks to 20 literals
  / 28 rules, from 36/39).
- **Contrast regressions in dark tones**: chart-card title at 1.00:1
  (`text-dark` on a now-dark surface), switch OFF track at ~1.9:1 with the
  knob darker than the track, progress/stepper/avatar-badge tracks at ~1.4:1.
  Root cause was semantic: `--ptah-line-strong` is a *decorative separator*
  token; information-bearing elements now use `--ptah-line-field`, calibrated
  at 3:1.
- **Wrong-value token picks**: badge `light` used the hover token in light
  mode; three status/loading dots darkened in both modes; `relief` buttons
  were byte-identical to solid in dark. Pagination text now maps
  `text-gray-600` → `--ptah-text-secondary` (the closest value), not
  `--ptah-text`.
- **`focus:bg-white` on the audit screen's filter field** beat the tokenized
  toolbar rule by specificity — focusing the field painted it white under
  *papel*, with the whole suite green. The literal reported symptom.
- **The menu editor's icon field** (and its preview chip and example chips)
  ignored the theme, and two of its strings were hardcoded pt-BR — now
  tokenized and translated (`menu_form_icon_*` keys in both languages).
- `forge-textarea` regained its `:disabled` background, lost in migration.

### Deliberate visual changes (declared, not regressions)

- Light/secondary buttons in dark mode now follow the dark **tone** (zinc
  under *carvão*, navy under *meia-noite*) instead of pinned slate, gain the
  same shadow as the other variants, and `flat` is finally transparent there.
- Pagination: enabled text `#94a3b8 → #cbd5e1` in dark; border moves to
  `--ptah-line-field-hover`, fixing an inherited sub-3:1 failure the old
  `border-gray-200` always had.
- Status/loading dots are lighter in both modes (back to their pre-wave
  visibility, now token-driven).

### Docs — agent-first routing, and the skill stops teaching the bug

- The shipped `ptah-development` skill is now **entirely in English**, opens
  with a *configure-before-you-code* decision map (including the row that was
  missing: a complete Swagger-annotated REST API is one flag,
  `ptah:forge Name --api`), and its Design Tokens section teaches the token
  contract instead of the fixed-hex table that caused the papel bug in the
  first place. `SkillGuidanceTest` keeps the poison rows from returning.
- **`AGENTS.md`** at the package root: a one-page router for AI agents and
  busy humans — which document answers which question, and the two rules that
  prevent the most wasted work.
- `KnownLimitations.md` §6 rewritten from fresh measurements, **correcting a
  five-release-old false claim of ours**: `crud-config` has been fully
  theme-aware since 1.21 via scoped repaint rules — a raw grep of palette
  utilities counts both the debt and the keys of the mechanism that fixes it.
  Honest decomposition of the 1097 remaining occurrences (439 are not debt,
  279 are `permission-guide` — the real worst offender, scheduled for its own
  wave, ~112 await a missing faint-glyph token tier, ~70 are correct forever).

### Upgrade warnings

- **Hosts without a guaranteed Vite build**: the CDN fallback gets stricter on
  purpose. Surfaces that used to paint via `bg-white` now delegate to the
  package stylesheet, which the CDN fallback never loads — they render with
  **no background at all**. Do not upgrade past 1.25.0 without a build.
- **Hosts that wrote CSS over package-view utilities** (selectors targeting
  `bg-white`/`text-gray-*` inside package views) lose those anchors on this
  release, as the docs always warned.

### Deferred, documented

`permission-guide` (279 occurrences, zero `dark:`), the faint-glyph token tier
decision, `forge-alert` (its AA proof composites literals — tokenising it
invalidates the proof), `forge-demo` (standalone showcase, never loads the
package stylesheet).

**Suite:** 1831 passing (+29 since 1.25.0), 11049 assertions. PHPStan clean,
no new baseline entries. No migrations.

---

## [1.25.0] — 2026-08-25

The mobile round. Every item here came from a production ERP's first day on a
phone — none of it from the test suite.

### Changed — cards are the default on a narrow screen

`viewMode` gained a third state, **`auto`**, and it is the new default: the
table at `≥ md`, cards below.

The third state is not decoration. `viewMode` is a *persisted per-user*
preference while the layout question is *per-device* — writing `cards` because
someone opened a screen on their phone would hand their desktop session a card
grid the next morning. `auto` persists nothing device-specific and lets CSS
decide at render time, which also means no viewport round-trip, no wrong-layout
flash on first paint, and none on rotation either.

**Existing installations get it.** Every preference blob written before this
release stored `viewMode: 'table'` because that was the default, not a choice;
reading them literally would mean the responsive layout never arrives for a
single existing user — exactly the people who reported the problem. The
preference schema version (`2.2.0`) separates the two cases: pre-2.2.0 blobs
storing `table` are read as `auto`, a `table` pin chosen *after* upgrading is
respected, and a legacy `cards` was always deliberate and is never touched.
Same mechanism the density axis used in 1.18.0.

Rendering both layouts under `auto` costs, measured rather than assumed: on a
real 4-row listing **+11,826 bytes raw, +984 after gzip (2.1%)**. No extra
database or formatting work — both layouts consume the same rows through the
same `formatCell()`, and the hidden half sits in `display:none`, which the
browser does not lay out. An explicit `table`/`cards` pin renders only that
layout.

Set `ptah.crud.responsive_cards` to `false` for the previous behaviour. **If
you published `config/ptah.php` before this release, that key is absent from
your copy** — Laravel merges package config shallowly, so a new *nested* key
never reaches an existing published file. Absence reads as `true`, which is the
intended default; disabling it means adding the key to your own config, and the
`PTAH_CRUD_RESPONSIVE_CARDS` env var alone will not reach you.

### Added — the card view can be sorted

It could not be, at all. The order came from whatever the **table** view had
last chosen, so on a phone — where the table is never opened — every listing
was frozen on `id DESC`.

A table header folds two gestures into one (pick the column, click again to
reverse), which does not survive a touch screen. The new `_sort-bar` splits
them: a `<select>` for the column, a button for the direction. Both are built
from the new `sortableColumns()`, the same method that decides which table
headers are clickable, so the select and the headers cannot drift apart. It
renders nothing on a screen with no sortable column.

`sortBy()` still serves the headers. The select goes through `updatedSort()`,
which **keeps** the direction — toggling on re-pick would silently reverse the
listing — resets the page, saves preferences, and re-validates the incoming
value against the allowlist: a column the user may not read must not be
namable, or ordering becomes an oracle for it.

### Fixed — a tampered sort direction could 500 the screen

`$direction` is bound in the view and interpolated into `ORDER BY`. Laravel's
`orderBy()` throws on anything but asc/desc, so a single `$wire.set` was enough
to take a listing down. `updatedDirection()` now normalises it.

### Changed — the navbar collapses into one menu on a phone

The company switcher's inline bar (active company name plus one tab per
company) fought the bell, the avatar and the settings gear for the same ~60px,
and the companies came out overlapping and unreadable.

Below `md` the inline bar is hidden and the switcher reappears as a vertical
section at the top of the admin menu — the switcher's new `stacked` layout. It
is a second instance of the same Livewire component, not copied markup, so
switching company stays owned by `CompanySwitcher` and there is no duplicated
logic to fall out of sync. The active row is marked by an icon and
`aria-current`, not by colour alone (WCAG 1.4.1, and the dark panel does not
give two text colours enough separation anyway).

If no admin menu exists — every module that generates it is off — the inline bar
stays visible on mobile instead. Cramped beats absent: hiding it there would
leave a phone user with no way to change company at all.

### Fixed — three regressions from this very release, caught by looking

Recorded because each is a class of bug a green suite cannot see:

- **The descending sort icon was clipped.** Its path drew to `y=28` inside a
  24-unit `viewBox`. `DESC` is the default direction, so it was the icon every
  user would have seen first.
- **`min-height: 2.75rem` on the sort controls broke the density axis.** Added
  "for the 44px touch target", it lifted exactly two controls off
  `--ptah-control-h`: 44px against the toolbar's 36px (comfortable) or 28px
  (compact) — `min-height` beats `height`. Removed, because the lever already
  existed: the profile's **spacious** density *is* 44px and raises every
  control together. Measured after the fix: 28 / 36 / 44 px across all four
  controls.
- **Hiding the navbar's middle grid child moved the actions to the middle.** A
  `display:none` item occupies no grid column, so auto-placement pulled the
  actions from column 3 into the `auto` column 2 and they rendered mid-bar
  instead of against the right edge. Fixed with an explicit `col-start-3`;
  measured 16px from the right edge (the navbar's own `px-4`) at 390 / 767 /
  768 / 1440.

All three now have guards. A note on the verification, since it cost time:
Chrome on Windows clamps `--window-size` to roughly 504px, so only device
emulation gives a true phone viewport — the "overflow" first visible in a
screenshot was the image being cropped at 390 while the page was laid out at
504.

**Suite:** 1802 passing (+26). PHPStan clean, no new baseline entries. No
migrations.

---

## [1.24.0] — 2026-08-24

Closes the `--style=` design debt by deciding the question underneath it: the
misspelled `contitionStyles` **is** the canonical key, ratified rather than
renamed. Nothing to migrate, no behaviour change for any existing `--style=`
call.

### Changed — `contitionStyles` is official

Every writer (CLI, visual editor, generator, doctor) and the only render-time
reader (`HasCrudRenderers::getRowStyle`) already agreed on it. Renaming to the
correct spelling would touch every exported config in every installation — and
this package is on Packagist, so "every installation" is not a number anyone
knows — to buy nothing but orthography. The correctly-spelled
`conditionStyles` stays a read-only alias, as before.

### Fixed — the published JSON schema rejected every working config

`JsonSchemaBuilder` documented a `styles` section, which nothing reads at
render time, and carried `additionalProperties: false` over 7 declared keys
while real configurations carry 24+ (`cacheStrategy`, `configLinkLinha`,
`crud`, `customFilters`, `uiPreferences`, `notifications`…). Anyone who wired
it up to validate a real config would have seen everything fail at once.

It now names `contitionStyles`, accepts **both** item shapes via `anyOf` (the
legacy shape still renders, so rejecting it would report a problem that does
not exist), takes its condition enum straight from `StyleRule::CONDITIONS` so
the two cannot drift, and leaves the root open. A documentation schema that
enumerates a growing key set becomes a lie the moment a key is added; the
authority on validity is, and remains, `ConfigSchemaValidator`.

### Fixed — a colon in the style VALUE corrupted the rule in silence

```bash
--style="start_at:==:12:30:background:#eee;"
# value "12", style "30:background:#eee;" — never matches, emits garbage CSS
```

The style segment is colon-rich by nature, so the positional form cannot also
let the value hold a colon. There is now an explicit end-of-value marker, in
the same `key=value` idiom `--column`/`--filter`/`--action` already use:

```bash
--style="start_at:==:12:30:style=background:#eee;"
# value "12:30", style "background:#eee;"
```

**The short form is byte-for-byte unchanged**, which means the bad case above
is still mis-parsed when the marker is omitted. That is deliberate: nothing
distinguishes `30:background:#eee;` from a legitimate style string, so
auto-detection would be a heuristic that mis-parses valid input instead. The
constraint is documented in `docs/KnownLimitations.md`, and both behaviours
are pinned by tests.

`FilterParser` and `ColumnParser` fixed this same class of bug for their
`options=` lists back in 1.17.0; `--style=` was the one left out.

### Changed — new configs no longer sprout an unread key

`getDefaultConfiguration()` stopped seeding `'styles' => []`. A key that
exists only so `ptah:config:doctor --fix` can migrate it later is an
invitation: whoever opens the JSON — person or agent — writes rules where the
name makes sense, and those rules silently never apply. Rows that already
carry it are still migrated.

### Deprecated

`Ptah\Commands\Config\Validators\ConfigValidator` is marked
`@deprecated`, superseded by `ConfigSchemaValidator` (what `ConfigCommand`
actually runs). It has had no production caller for several releases. **It is
not removed**: it is a public class on a published 1.x package, so deleting it
would be a semver break for anyone calling it, dead or not. Removal is
scheduled for 2.0.

The harmful half is fixed now — a docblock in `CrudConfigEnums` pointed
readers at `ConfigValidator::validateStyle()` as the authority on style
conditions, when the authority is `StyleRule::normalize()`.

### Added — the guard for schema-vs-runtime drift

`PublishedSchemaMatchesRuntimeTest` runs `ptah:config --style=` and asserts
that the package's own published schema accepts the package's own output.
Verified against the pre-fix schema: 5 of its 6 tests fail there.

Every finding behind this release has the same shape — *an artifact that
describes the system drifted from the system, and nothing broke*. The schema,
the orphan class, the key that is born empty, the docblock pointing at the
wrong validator: all green the whole time, because nothing tested the
coherence between what the package says about itself and what it does. This
package already guards doc↔keyMap, enum↔renderer↔schema and tooltip↔field;
schema↔runtime was the missing axis.

**Suite:** 1776 passing (+10). PHPStan clean, no new baseline entries. No
migrations.

---

## [1.23.0] — 2026-08-24

Notifications stop being something you write code for. A CRUD screen now
declares — in the visual editor, in the ninth tab — which lifecycle events
notify whom, and one trait on the model makes it happen. Optional realtime
through Reverb. No schema change on upgrade.

### Added — config-driven CRUD notifications

The dev picks the event (`created` / `updated` / `deleted`), the audience
(a specific user, a role, or every active staff member), and the message.
The model gets one trait:

```php
use Ptah\Traits\SendsCrudNotifications;

class Order extends Model
{
    use SendsCrudNotifications;
}
```

That is the whole integration. The rules live in the `config` JSON of the
screen's `crud_configs` row, under `notifications.rules`, alongside the
columns and filters they already describe — so exporting a screen's config
carries its notifications with it.

Titles, bodies and URLs accept `%column%` placeholders, resolved from the
saved record: `Nova venda: %customer_name%`, `/orders/%id%`.

**Placeholders honour column permissions.** A column tagged with
`colsPermission` is not substitutable — a notification cannot become the
side channel that leaks a value the recipient is not allowed to see on the
grid. The primary key is normally substitutable even without a column entry
(so `/orders/%id%` works out of the box), *unless* the config explicitly
restricts it, in which case the restriction wins. Both halves of that
contract are pinned by tests.

Delivery is a queued job (`SendCrudNotificationJob`) marked
`afterCommit` — a rolled-back save sends nothing, and the `sync` driver
honours that too. A delivery failure is logged, never raised: a
notification must not be able to break the save that triggered it. A
reentrancy latch means a cascading save inside a lifecycle hook does not
fire a second round of notifications.

### Added — optional realtime (Reverb)

Off by default and inert: three early returns (dispatch, channel
registration, the bell's listener) mean a host that never sets
`PTAH_NOTIFICATIONS_BROADCAST=true` does not execute a line of this code,
and the existing poll keeps working unchanged.

When enabled, `PtahNotificationCreated` broadcasts on the private channel
`ptah.notifications.{userId}`, authorized to its owner only — registered by
the package, so no edit to `routes/channels.php`. **The event carries the
notification id and nothing else** — never the title or body. The payload
transits the websocket server, and a notification's text can quote a
record's data; the id is a pure "go refresh" trigger, and the content still
arrives over the authenticated path.

The channel registration is wrapped in `try/catch` on purpose:
`Broadcast::channel()` resolves the default connection the first time it
runs, so a host whose `BROADCAST_CONNECTION` points at a driver with no SDK
installed would otherwise have this package breaking the boot of *every*
request. A test asserts the boot survives exactly that.

### Added — a tooltip on every field of the config editor

All 147 form fields in the CrudConfig editor carry a `title` explaining what
the field does, in English and pt-BR. A test fails the build when a field
lacks one — and when it uses the `:title="__()"` form, which on a plain HTML
tag is an Alpine expression, not a Blade binding (the bug this codebase has
now shipped twice).

Two tooltips written for this release described behaviour the code does not
have and were corrected against the implementation before merge.

### Added — `ptah:config:doctor` check 9: notification delivery

Found by using the feature: a correctly configured rule delivered nothing, and
nothing anywhere said why. The queue connection was `database` with no worker
running, so the job sat in the queue looking exactly like a broken feature.

The doctor now reports, per screen carrying `notifications.rules` and all
warning-only:

- the model does not use `SendsCrudNotifications` — the rules are inert, since
  nothing hooks the Eloquent events;
- an audience naming a role or user that does not exist, or left empty;
- a rule whose title is empty (the runtime drops it).

And once per run, when any screen has rules and the connection is not `sync`:
the note that delivery only happens while a worker runs.

New `ptah.notifications.queue_connection` (default `null` = the application's
own connection) forces notification jobs onto a specific connection — set it to
`sync` to deliver inline without moving the whole application off its queue.
It is a nested config key, which is normally unsafe under Laravel's shallow
package merge; it is safe here only because its absence *is* its default.

The role match uses the same identity rule as `ptah_has_role`: case-insensitive
and trimmed, never slugged.

### Changed

- **"Tema Visual" removed from the general settings tab.** Appearance has
  been global for several releases (the six `data-ptah-*` axes on `<html>`);
  the per-screen `theme` key was read by nothing but the editor that wrote
  it. Existing values are ignored, not migrated — nothing to do.
- `docs/Configuration.md` said the editor had 7 tabs. It has 9, and the
  `notifications.rules` schema was documented nowhere. Both fixed, plus the
  full 11-field rule shape, config precedence when a model has two config
  rows, and where each audience legitimately resolves to zero recipients.
- The bell's dropdown list scrolls (`max-h-[min(24rem,60vh)]`), with "mark
  all as read" pinned outside the scroll area. At `dropdown_limit` 20 the
  panel passed 1800px and pushed its own footer off-screen, and the navbar
  is fixed, so the page behind it did not scroll to the rescue.

### Fixed

- `AiChatService` called `set_time_limit(300)` unconditionally. Under CLI the
  limit is 0 (unlimited), so the call *imposed* a five-minute ceiling on the
  process — this was the cause of test runs dying around 95% for days. Now
  guarded by SAPI.

### Still not implemented (documented as such)

The notification history page and `ptah:notification-prune` remain absent;
`purgeRead()` exists on the service for anyone scheduling their own cleanup.

**Suite:** 1766 passing (+257 since 1.22.0). PHPStan clean, no new baseline
entries. No migrations.

---

## [1.22.0] — 2026-08-23

A reusable notification centre, a contrast audit that measures the rendered
DOM instead of the source, and the root cause of test runs dying mid-suite.
No schema change on upgrade — the notification table is publishable and
opt-in.

### Added — notification centre

The navbar bell stops being a decorative dead control. ptah provides the
plumbing; your application provides the generators. Full guide:
[docs/Notifications.md](docs/Notifications.md).

- **Navbar slot with three states** (`ptah.navbar.notifications`): unset keeps
  today's static bell byte-identical; `none` (also `off`/`hidden`/`false`/`0`)
  **removes the bell from the DOM**; any other value mounts that Livewire
  alias. A typo'd alias falls back to the static bell instead of throwing
  `ComponentNotFoundException` from inside the layout, which would take down
  every page.
- **`ptah_notify()` / `ptah_notify_role()` / `ptah_notify_all()`** plus
  `NotificationService` — idempotent pushes by `dedupe_key`, audience
  targeting that reuses the roles and company scoping from 1.19.0, and
  `unreadCount`/`list`/`markRead`/`dismiss`/`purgeRead`.
- **`ptah-notification-bell`** — unread badge (hidden at zero), dropdown with
  colour and icon per type, click marks read and deep-links to the record,
  empty state, Esc/click-away, `aria-expanded`/`aria-haspopup`, dark mode
  through the existing tokens (zero new CSS). The list query runs only while
  the dropdown is open, so a closed bell polls one indexed `count()`;
  `PTAH_NOTIFICATIONS_POLL=none` turns polling off entirely.
- **Opt-in by construction**: the migration lives in a publishable
  `database/migrations/`, the module is off by default, and every read
  degrades to a neutral value when the table is absent — no exception, no
  logged SQL error. A test proves the table does not appear from the
  package's own migrations even with the module enabled.

> **Upgrading changes nothing** unless you opt in:
> `vendor:publish --tag=ptah-notifications` + `migrate`, then
> `PTAH_MODULE_NOTIFICATIONS=true`. Human-executed, per environment.

Not implemented yet, and marked as such in the docs: the "view all" history
page and the `ptah:notification-prune` command (the service's `purgeRead()`
can be scheduled from your app meanwhile). Broadcasting, email and per-user
preferences are not planned.

### Fixed — contrast, measured in the browser

Two previous releases "fixed" dark-mode contrast in the CRUD config modal by
measuring pairs picked by hand from the source. That method missed whole
categories, so the modal was still wrong. A browser auditor that walks every
visible text node, composes the effective background up the tree and computes
the real WCAG ratio found **67 failing pairs**, including:

- a title at **1.00:1** — text the exact colour of its background — because
  the form-preview overlay is a *sibling* of the config panel, so no theming
  rule ever reached it;
- the emerald tint box introduced by 1.21.0's own SearchDropdown wave
  (1.40:1);
- the DISTINCT badge painting `text-primary` on `bg-primary` — 1.00:1 in
  **both** themes;
- bare `text-primary`/`text-red-600` that never received the `-lite` swap
  their neighbours had, and rail labels at 2.36:1.

Mirroring the sweep to **light mode** found 15 more, which is the point of
mirroring: `--ptah-line-control` never reached 3:1 against white, and two
row-style presets ("Cancelled"/"Urgent") failed in *every* theme — a
pre-existing bug no dark-mode audit could have caught. Genuinely inert
elements are exempted, as WCAG 2.1 exempts inactive components.

The auditor stays as a permanent browser guard: reintroducing a light box
with light text now fails the suite by element name and ratio.

### Fixed — `set_time_limit` was capping the whole process

`AiChatService` called `set_time_limit(300)` to give slow local AI providers
more room. In CLI that did the opposite: `artisan`, queue workers and PHPUnit
run with `max_execution_time = 0` (unlimited), so the call **imposed** a 300s
ceiling on the entire process — the timer started in the AI service and
expired minutes later inside an unrelated docblock parser or query grammar.
That is what had been killing full test runs at ~95% for days, and in a queue
worker it would have cut a long conversation in half. Now guarded on SAPI,
and it never lowers a limit the host already granted.

### Notes

- Two browser tests are skipped with full diagnoses recorded in the tests and
  in `docs/Testing.md`: DOM-driven Livewire bindings (`wire:click` inside a
  teleport, `wire:model.live` on the search input) never reach the server in
  the Dusk harness, while `$wire.set()` does a complete round-trip. A
  page-authored native `input` event fails identically, which rules out
  "synthetic events are not trusted". Root cause unidentified; the behaviour
  those tests covered is pinned server-side in the Feature suite.
- `NotificationsDocTest` fails when the documentation and the code disagree:
  every documented service method must exist, every `PTAH_*` env must be in
  the config, and every value that hides the bell must appear in the doc.

## [1.21.0] — 2026-08-22

Config-editor polish and the full SearchDropdown configuration surface.
No schema change — as in every release since 1.13.2.

### ⚠️ Behavior change — read before upgrading

Three searchdropdown column keys — `colsSDFilters`, `colsSDLabelTwo` and
`colsSDPlaceholder` — were **editable in the visual editor but dead at
runtime** for months. From this release they are honored: a config that
carries them will start filtering, showing a second label line, or using its
own placeholder on the next deploy, with no one touching the config. To audit
which screens are affected before upgrading, run against your database:

```php
// php artisan tinker — human execution, read-only
Ptah\Models\CrudConfig::all()->flatMap(fn ($c) => collect($c->config['cols'] ?? [])
    ->filter(fn ($col) => ($col['colsTipo'] ?? '') === 'searchdropdown'
        && (!empty($col['colsSDFilters']) || !empty($col['colsSDLabelTwo']) || !empty($col['colsSDPlaceholder'])))
    ->map(fn ($col) => $c->model.' :: '.$col['colsNomeFisico']))->values();
```

This is deliberately **not** a permanent `doctor` warning — it would flag
every legitimate future use of these keys forever.

### Added

- **The BaseCrud inline searchdropdown gains the component's full surface.**
  Background: BaseCrud never used the standalone SearchDropdown component —
  it has its own inline widget, and *three divergent `colsSD*` dialects*
  coexisted with eleven dead keys. The editor wrote keys the runtime never
  read, so configuring a searchdropdown's value/label/ordering/service mode
  through the editor was silently impossible. Now:
  - `sdSettings()` resolves all three dialects (canonical → editor → wizard)
    so every config ever written by any producer works, zero data migration;
  - `colsSDInitWithData` — open the dropdown without querying until the user
    types (the headline request);
  - `colsSDLabelTwo`/`colsSDLabelThree` item lines; builtin masks
    (`cnpj|cpf|money|phone|date`) via the new `Ptah\Support\SearchDropdownMask`
    (shared with the component by delegation, parity-pinned; the dynamic
    `Class::method` mask branches deliberately did NOT become
    config-editable); extra LIKE columns and fixed filters (identifiers
    guarded by `SqlIdentifier`, operators whitelisted, values always bound);
    `colsSDPlaceholder`; panel position;
  - the editor's SD tab appears only for searchdropdown columns and writes
    the canonical keys; CLI gains `sd_init_with_data`, `sd_label_three`,
    `sd_mask_one/two/three`, `sd_array_search`, `sd_start_list`,
    `sd_depends_on`, `sd_filter_column`; the wizard writes canonical keys and
    its free-form "WHERE filter" question was replaced by the validated JSON
    filter shape (a raw SQL fragment has no safe translation);
  - `ptah:config:doctor` gains seven searchdropdown checks with an idempotent
    `--fix` migrating legacy-dialect keys.
- **`forge-select` is optionally searchable** (`searchable` prop): filter
  input focused on open, diacritics-insensitive matching ("permissao" finds
  "Permissão"), arrows walk only visible options, double-Escape, empty-value
  options never filtered out, `aria-live` on the filtered list. The column
  permission select in the config editor uses it. Opt-in proven: no
  searchable artifact leaks into a non-searchable render.

### Fixed

- **Eight dark-mode contrast failures in the Configurar CRUD modal**, each
  measured with WCAG math before and after (worst: hover surfaces at 1.36:1,
  now 6.97:1) — light hover literals, the permission badge, text over the
  theme-invariant hint boxes, explicit placeholders. `hover:border-slate-300`
  was audited and deliberately kept (the token would regress it).
- Real-Chrome tests for the searchable select caught a real bug the
  structural tests could not: clicking the trigger bypassed `openList()`, so
  the filter never focused nor reset on the most common open path.

### Notes

- One browser test (modal save via synthetic WebDriver click) is skipped with
  a full diagnosis: under Chrome 151.0.7922.173 the synthetic click on the
  teleported save button delivers every DOM event but Livewire's binding does
  not fire. Human clicks and keyboard-driven `wire:model` work; server-side
  validation coverage lives in the Feature suite. See `docs/Testing.md`.
- Internal API: `HasCrudSearchDropdown::resolveSearchDropdownResults()`
  (protected) changed signature; `sdSettings()` is protected by design —
  public would be needless wire-callable surface.

## [1.20.0] — 2026-08-22

Column-level permissions for the BaseCrud, plus a full documentation audit.
No schema change — as in every release since 1.13.2.

### Added — column-level permissions

A `colsPermission` tag on any column in `crud_configs` restricts that column
to users granted the referenced `page_object` key (qualified
`page::section::obj_key` accepted, resolved literal-first). **No tag = public
= byte-identical behavior**, pinned by test; permissions module off = the tag
is ignored, like every other gate in the package.

- **The cut happens at the source**: `crudConfig` is `#[Locked]` and rebuilt
  from the DB on every request, so filtering it there is the per-request
  re-intersection for every derived reader — table, cards, quick search,
  filter/URL whitelists, form fields, export payload, print. What does not
  derive from it gets an explicit guard: a forged sort falls back to `id`,
  filters probing a denied column are dropped, `openEdit` unsets denied keys
  from the public payload, `renderLink`/custom renderers skip denied
  attributes, and totalizers / conditional row styles / custom filters /
  `groupBy`/`groupBreak` leave the config with the column (a group header
  renders the grouping value; a row color reveals the range).
- **File paths re-authorize at generation time** — a grant revoked between
  dispatching an export and the worker running still excludes the column.
  The synchronous PDF's totalizer recomputation now runs behind the gate,
  and the pre-existing fail-open (an empty column list made the exporter
  dump every attribute) is closed at both consumers: all columns denied
  means 403 (sync) or a failed export (queued), before any file is written.
- **Authoring**: the visual editor offers a select over the real, active
  `page_objects` (never free text — a typo'd key would mean "nobody sees
  it" silently), defaulting to "None (everyone sees it)", auto-emitting the
  qualified form on cross-page collisions, with a closed-by-default warning
  and a lock badge on the column list. CLI:
  `--column="cost:number:permission=page::viewCost"`. `ptah:config:doctor`
  warns on a tag pointing at a key no `page_object` has.
- **Cost**: the gate resolves every key on a screen in one pass over the
  memoized permission map — zero extra queries, zero audit rows (deliberate:
  N columns × N renders would flood the audit table; documented).
- Proven in real Chrome (Dusk): the denied user's page contains neither the
  column's header nor the value anywhere in the source.

### Fixed — documentation audit (the docs stop lying)

An audit of every doc claim against the code found 11 direct lies, 10 stale
mentions and 7 gaps — all corrected, the code being the only source of
truth. Highlights: the bundled skill's CLI section taught an almost entirely
fictional `--column`/`--filter`/`--action` syntax; two option tables were
invented in camelCase for keys the parser never knew; `ptah:docs` was
documented but does not exist; the README undercounted components and
appearance axes. New drift guards make the worst class unrepeatable:
`docs/Commands.md`'s option table is parsed and compared against the real
parser `$keyMap` in both directions, and the action-type enum is compared
against both the renderer and the JSON schema.

### Fixed — validator vs runtime (the house's oldest footgun, twice more)

- The schema validator accepted action types `wire/route/url/modal` while
  the runtime only executes `link|livewire|javascript` — aligned to the enum
  the renderer honors (no legacy configs used the dead types).
- The `sd_value`/`sd_label`/`sd_order_by` CLI shortcuts wrote keys the
  SearchDropdown runtime never reads — an accepted option that silently did
  nothing. They now write the keys the runtime actually consumes.

### Operational notes (human execution, per environment)

Seeding the permission keys (`ptah_page_objects`) and granting them per role
is done by a human through ptah's own permission screens — an example
snippet lives in `docs/Permissions.md`. Marking a currently-public column
hides it from everyone except masters until the grant is made: the default
is closed, by design.

## [1.19.0] — 2026-08-21

Fase 2.5: the four debts the August audit made non-deferrable, closed in one
release. No schema change — as in every release since 1.13.2.

### Security

- **`BaseCrud::$crudConfig` is `#[Locked]`** — the last query-governing public
  property still client-writable (an aging follow-up known since July). It
  governs cols, permission mapping, export limits, hooks and bulkActions; a
  forged `$wire.set` could override all of it. Every legitimate write is
  server-side and the editor's event→reload flow is regression-tested.

### Fixed

- The AI chat panel scrolls the conversation to the bottom when OPENED —
  `scrollToBottom()` only fired on send, so the widget opened showing the top
  of the history.

- **`ptah:config --style=` works for the first time.** Four row-style formats
  coexisted and only the runtime's worked: the CLI wrote to a key nothing
  reads, its validator demanded a shape its own parser never produced (every
  save aborted since introduction), the wizard emitted a third shape, and the
  docs described a fourth — including a claim it was "fixed" in March. One
  canonical shape now — `{field, condition (==,!=,>,<,>=,<=), value, style}`
  under `contitionStyles` (the typo stays: it is the de-facto contract of the
  editor, generator, model accessor and docs; `conditionStyles` is accepted as
  a read alias) — normalized by the new `Ptah\Support\StyleRule` at every
  boundary. Legacy `cols*`/`eq` entries keep working via on-read
  normalization; `ptah:config:doctor --fix` migrates them idempotently
  (**human execution, per environment**); a rule with empty CSS no longer
  shadows the rules after it.

### Added

- **Permission wave 5**, the prerequisites for running a large ERP on ptah's
  ACL:
  - `ptah_has_role()` / `PermissionService::hasRole()/getRoleNames()` — role
    as *identity*, never a gate (use `ptah_can()` to authorize). Master does
    NOT satisfy another role's name. Matching is lowercase+trim exact;
    separators are significant ("Vendas-SP" ≠ "vendas sp").
  - **Qualified permission keys** `page::obj_key` / `page::section::obj_key`
    disambiguate an `obj_key` that collides across pages — as plain strings
    that traverse the middleware, config identifiers and helpers with zero
    signature changes. Bare keys behave exactly as before (literal match wins
    first, everywhere); the bare permission map is byte-identical.
  - **`ptah:permission:why {user} {objKey}`** — prints the whole chain (roles,
    every page object with that key, the crossing binds) and the exact missing
    piece, with the verdict taken from the real `check()`. Auditing is
    disabled in memory during diagnosis; the company context is printed
    explicitly.
  - **`ptah:audit-prune`** — batched, truncate-free retention pruning for
    `ptah_permission_audits` (`--days`, `--chunk`, `--dry-run`; refuses
    `--days<1`; inline default 90 so older published configs stay safe).
    **Destructive; human execution, always `--dry-run` first.**
  - **Per-request memoization**: a warm permission gate drops from 9 cache
    reads to 0; flushed by the same version bumps every in-process mutation
    already fires, so immediate revocation semantics are unchanged.
- **A real-browser test suite** (`orchestra/testbench-dusk` + Chrome, its own
  `phpunit.dusk.xml`, excluded from the default run): twelve tests aimed at
  the bug class no file-reading test can see — hotkeys via real KeyboardEvents,
  modal Esc/aria-invalid, sidebar collapse/active pill, global density
  changing computed heights, toolbar label collapse, dark-theme computed
  backgrounds, and search-then-back persistence. Setup lessons (fixed
  `app.key` or `livewire.js` 404s; file session driver or "Page Expired";
  migrations inside `$app->booted()`) are recorded in `docs/Testing.md`.
- Quick-search persistence contract pinned end-to-end by tests (typed search
  survives a full remount and actually filters; clearing persists; guest
  session fallback holds).

## [1.18.0] — 2026-08-21

UX waves A–D, driven by screenshots and live testing from a real consumer
project. No schema change — as in every release since 1.13.2.

### Added

- **Global density and font size** as the 5th and 6th appearance axes in
  `/profile` (`data-ptah-density`: `compacta`/`confortavel`/`espacosa`;
  `data-ptah-fontsize`: `pequena`/`normal`/`grande`), riding the same
  whitelist/cookie/reset infrastructure as the other four. Each component
  family has its own density tokens (`--ptah-field-fs/-py`, `--ptah-bar-py`)
  whose *comfortable* value is that family's pre-density value — zero visual
  regression for users who never open the picker, pinned by a parity test.
- **Per-screen density can now follow the profile**: the BaseCrud toolbar's
  density dropdown gained "Padrão do perfil" (the new default). A persisted
  legacy `comfortable` migrates to it on load (it was the never-chosen
  default); persisted `compact`/`spacious` were deliberate and stay pinned.
- **Keyboard shortcuts**, discoverable via a `?` overlay: `/` focus search,
  `n` new record, `f` toggle filters, `v` table/cards, `r` reload, and
  `Ctrl+B`/`Cmd+B` collapses the sidebar (VSCode convention).
- **"Gerenciar Permissões" modal**: client-side filter over pages/objects and
  a per-page accordion (always opens collapsed; each header shows a live
  checked/total summary).
- **`<x-forge-empty>` and `<x-forge-skeleton>`** so custom screens get
  native-looking empty and loading states; the BaseCrud table's own empty
  state now uses the former.
- **`docs/CustomScreens.md`**: the token contract, the six axes, the
  `forge-*` catalog with real props, a full page recipe, the house traps
  (each with the guard that catches it) and a PR checklist. A doc guard
  fails when a published component is missing from it.

### Fixed

- **The ACL/module modals are legible in dark mode** — the v1.17.0 migration
  missed them; light slate bands under theme-following text measured as low
  as 1.13:1. Systematic sweep, semantic chip tokens
  (`--ptah-success/danger/warn-soft`), and the modal repaint scoped so it
  cannot leak into the BaseCrud's own modals.
- **Collapsed sidebar is genuinely icon-only**: labels leave the flow, icons
  center, group rail no longer renders squeezed, tooltips only while
  collapsed, and a group whose child is the active page wears the active
  pill. On tablet, tapping a group icon peeks the rail open (the old formula
  ignored user intent between 768 and 1024px).
- **Keyboard shortcuts actually work**: the "is a dialog open?" guard tested
  the `aria-modal` element's own computed display, which never changes when
  an ancestor is the hidden one — so every BaseCrud page reported a phantom
  open dialog and silently swallowed every key. `checkVisibility()` (with a
  `getClientRects()` fallback) tests rendered visibility through the chain.
- The six module screens report success/error through the global toast
  (pausing on hover/focus, WCAG 2.2.1) instead of an inline alert that
  scrolls out of view.
- `prefers-reduced-motion` keeps spinners spinning (slowly) instead of
  freezing them — a stopped spinner reads as a hung screen.

### Notes for the curious

- Bare `$wire`/`$el` inside `x-data` methods **work** — Alpine evaluates
  `x-data` under `with(scope)`, so closures resolve magics at call time. A
  reviewer and the maintainers both got this wrong in the same week; the
  codebase uses `this.$` as an explicitness convention, not a correctness
  fix. Recorded here so nobody "fixes" a healthy component again.

## [1.17.0] — 2026-08-21

Waves 2–4 of the full v1.15.2 audit (four independent reviewers: backend,
frontend, UX, security). No schema change — as in every release since 1.13.2.

### Fixed — functional

- **Bound `:title` / `:placeholder` / `:aria-label` with `__()` on plain HTML
  tags** emitted the raw string into the DOM; the page-level Alpine root then
  evaluated `__('…')` as JavaScript and threw a silent `ReferenceError`.
  Tooltips never rendered, the audit screen's search had no placeholder, and
  icon-only buttons had no accessible name. Fixed across the module screens
  (roles, companies, menu, audit, departments, pages, company-switcher) — the
  correct form is `title="{{ __('…') }}"`. Guarded by
  `BladeBoundAttributeOnPlainTagTest`, which flags a bare `__(` anywhere in a
  bound value on a non-`<x-…>` tag (ternaries included; `{{ }}` / `@js()` are
  exempt as server-compiled).
- **`forge-select` regenerated its root id with `uniqid()` on every render**,
  so Livewire's morph treated it as a new node — an open dropdown closed
  whenever any neighbouring field triggered a round-trip. Now a deterministic
  md5 id, the same cure `forge-input` already had.
- **PDF export totalizers never worked**: the lookup queried a column that does
  not exist (`model_name`) and the `QueryException` was swallowed, so the
  feature silently returned nothing since its introduction. The lookup now uses
  the real column and resolves like `ExportAuthorizer` (exact match, then
  canonical `ModelKey`), covering nested identifiers. **Behavioral change:**
  configs with `totalizadores.enabled` will see a totals row appear in PDF
  exports for the first time.
- **Excel formula-injection guard scoped to `=` only.** PhpSpreadsheet's
  writer only reinterprets values starting with `=` in a programmatic `.xlsx`;
  prefixing `+` / `-` / `@` (the broader OWASP CSV list) left a *visible*
  apostrophe in the cell, corrupting formatted phones (`+55 11 …`) and handles
  (`@user`) for zero security gain.
- **CLI parsers stopped truncating values at `:`** — `FilterParser`
  (`options=active:Active,inactive:Inactive` silently became `active`) and
  `JoinParser` (its own docblock example lost every `select=` column past the
  first alias) now use the tokenizer `ColumnParser` always had.
- `ptah:config-doctor` warns when two configs share a `permissionIdentifier`
  or two pages share an `obj_key` — permission resolution is global by key, so
  a collision grants cross-access. Diagnosis only; resolution unchanged.
- Bulk actions and `toggleSelectAll` use the model's real primary key
  (`getKeyName()`); with a custom PK, "select all" used to fill the selection
  with `null`s.
- 2FA **email-code sending is rate-limited** (3/min per user+IP, distinct
  limiter family from verification, so the buckets cannot collide).

### Fixed — theme & accessibility

- **`forge-alert` passes WCAG AA in light mode** (the old pairs measured
  1.9–3.1:1). New `--ptah-success-strong` / `--ptah-warn-strong` tokens join
  `danger-strong`; `AlertContrastTest` computes real luminance for all twelve
  pairs in both modes.
- **A field with a validation error is visibly different again.** The wrapper's
  unlayered border rule was flattening `border-red-400`; an
  `[aria-invalid="true"]` rule now outranks it, `forge-input` and the five
  inline modal field types expose `aria-invalid` / `aria-describedby`, and the
  message color passes 4.5:1.
- **`forge-modal` closes on Esc, traps focus** (`x-trap` ships inside
  Livewire 4's bundle — verified in the dist) and keys its title id off md5
  instead of `Str::random`.
- **`search-dropdown` is keyboard-operable** (combobox/listbox/option roles,
  arrows, Enter, Escape, `aria-activedescendant` cleared on collapse) and its
  panel/clear button follow the theme tokens.
- **The module screens (roles, companies, menu, audit, departments, pages,
  user×permission) follow the appearance presets in BOTH modes.** Their ~24
  chrome rules left the frozen layout `<style>` for tokenized CSS — the frozen
  block's ceilings dropped 57 literals/56 rules → 36/39, every site accounted
  for in the migration ledger.
- **The CrudConfig editor (2,830 lines) follows the theme, dark mode and the
  user's accent.** Its two in-view `<style>` blocks moved to tokenized CSS
  (`.ptah-cfg` / `.ptah-cfg-content` scopes; the always-dark nav rail is
  deliberately excluded); the indigo focus ring now derives from the accent.
- Pagination (`<nav aria-label>`, arrow labels, `aria-current="page"`),
  toolbar dropdowns (Esc + `aria-expanded`), sidebar (`aria-expanded`,
  `aria-current`, tokenized rail hairline).

### Added

- ~200 new behavioral tests (suite: 953 → 1177), including first-ever coverage
  for the CLI parsers, `ConfigValidator`, `CrudConfigEnums`, the CLI/flash
  error formatters and `GetSystemInfoTool` (asserting it does NOT expose
  framework/PHP versions unless `ptah.ai_agent.expose_system_details` is
  explicitly enabled).

### Known limitation (recorded, deliberately deferred)

- `ptah:config --style=` is inoperative end-to-end (pre-existing): the parser,
  the schema validator and the runtime disagree on keys and operator
  vocabulary. Unifying them is a design decision — tracked, not patched here.

## [1.16.0] — 2026-08-20

Wave 1 of the audit: the confirmed security criticals. Every fix replicates a
pattern that already existed in the package — the holes were omissions, not
missing infrastructure. No schema change.

### Security

- **Bulk actions enforce authorization and tenant scope.** `bulkDelete` /
  `bulkRestore` / `bulkForceDelete` / `executeBulkAction` ran on the raw
  client-writable selection with no `authorizeCrudAction()` and no
  `scopedQuery()` — any authenticated user could delete (or permanently
  force-delete) another company's records from the console. All four now gate
  and re-resolve ids through the scoped query; `executeBulkAction`
  re-resolves BEFORE handing ids to the configured service.
- **`bulkExport` no longer exfiltrates cross-tenant data** — the selection is
  intersected with `buildBaseQuery()` (same scope as the listing) before the
  export token is issued.
- **The six ACL screens re-assert master access on every Livewire request**
  (new `RequiresMasterAccess` trait called in `boot()`). Livewire 4 does not
  re-apply custom route middleware on subsequent component requests, so a user
  whose MASTER role was revoked mid-session could previously keep granting
  permissions on an open screen. `PtahMaster` is also registered as persistent
  middleware as reinforcement.
- **PDF export escapes every value it echoes** — the cell renderers used raw
  `echo` inside `@php`, so free-text fields reached DomPDF unescaped.
- **`SessionService::revokeSession()` requires the owning user** and filters
  by `user_id`. **BREAKING** for direct callers: the signature is now
  `revokeSession(string $sessionId, Authenticatable $user)`. Previously any
  authenticated user could terminate any other user's session by id.
- **`BaseCrud::$lockedFilters` is `#[Locked]`** — it is the master-detail
  security scope and was the only scope property still client-writable.
- **Dependencies patched** (dompdf 3.1.6, guzzle 7.15.3, commonmark 2.10,
  phpspreadsheet 1.30.6): `composer audit` is clean — was 21 advisories.

### Added

- 23 regression tests, each proven to fail without its fix (suite: 930 → 953).
  Two new test-only migrations live in `tests/migrations/` and are loaded
  exclusively by the test `TestCase` — never by the ServiceProvider.

## [1.15.2] — 2026-08-18

### Fixed

- **BaseCrud follows the theme and the density.** The appearance picker shipped
  in 1.15.0 reached the chrome but stopped at the working surface: the modal
  panel, filter panel and config form still carried hardcoded slate/white
  utilities. All three now paint from `--ptah-*` tokens, so every preset
  reaches them (the modal's transparency went with it).
- Toolbar controls share one height, padding and font size, driven by the
  density variables — search and buttons stop disagreeing.
- The search magnifier no longer overlaps the placeholder (`.ptah-c-control`
  skips `padding-inline` on inputs, which was clobbering `pl-9`).
- **Toolbar button labels collapse to icon-only ONLY when the row genuinely
  wraps**, measured on the rendered layout (vertical-centre comparison — with
  `items-center`, items of different heights sit at different `offsetTop` on
  the same line, which defeated naive detection) instead of guessed from a
  breakpoint.

## [1.15.1] — 2026-08-18

### Added

- **The theme survives logout.** The four appearance axes are mirrored into a
  sanitized, `httpOnly`, `SameSite=Lax` cookie, so the login screen renders in
  the user's chosen theme before authentication; on login the server-side
  preference wins over the cookie.
- **The auth screens follow the theme** — inputs and labels included.
- **"Voltar ao original"** reset button on the Aparência tab restores the
  four axes to the package defaults.

### Fixed

- Preferences load correctly at login (previously the defaults flashed until
  the first interaction re-applied the stored choice).

## [1.15.0] — 2026-08-17

> **Tagging note:** `1.13.3` and `1.14.0` below were never published as their own
> Composer tag — `main` was still at `1.13.2` when this release was cut. Both
> entries are kept exactly as written (they document distinct, independently
> completed pieces of work) and ship for the first time inside `1.15.0`. If you
> install via Composer you go straight from `1.13.2` to `1.15.0`; nothing in
> either entry was skipped, both are simply bundled here.

### Added — per-user Appearance (`/profile`, 6th tab)

Four independent axes, chosen per user and persisted server-side:

| Axis | Options (slugs) |
|---|---|
| Light tone | `puro`, `papel`, `nevoa` |
| Dark tone | `carvao`, `grafite`, `meianoite` |
| Accent | `azul`, `violeta`, `ciano`, `verde`, `teal`, `ambar`, `vermelho`, `rosa`, `cinza` (9) |
| Font colour | `suave`, `neutra`, `forte` |

Slugs are plain ASCII on purpose (`nevoa`, `carvao`, `meianoite` — no accents), since
they round-trip through a `data-ptah-*` HTML attribute and a JSON-cast preference
column. Persisted in `UserPreference` under key `theme`, group `appearance`, sanitised
against a server-side whitelist (`Ptah\Support\AppearancePresets`) before ever being
written back or rendered — an un-whitelisted value would leave every `var(--ptah-*)`
that depends on it invalid at computed-value time.

The four resulting values are rendered as `data-ptah-light|dark|accent|text` attributes
directly on `<html>` by the server (`forge-dashboard-layout`), so an authenticated
user's choice never flashes the default on load. Each pill in the Aparência tab previews
the option it represents (its own tone/scale/colour), not the currently active theme —
literal CSS per option, verified by a dedicated test (see Tests).

Building this surfaced and fixed several defects of its own before and shortly after
shipping, all in the same feature: the dark presets were inert because `.ptah-dark` was
also applied to `<body>` and the layout's root `<div>` (custom properties resolve per
element, so the bare `.ptah-dark` block on `body` won and inherited everywhere); the
accent only changed half the UI because a `theme-colors` partial pinned
`--ptah-primary` to a literal instead of deriving from `--color-primary`; the three
light tones were visually the same tone (`--ptah-surface`/`--ptah-canvas` were `#ffffff`
in all three, so only pill buttons moved); the font-colour axis was inert in light mode
because the token rule covering inherited text existed only under `.ptah-dark`; and
light mode in general had never been tokenised for the four biggest surfaces
(`main`, `forge-card`, `forge-sidebar`, `forge-navbar`) — a tone changed the buttons and
nothing else. All are fixed in this release (see Changed/Fixed below for the resulting
breaking/visual changes).

### Added — global toast host

The toast stack moved out of `base-crud` and into the layout (`<x-forge-toast-host>`),
listening on `window` for a `ptah-toast` event — any component can raise one now, not
just a BaseCrud screen. Undo changed from a direct `$wire.restoreRecord()` call to a
`ptah-toast-undo` window event that BaseCrud listens for.

> **Compatibility note:** if your host app overrode
> `resources/views/vendor/ptah/components/base-crud.blade.php` (or otherwise dropped
> the layout's toast host), it stops receiving toasts until `<x-forge-toast-host />` is
> re-included — re-publish or manually add it to your copy of the layout.

### Changed — behavioural breaking changes (no API changes)

1. **`.ptah-dark` (and the plain `.dark` marker) now live only on `<html>`.** They were
   also applied to `<body>` and the layout's root `<div>`; a host stylesheet that styled
   `body.dark …` or `.dark > div …` directly stops matching.
2. **`ptah:install` now injects `@import '.../ptah-components.css'` into the host's
   `app.css`** (documented in the `1.14.0` entry below) — installs from before that fix
   need to re-run `ptah:install` or add the import line by hand.
3. **`--ptah-primary-lite` moved from a 55% to a 45% white mix** (also `1.14.0`, an AA
   fix for the pair it inks four BaseCrud components with).
4. **The `ambar` accent changed from `#b45309` to `#92400e`.** The old value measured
   4.32:1 as ink against the package's new light backgrounds — under the 4.5:1 floor;
   the new value clears it at 6.10:1. The other 8 accents were unaffected.
5. **Sidebar and navbar borders now use `--ptah-line-strong` instead of `--ptah-line`**
   (17/255 more visible) — the value the surfaces' own dark-mode rules already used, so
   one token now backs the border in both scopes instead of two.
6. **Inherited text in light mode no longer resolves to the browser's pure black
   (`#000000`)** — a root rule now sets `color: var(--ptah-text-strong)` for light mode
   too (it previously existed only under `.ptah-dark`), so any element that declares no
   colour of its own (most BaseCrud table cells, among others) now follows the chosen
   font-colour preset instead of the browser default.
7. **`forge-card` (`default` variant), `forge-sidebar`, `forge-navbar`,
   `forge-page-header` and `forge-tab` stopped hardcoding Tailwind color utilities**
   (`bg-white`, `border-gray-100`, `text-gray-900`, `text-gray-500`, …) in favour of the
   `--ptah-*` tokens. A host that overrode these components by targeting those utility
   classes must now override the underlying tokens instead.

### Fixed — accessibility (WCAG AA / non-text 3:1, measured before and after)

| Element | Before | After |
|---|---|---|
| Row action icon "editar" (dark) | 2.05:1 | 7.40:1 |
| Row action icon "duplicar" (light) | 2.56:1 | 4.76:1 |
| Breadcrumb current item (no dark variant) | 2.05:1 | token-driven, both scopes |
| Breadcrumb link (no dark variant) | 3.69:1 | token-driven, both scopes |
| Navbar user chip — name | 1.35:1 | 6.97:1 |
| Navbar user chip — avatar initial (on hover) | 1.40:1 | 5.63:1 |
| Navbar user chip — chevron | 2.31:1 | 4.08:1 |
| Logout button label (light / dark) | 3.08:1 / 4.29:1 (never passed) | 6.11:1 (5.00:1 hover) / 6.87:1 (5.79:1 hover) |
| Admin dropdown icon | 2.54:1 | 4.76:1 |
| Sidebar menu item, child | 4.83:1 | 8.21:1 |
| Sidebar menu item, parent | 7.56:1 | 8.21:1 |
| Inactive tab, dark (no dark variant) | 3.03:1 | 5.71:1 |
| Active tab as text, dark | 1.68:1 | 5.06:1 |

Also: native form controls (`input[type=date]`, native `select`, scrollbars, autofill)
rendered with the OS **light** colour scheme outside BaseCrud screens, because
`color-scheme` was declared only inside a BaseCrud-scoped selector — now declared on
both `:root` and `.ptah-dark`, fixing every non-BaseCrud screen at once. The sticky
action column in dark mode used `--ptah-surface` while its row is transparent over a
darker page background, showing a visible seam; it now uses `--ptah-canvas` to match.

### Fixed — BaseCrud UI

- **Three different control heights in the same toolbar row** (buttons ~30px, per-page
  select ~34px, search box ~42px) unified through a density token
  (`--ptah-control-h/-px/-fs`) instead of matching paddings by hand.
- **"Espaçoso" (spacious) density rendered identical row padding to "Confortável"**
  (comfortable) — only font size changed. All three densities now differ in row
  padding as well.
- **The sticky action column** had a translucent header that let scrolling content show
  through, and no left edge to read as a pinned column — now opaque with a left
  border/shadow.
- **The four row-action icons used `p-2 -m-1`**, whose negative margin pushed each
  icon's hit area into its neighbour's; removed in favour of `gap`-based spacing.

### Fixed — authorisation: three defects where the admin acted and nothing happened

Found by auditing this package's permission system against the ERP it descends from,
ahead of rebuilding that ERP on top of ptah. All three share a shape: the administrator
performs an action, the screen confirms it, and the gate behaves as if nothing had
changed. That is worse than a loud failure — nobody goes looking for it.

- **Revoking a permission and then granting it again never took effect.**
  `RoleService::bindPageObject()` passed `['deleted_at' => null]` through
  `updateOrCreate()`, and `deleted_at` is not fillable, so `fill()` dropped it silently.
  Measured: grant → allowed, revoke → denied, **re-grant → still denied**, with the row
  stored as `can_read = true` *and* `deleted_at` set. The UI reported "Permissions
  updated." with the box ticked. Now uses the SoftDeletes API (`firstOrNew` + `restore`),
  the same technique `PermissionService::syncRole()` already used for `UserRole` — whose
  docblock documents this exact trap. No test had ever covered the cycle back.

- **The "Active" toggle on page objects and pages did nothing for authorisation.**
  `buildPermissionMap()` never filtered `page_objects.is_active`, and nothing read
  `ptah_pages.is_active` at all, so deactivating an object or a whole page revoked
  nothing. Worse, `buildMasterPermissionMap()` *did* filter, so the MASTER map excluded
  an inactive object while the ordinary map included it — `ptah_can()` and
  `ptah_permissions()` disagreed with each other for the same user. Both maps now apply
  the rule, an inactive page deactivates its objects, and `getCompaniesForResource()`
  follows the same rule (it feeds company selectors, and would otherwise offer branches
  for a resource the gate then refuses).
  **Behavioural change:** if you have deactivated objects or pages expecting them to stay
  reachable, they now deny. That is the intended meaning of the toggle.

- **Every queued export was refused.** `GenerateCrudExportJob` ran the gate inside a
  worker — no session, no `auth()` — so the user resolved to `null`, `allow_guest`
  defaults to `false`, and the job failed the export with "You are not allowed to export
  this data." For anyone, always. The authoriser now takes an explicit user and company,
  and the job passes the export's own `user_id` and `company_id`. Passing the user alone
  would not have been enough: with no session the company also resolves to `null`, and
  `null` here means `whereNull('company_id')` strictly, not "any company", so a user
  bound per branch would still have been refused.

Also: cache is now invalidated when a page object or page changes (observers existed for
roles, grants and assignments but not for these two, so a deleted object stayed permitted
until the 1h TTL expired), and a dead `Cache::forget()` in `CompanyService::setActive()`
naming keys from a superseded scheme was replaced with a real invalidation call.

### Changed — the CRUD config editor and AI config can finally be delegated

`ptah_can_manage_config()` and the AI model screen called `ptah_can(..., 'manage')`, but
`manage` was not in the action whitelist — so both were *always* `false` and the
capability was MASTER-only by construction, while the docs promised a `crud.config`
grant. The capability is now expressed as the **object**: `crud.config` and `ai.config`
are page objects, and holding `read` on one means you may configure it. A non-MASTER user
with that grant can now open the editor.

A `can_manage` column was implemented first and reverted. **The package's schema is
frozen: an update must never require a migration.** Installing ptah runs migrations and
that is fine; shipping a new one in an update is not, because existing installations will
not run `migrate` again — and because `loadMigrationsFrom()` makes package migrations
auto-discovered, so a new file executes on the consumer's next unrelated `php artisan
migrate` rather than waiting to be asked for. `SchemaIsFrozenTest` now pins the 17
migrations the package ships and fails on any addition or removal, with the alternatives
spelled out in its failure message. **This release adds no migrations.**

### Tests

Suite grew from ~695 (pre-`1.13.3`) to **907** (6204 assertions). The additions worth
calling out guard *classes* of defect, not one-off values:

- **Golden fixture + frozen origin + ledger** (`LayoutStyleBaselineTest`,
  `css-layout-origin.json`, `LayoutMigrationLedgerTest`) — makes a rule moving between
  the layout's inline `<style>` and `ptah-components.css` fail if its colour changes
  during the move, a case a diff alone cannot catch (both fixtures would otherwise
  regenerate together and both stay green).
- **Light/dark token parity** (`ThemeChromeOrphanTokenGuardTest`) — fails whenever a
  `.ptah-dark` rule declares a `--ptah-*` token with no light counterpart, with a
  documented, closed allowlist of legitimate exceptions.
- **Perceptible separation between tones and font scales**
  (`AppearancePresetContrastTest` additions) — a tone or scale that merely *passes
  contrast* without visibly differing from its neighbours now fails explicitly
  (consecutive font scales must separate by ≥ 12/255 per role; tone pairs by ≥ 10/255
  on canvas or surface).
- **Previews must be literal** (`AppearancePreviewLiteralTest`) — every pill option must
  have a rule, no such rule may reference a `var(--ptah-*)` token (which would make
  every option render identically), and tone previews specifically must not be scoped
  to `.ptah-dark`.
- **Double quotes inside an Alpine `x-data` attribute** (`LayoutXDataQuotingTest`) — a
  quote inside a comment or an attribute selector used to close the `x-data` string
  early and dump the entire Alpine object as visible text; now scanned across every
  view carrying `x-data`/`x-init`.
- **Single carrier of the theme** (`ThemeCarrierTest`) — pins `.ptah-dark`/the appearance
  `data-ptah-*` attributes to `<html>` only, so a regression that reintroduces them on
  `<body>` or a wrapper `<div>` (which would silently make dark presets inert) is
  caught immediately.

### Docs

- `docs/Configuration.md` — new "Per-user Appearance" section under
  `resources/css/ptah-components.css`: the four axes, the slug values, where the tab
  lives, how it persists, and that a user-chosen preset overrides the `:root`/
  `.ptah-dark` host override described earlier in that same section.
- `docs/KnownLimitations.md` — new section documenting the partial coverage of the
  theming work (measured, not estimated): how many color literals/rules remain in the
  layout's inline `<style>`, how many hardcoded text-color utilities remain across the
  views (and which two screens concentrate most of them), and that the whole Appearance
  axis has no effect at all under the Tailwind CDN fallback (no Vite build).
- `README.md` — one line in "Theming & customizing views" pointing at the new
  per-user Appearance tab.

## [1.14.0] — 2026-08-17

### Fixed — `ptah:install` never wired the host's `app.css` to Ptah's component stylesheet

`updateAppCss()` injected `@source`, `@custom-variant dark` and the brand `@theme` tokens into the
host's `resources/css/app.css`, but never the `@import` of `ptah-components.css` — the file that
defines every `.ptah-c-*` BaseCrud class and the 24 neutral `--ptah-*` tokens (`--ptah-surface`,
`--ptah-line`, `--ptah-text-*`, etc.). Since Tailwind v4 only compiles classes it can see from an
`@import`/`@source` chain, a fresh install shipped a host app with a **completely unstyled BaseCrud
UI** — tables, filters, modals — while the command reported every step, including `npm run build`,
as successful.

`updateAppCss()` now also injects:

```css
@import '../../vendor/jonytonet/ptah/resources/css/ptah-components.css';
```

right after `@import 'tailwindcss';` and before any `@theme` block, idempotently (safe to
re-run, never duplicates an existing import). `ptah-components.css` was chosen over the also
unused `resources/css/forge.css`: the latter re-declares `@import "tailwindcss"` internally, which
would double-import Tailwind in the host.

> **Upgrade note:** if you installed ptah before 1.14.0, your `app.css` is missing this import.
> Either re-run `php artisan ptah:install` (it will only add what's missing) or add the line above
> manually — see the "BaseCrud renders unstyled" entry in the Troubleshooting section of
> [docs/InstallationGuide.md](docs/InstallationGuide.md).

### Fixed — two defects in the same method, both surfaced by the import above

- **The `@source` for the package's views stopped being injected.** Its guard was
  `str_contains($content, 'vendor/jonytonet/ptah')`, and the new import line contains that exact
  substring — so from the moment the import existed, the guard was always true and the `@source`
  was never written again. Tailwind would then not scan Ptah's Blade files, and every utility class
  used *only* inside the package would be tree-shaken out of the host's bundle: a fresh install
  would still look broken, for a different reason than before. The guard now matches the views
  path specifically.
- **A host installed before this release gained nothing by re-running the command.** The method
  returns early when the brand tokens are already present, and that path never wrote to disk — so
  the import was computed in memory and discarded. This is the upgrade path for every existing
  installation, i.e. the case that decides whether the fix reaches anyone; it now persists before
  returning.

### Fixed — an AA failure that only the default configuration had

`--ptah-primary-lite` is the ink on a `--ptah-primary-soft-d` pill in four shipped components:
`.ptah-c-dd_item_sel` (selected dropdown row), `.ptah-c-btn_on` (active toggle),
`.ptah-c-active_badge` and `.ptah-c-saved_filter_btn`. At its original 55% white mix that pair
measured **4.47:1** against the `config/ptah.php` default primary (`#5b21b6`) — under the 4.5:1
floor for text.

It survived the 1.13.3 contrast sweep because `forge.css` defaults to a different primary
(`#1e40af`) which lands at 4.55:1: **the demo app passed while a stock install did not.** Both
sides of the pair derive from `--color-primary`, so neither ever appears as a hex in the CSS and no
amount of reading the stylesheet reveals the ratio — it has to be computed, which is why it went
unseen.

The mix is now 45%, giving 5.63:1 on the config default and 5.67:1 on the `forge.css` one. Contrast
is monotonic in that direction (the token is only ever ink or border on a dark ground), so no other
call site can regress; 25 resolved sites shift, all of them the same substitution. 50% would also
clear AA but leaves ~0.5 of margin, and `--color-primary` is host-configurable.

### Changed — the chrome's dark mode starts moving onto the theme tokens

The dashboard layout carried a 330-line inline `<style>` block that retrofitted dark mode onto the
chrome with 153 colour literals and no tokens, so a user-selected theme could never reach it. The
company switcher, the page root and `main` now take their colours from the `--ptah-*` neutrals
instead (`ptah-components.css`), and five orphan rules were deleted — nothing in the package carries
`.ptah-sidebar-toggle` or `.ptah-navbar-search`. The block is down to 127 literals / 107 rules.

Visible change: the switcher's active tab and hover were a frozen navy (`#1e40af`) that ignored
`PTAH_COLOR_PRIMARY` entirely. They now follow the configured brand, so a host on the default
config sees violet where it used to see blue. That is the fix, not a side effect.

Sidebar and navbar are next; the 21 rules that repaint Tailwind utility classes from a distance stay
put for now, because an inline `<style>` is unlayered and beats `@layer utilities` — moving those
without moving their views would change which rule wins.

### Tests
- New `InstallCommandUpdateAppCssTest` (7 cases) exercises `updateAppCss()` directly against a
  temp `app.css`: fresh file gets the import in the right position, a realistic fixture with
  pre-existing `@source` lines gets it immediately after `@import 'tailwindcss';`, running the
  command twice never duplicates it, an `app.css` that already has it is left byte-for-byte
  untouched, and a missing `app.css` still only warns. Two of the cases guard the defects above by
  their observable effect — both were verified to fail when the corresponding fix is reverted,
  rather than assumed to be covered.
- New `ContrastGuardTest::primary_lite_ink_on_a_primary_soft_pill_passes_aa_for_both_default_primaries`
  computes the pair for **both** default primaries over both dark grounds, reading the mix
  percentages out of the CSS so tuning either token re-runs the measurement instead of invalidating
  it. Verified to fail at the old 55%, reporting 4.4766:1 and naming the four affected components.
- New golden-fixture harness for the layout teardown: `LayoutStyleBaselineTest` (184 declaration
  sites plus a ceiling that only ever shrinks) and `LayoutMigrationLedgerTest`, which partitions a
  frozen pre-migration snapshot — every site that leaves the `<style>` block must be recorded as
  migrated verbatim, deliberately changed (with from/to/reason) or deleted (with a reason). Without
  the frozen snapshot, a rule moving between the two stylesheets regenerates both fixtures at once,
  so a colour change during the move would be indistinguishable from a faithful move; that scenario
  was reproduced deliberately and the ledger caught it while both fixtures were green.

## [1.13.3] — 2026-07-29

### Fixed — 12 contrast failures, some of which made controls invisible

A dedicated UI/UX audit (ahead of the upcoming theming work) found elements shipping below
WCAG AA — a few badly enough to be unusable. All ratios below were calculated, not estimated,
and a new test recomputes them from the source files so a revert breaks the suite.

| Element | Before | After | Minimum |
|---|---|---|---|
| Sort-direction arrow, idle (light / dark) | **1.41** / 1.94 | 4.55 / 5.71 | 3.0 (icon) |
| `forge-button color="warn"` | **2.15** | 6.81 | 4.5 |
| Search placeholder (light / dark) | 2.50 / 3.35 | 4.63 / 6.23 | 4.5 |
| Modal subtitle | 2.56 | 4.76 | 4.5 |
| Clear-filter icon, dropdown chevron, clear-search | 2.54 | 4.76 | 3.0 |
| Filter-panel muted text / cancel button | 2.54 | 4.76 | 4.5 |
| `forge-button color="success"` | 2.54 | 5.48 | 4.5 |
| Toast `success` / `danger` | 2.54 / 3.76 | 5.48 / 4.83 | 4.5 |
| Bulk-delete and discard buttons | 3.76 | 4.83 | 4.5 |
| `forge-button color="danger"` | 3.76 | 4.83 | 4.5 |
| "Columns" button, active | 3.07 | 4.84 | 4.5 |
| Delete-saved-filter (also moved off `rose` onto the `danger` role) | 3.34 | 5.91 | 4.5 |
| "Trash" button, active | 4.41 | 5.91 | 4.5 |

The **idle sort arrow at 1.41:1** meant the affordance telling you a column is sortable was
effectively invisible. `forge-button color="warn"` was white on amber at **2.15:1** — the same
mistake `forge-badge` already avoided by using dark text.

### Fixed — the solid button scales now darken coherently
`success` and `danger` had a resting colour that was *darker* than their own hover (or identical
to it), so hovering gave no feedback or moved the wrong way. Both now darken monotonically
(`success` 5.48 → 7.68 → 9.72; `danger` 4.83 → 6.47 → 8.31), and the `relief` variant stopped
hard-coding `text-white` for every colour — it follows each family, which also fixed amber
(3.19 → 4.59) and the light/secondary variant (**1.47** → 7.00).

> `warn` keeps hover and relief on the same amber: a third, darker step would push the contrast
> with its dark text back below 4.5 (darkening amber approaches the text's own luminance). Noted
> in the component.

### Tests
- New `ContrastGuardTest` (30 cases): extracts the colours from the CSS/Blade **by regex** and
  recomputes every ratio, plus two tests that compare relative luminance to pin that each button
  scale keeps darkening. Verified failing before the fix.

## [1.13.2] — 2026-07-28

### Changed — the generated controller no longer ships unreachable CRUD actions

`ptah:forge` emitted `create()`, `show()` and `edit()` returning
`view('{entity}.create'|'.show'|'.edit')`, but the generator only ever writes the
**index** view (BaseCrud handles create/edit/delete in a Livewire modal) and only
registers the **index** route — so those three actions could only ever throw
`View [{entity}.create] not found` if someone routed them. 1.13.0 silenced the
resulting static-analysis noise with a `@var view-string` annotation, which hid the
defect instead of removing it; they are now simply not generated.

The generated controller keeps `index`, `store`, `update` and `destroy`. If you need a
non-BaseCrud screen, add the action together with its view and route — the stub says so.

Affects newly generated controllers only; existing files are untouched (and are only
rewritten with `--force`). A test now pins that the controller references **only** views
the generator actually creates.

## [1.13.1] — 2026-07-28

Follow-ups found while scaffolding 14 real entities on 1.13.0.

### Fixed — the generated API routes were mounted under a doubled prefix

`Route::prefix()` was built from `ptah.api.prefix` **plus** `v1`, but the group is
written into `routes/api.php`, which Laravel already mounts under the app's api prefix
(`withRouting(apiPrefix:)`). The result was `api/api/v1/{resource}` — a 404 for every
documented URL. The group now adds **only** the version segment; `ptah.api.prefix`
describes the app's mount point for the Swagger URLs and the CLI messages, and is no
longer re-applied to the route.

### Fixed — the generated Swagger documented URLs that 404

Two independent defects stacked: `@OA\Server` carried `{APP_URL}/api/v1` **and** every
path carried `/api/v1/…`, and OpenAPI concatenates the two — documenting
`/api/v1/api/v1/{resource}`. The server URL is now the host only, and the paths honour
`ptah.api.prefix` instead of hard-coding `api`.

### Fixed — `string(60)` / `char(2)` had their length silently discarded

The field parser read the length and then threw it away, so every text column became
`varchar(255)` — while `decimal(p,s)` and `enum(a|b)` honoured their parentheses, making
the inconsistency invisible until you inspected the schema. `char` wasn't a supported type
at all (it fell through to `string`). Both now emit the declared width
(`$table->string('code', 20)`, `$table->char('uf', 2)`), the generated validation rule
follows it (`max:20` instead of a blanket `max:255`), and `--db` keeps the real column
width when reading an existing table.

> This mattered beyond disk: under InnoDB/utf8mb4 an indexed `varchar(255)` costs 1020
> bytes against a 3072-byte per-index limit, so a composite index over three such columns
> overflowed — blocking exactly the composite indexes the post-forge checklist asks you to
> add by hand.

### Fixed — the embedded skill taught a method that doesn't exist

`ptah-development` documented `$this->service->getDados($request)` in five places (the
name was renamed to `getData` long ago) and described the generated routes as
`routes/web/{folder}/…` + `routes/api/{folder}/…`, paths that never existed. An agent
reading the skill would reintroduce the bug the 1.13.0 stub fix had just removed.
Re-publish with `--tag=ptah-skills` to pick this up.

## [1.13.0] — 2026-07-28

> **🔒 Security — if you generated API scaffolding with `ptah:forge --api` before this
> release, audit `routes/web.php` now.** Look for `Route::prefix('v1')->group(...)` blocks with
> `Route::apiResource(...)`: those endpoints (including `DELETE`) were registered **without any
> authentication middleware**. Move them to `routes/api.php` under
> `->middleware(config('ptah.api.middleware'))`, or delete and re-generate with this version.

### Fixed — `ptah:forge --api` generated unauthenticated API routes (CRITICAL)

The API route generator wrote `Route::apiResource(...)` inside a bare `Route::prefix('v1')`
group with **no middleware at all**, so all five endpoints — `index`, `store`, `show`, `update`
and **`destroy`** — were publicly reachable. Three defects compounded:

- **No authentication.** The web route generated in the same run *does* get
  `->middleware('auth')`; the API route got nothing. A `DELETE /v1/{resource}/{id}` with no
  credentials would have deleted the record.
- **Written to `routes/web.php`.** `routes/api.php` doesn't exist in a fresh Laravel 11+ app
  (it comes from `php artisan install:api`), and the generator silently fell back to `web.php`.
  Even with auth added, those routes inherit the **`web`** group: session + CSRF (so a real API
  client gets **419** on any write) and no `api` group (no `throttle:api`, and a 500 renders an
  HTML error page instead of JSON).
- **`config('ptah.api.prefix')` and `config('ptah.api.middleware')` were dead config** —
  declared, documented, and consumed nowhere in the package. Worse than absent: it looked like
  the routes were protected.

Now: the generated group applies **`config('ptah.api.middleware')`** (default
`['api', 'auth:sanctum']`) and **`config('ptah.api.prefix')`** (so the URL is `api/v1/…`, which
finally matches both `docs/BaseLayer.md` and the `@OA\Server` of the published `SwaggerInfo` —
previously the Swagger UI documented a URL that 404'd). An empty/misconfigured middleware value
falls back to the safe default rather than emitting an open route.

**The `web.php` fallback was removed.** If `routes/api.php` is missing, `--api` now **fails with
an actionable error** (`run "php artisan install:api" first`) and generates no routes, instead of
quietly shipping unauthenticated endpoints. This also resolves the `auth:sanctum` /
`laravel/sanctum` mismatch: `install:api` installs Sanctum, so the default guard exists by the
time API routes can be generated. If the `sanctum` guard is later removed, the generated file
carries an inline warning while staying protected.

### Fixed — every `--api` entity 500'd on `index`

`controller.api.stub` called `$this->service->getDados($request)`; the method on `BaseService`
is **`getData()`** (a leftover from the pt→en rename — the docs already said `getData`). This
broke the `index` of 100% of entities generated with `--api`. Swept the remaining stubs for
other pt-named service/repository calls: none left.

### Fixed — typing of generated code (PHPStan/Larastan noise)

Running Larastan level 5 over a project whose `app/` was entirely generated produced 331 errors
that the developer never wrote and can't fix without editing generated files. Fixed at the stubs:

- `resource.stub` now carries `@mixin \App\Models\{Entity}` (respects sub-folders) — clears 261
  errors at once and improves IDE autocomplete.
- `controller.stub` annotates the view name as `view-string` (42 errors).
- `dto.stub` documents `$request` as a `FormRequest` before calling `validated()` (14 errors) and
  marks the class `@final` for the `new static()` diagnostic (14 errors).
  > The obvious fix — type-hinting `fromRequest(FormRequest $request)` — was **rejected**: PHP
  > requires parameter contravariance, and `BaseDTO::fromRequest()` is declared with `Request`,
  > so narrowing it raises a **fatal error when the generated class loads** — strictly worse than
  > the original issue, which only surfaced when calling it with a non-`FormRequest`.

> **Stubs already published** (`stubs/ptah/`) are not updated automatically — re-publish with
> `php artisan vendor:publish --tag=ptah-stubs --force` to pick up the stub fixes above.

### Tests
- The API route generator: middleware + prefix applied, custom prefix honoured, **no route
  written and an error returned when `routes/api.php` is absent** (proving no open endpoint and
  no `web.php` pollution), and the sanctum-guard warning in both directions. Plus stub-content
  guards (`getData` not `getDados`, `@mixin` in resources incl. sub-folders, the DTO annotation).

## [1.12.0] — 2026-07-28

Eight issues found while bootstrapping a brand-new project from scratch
(`create-project` → `ptah:install` → modules → `ptah:forge` → the published Docker setup).
Three of them completely blocked the path they document.

> **⬆️ Upgrade action — only if you published the Docker stubs:**
> `docker compose down` (the old containers become orphans), then
> `php artisan vendor:publish --tag=ptah-docker --force`.
> **Container names change** from `${APP_NAME}_app` to `<directory>-app-1` — use
> `docker compose exec app …` (which is what the docs already use). No action needed if you
> never published `ptah-docker`.

### Fixed — the published Docker environment could not build or start

- **`pecl install redis` failed, so the image never built.** `php:8.3-fpm-alpine` ships no
  build toolchain and the stub's `apk add` didn't include one, so `phpize` aborted
  (`docker-php-ext-install` was fine — it manages its own toolchain; only the PECL step was
  affected). The `redis` step now installs `$PHPIZE_DEPS` as a virtual package and removes it
  afterwards, keeping the final image slim.
- **`container_name: ${APP_NAME:-ptah}_app` produced invalid names.** `APP_NAME` is free text
  ("B2B Engepeças"), while Docker only allows `[a-zA-Z0-9][a-zA-Z0-9_.-]` — and the failure
  only surfaced *after* the whole build, when creating the containers. `container_name` was
  removed from all five services; Compose now derives (and sanitises) the names from the
  project name. A dedicated `APP_SLUG` was considered and rejected: it doesn't remove the
  failure mode (a slug can also contain spaces) and a fixed default would collide between two
  Ptah projects on the same machine.

### Fixed — `ptah:module api` / `ai-agent` left the module half-installed

`activateApi()` (and `activateAiAgent()`) ran `composer require` with a hard-coded 300 s
timeout and no idempotency check. On slow I/O the `ProcessTimedOutException` propagated out of
the task, so the **remaining steps never ran** (the two `vendor:publish` calls and the
placeholder substitution in `SwaggerInfo.php`) and `PTAH_MODULE_API=true` was never written —
leaving the module inactive even when the package was already there. Now: the require is
skipped when the vendor directory already exists (re-running is safe), the timeout is
configurable via **`ptah.process_timeout`** (`PTAH_PROCESS_TIMEOUT`, default 300 — also applied
to `npm install`/`npm run build` in `ptah:install`), failures are contained so the publishing
steps always run, and a failed install ends with an actionable warning instead of silence.

### Fixed — `<x-forge-tabs>` array mode crashed the component showcase

The component read `$tab['id']` while `/ptah-forge-demo` passed `key`, so the demo page — the
reference used to build screens — returned **HTTP 500** (`Undefined array key "id"`). Each tab
now accepts `id` (canonical) **or** `key`, falling back to `tab-{index}`, so copying either
shape works. The demo itself was also fixed: besides the wrong key it used named
`<x-slot:*>` blocks that array mode never renders (it expects `$tab['slot']`), so the panels
would have been empty. Two more latent demo bugs surfaced while making it render and were
fixed as well: `<x-forge-list>` expects `badge` as an array (`['label' => …, 'color' => …]`),
and `<x-forge-table>` expects `headers` — the demo passed `columns`, which is not a declared
prop and was silently ignored, rendering a table with no header row.

### Fixed — `ptah:module permissions` printed an admin password that didn't work

Re-running the module (e.g. after `ptah:install`, which the "Next steps" recommend) generated
and printed a fresh password under the banner *"Admin created successfully!"*, but the existing
user's password was preserved — so the credential shown was invalid, and a strong password was
leaked into terminals and CI logs for nothing. The password is now only shown after
`Hash::check` confirms it actually applies; otherwise the command says the admin already exists
and its password was preserved. (The seeder itself was correct — it only printed on creation;
the banner in the command was unconditional.)

### Fixed — `storage:link` failure reported as success

On filesystems without symlink/junction support (network shares, some mounted volumes) the OS
error was printed but the task still reported success and `public/storage` didn't exist —
uploads then 404'd far from the cause. The install now verifies the link and, when missing,
prints an actionable warning (run from a local disk, or point the `public` disk at
`public_path('storage')`). It does not fail the install.

### Changed — opcache is enabled by default in the Docker stub

The stub shipped `PHP_OPCACHE_ENABLE: 0` plus `opcache.revalidate_freq = 0`, which on a Docker
Desktop bind mount meant recompiling every request (measured: 8.5 s vs 2.5-4 s per request on
the same route). Now opcache is on with `revalidate_freq` defaulting to **2 s**
(`validate_timestamps` stays on, so hot reload still works — changes appear within 2 s). Both
are overridable: `PHP_OPCACHE_ENABLE=0` / `PHP_OPCACHE_REVALIDATE_FREQ=0` in `.env`.

### Docs
- Removed `--soft-delete` from `AI_Guide.md` — the flag never existed (soft deletes are the
  default; `--no-soft-deletes` opts out). It was the only non-existent flag in the docs.
- `Configuration.md` now documents **`config/ptah.php`** (key → env → default → reference), which
  was previously undocumented — every `PTAH_*` variable in one table.
- Corrected the documented default of `PTAH_MENU_DRIVER` (`database`, not `config`) — in the
  docs **and** in the command output that the docs mirror.
- The `HasUserPreferences` trait is now presented as **optional** (it's a convenience API on
  your model — BaseCrud persists preferences without it; it has no call sites in the package).
- `ptah:forge --fields` help now warns that a bare `decimal` silently becomes `decimal(10,2)`.
- `darkaonline/l5-swagger` added to `composer.json` `suggest`.

### Tests
- New: Docker stub guards (toolchain in the PECL step, no `container_name`, opcache defaults),
  `forge-tabs` array mode (`id`, `key`, index fallback, `defaultTab`), the whole forge-demo view
  rendering without throwing, and a seeder test pinning that a re-run **never** overwrites an
  existing admin's password.

## [1.11.2] — 2026-07-25

### Fixed — `<x-forge-select>` fell back to the placeholder after a re-render

A select bound with `wire:model` but **without** `:selected` showed its placeholder again
after any Livewire re-render, even though the value was still applied (display-only — the
filter kept filtering, the form kept the value). `$initialSelected` was seeded **only** from
the `:selected` prop, while the Alpine↔Livewire bridge is one-way (Alpine → Livewire), so
every re-evaluation of `x-data` reset the label to nothing.

It now seeds from the bound Livewire property (server-side, dot-paths supported) when
`:selected` is omitted. `:selected` keeps absolute precedence, so existing usage is unchanged.

This affected **10 of the 13 built-in call sites** — not just the BaseCrud filter panel, but
also **edit modals** that showed "Select…" instead of the stored value (company `tax_type`,
page-object `obj_type`, role `department_id`, and the user-permission role/company selects).

> Two obvious-looking fixes were **rejected** on purpose, and are worth knowing if you plan
> to touch this component:
> - **`@entangle`** would break the BaseCrud filters: `Alpine.entangle` bails out entirely
>   when the Livewire property is `undefined`, and `$filters` starts as `[]` with no
>   pre-seeded keys — the select would stop working altogether, not just look wrong.
> - **A stable `id` + `wire:key`** would freeze the three call sites that rely on `:selected`:
>   the node swap caused by the random `id` is currently the *only* path that re-populates
>   Alpine from the server, which is exactly why `:selected` works when an edit modal opens.
>   The `uniqid()` therefore stays intentionally — replacing it requires substituting that
>   repopulation path first.

`multiple` + `wire:model` remains unsupported (the bridge sends a JSON string, not an array);
no call site combines them. Documented in the component.

> **If you published Ptah views**, re-publish to get this fix:
> `php artisan vendor:publish --tag=ptah-views-components --force`

## [1.11.1] — 2026-07-25

### Fixed — boolean columns and the visual config editor

Six bugs found in real use (a 90-screen ERP), all in shared paths. Four of them below,
plus the focus-loss and mojibake fixes further down:

- **A boolean column always rendered "Não" in the listing.** `formatCell()` applied the
  `colsSelect` value→label map (`1` → "Sim") **before** the renderer, and `renderBoolean()`
  matches the raw value strictly (`1`, `'1'`, `true`, …) — so the mapped label never matched
  and every row fell through to the false badge. The map is now skipped when the effective
  renderer is `boolean` (explicit `colsRenderer: "boolean"` or the legacy
  `colsHelper: "yesOrNot"` that `ptah:forge` generates), so the renderer sees the raw value.
  This affected **every boolean column scaffolded by `ptah:forge`**, plus cards, print and
  export (all go through `formatCell`). Columns **without** a boolean renderer keep mapping
  labels exactly as before (`badge`/`pill`/`money`/… unchanged).
  > If you had a boolean column with custom `colsSelect` labels, it now shows
  > `colsRendererBoolTrue`/`colsRendererBoolFalse` (or `ptah::ui.bool_yes`/`bool_no`).
  > Set those keys — or drop the boolean renderer — to keep custom wording.
- **A boolean field had no control in the create/edit modal** (it fell through to a free-text
  input, so editing it appeared to do nothing). `colsTipo: "boolean"` now renders the Yes/No
  select — the same control the config preview already promised — and persists `1`/`0` both
  ways. (`ptah:config` generates `colsTipo: "boolean"`; `ptah:forge` generates
  `colsTipo: "select"` — both work now.)
- **The visual config editor could not save at all.** `ConfigSchemaValidator` used
  `isset($col['colsRenderer'])`, which is `true` for `""` — and the editor writes `""` as the
  default for a new column (and for the "None" renderer option). Any column added through the
  editor therefore threw `ConfigValidationException: Invalid column type ""`. Empty
  `colsTipo`/`colsRenderer` are now treated as absent, matching what the runtime already did.
  The error message also names the right key now (`renderer` vs `column type`).
- **Saving a config with a JOIN also threw.** The validator required `colsTipo`/`colsTable`/
  `colsOn`, but both the runtime (`applyJoins`) and the editor (`addJoin`) use
  `type`/`table`/`first`/`second`. The validator now follows the runtime (and still accepts the
  legacy shape), so a config the runtime can execute is never rejected. Invalid joins
  (unknown type, duplicate table, missing table) are still rejected.

### Fixed — a live-search input lost focus while typing

`<x-forge-input>` generated its `id` with `uniqid()`, i.e. a **different id on every render**.
Livewire's DOM-diff uses `el.id` as the morph key when there is no `wire:id`/`wire:key`, so a
changing id made the morph **remove and re-create the input** — the focused element vanished
mid-typing on any `wire:model.live` field. The id is now derived from the field's identity
(stable across renders), and an input **without** a label emits no `id` at all (nothing to key
on → the node is patched in place).

This affected **9 built-in screens** — the menu, company, role and user-permission searches,
the BaseCrud toolbar search, three filter-panel inputs and the calculated-field input — and any
consuming app using `<x-forge-input wire:model.live>`. Fixed at the root, so no call site
changed.

> Known related issue, deliberately not changed here: `<x-forge-select>` has the same random-id
> pattern, but its re-initialisation is load-bearing (it's what populates the select when an
> edit modal opens). Fixing it needs a per-call-site review and is tracked separately.

### Fixed — mojibake (`â€"`) in shipped sources

A formatting pass back in March re-encoded three files as if they were CP1252, leaving 2,576
double-encoded byte sequences (`—`, `─`, `═`, `→`, accented letters…) plus one UTF-8 BOM. The
user-visible symptom was `â€"` instead of `—` in the config editor's column table. Bytes are
repaired (pure substitution — not a single line added or removed) and a **regression guard test**
now fails the suite if double-encoded sequences or a BOM reappear in `.php`/`.blade.php` sources.

### Tests
- Regression tests for all six (each verified failing before its fix): boolean renderer vs the
  select map (explicit renderer and legacy helper), a guard that `badge` on a select column still
  uses the mapped label, the boolean form control + persistence both ways, empty/invalid
  `colsTipo`/`colsRenderer`, the editor's `addField → save` round trip, JOIN validation in both
  shapes, source-encoding/BOM guards, and `<x-forge-input>` id stability across renders.

> **If you published Ptah views**, re-publish to get these fixes:
> `php artisan vendor:publish --tag=ptah-views-components --force` (for `<x-forge-input>`) and/or
> `--tag=ptah-views-base-crud --force` (for the config editor and the boolean form control).

## [1.11.0] — 2026-07-20

> **⬆️ Upgrade action required:** run `php artisan migrate` to create the new
> `ptah_exports` table (ships with the package). Without it, the queued export
> transparently degrades to the synchronous download — nothing breaks, but the
> background/panel mode stays off until you migrate. Needs a real queue
> (`QUEUE_CONNECTION` ≠ `sync`) + a running worker to process in the background.

### Added — BaseCrud large-volume export (queued) + "Exportações" panel

A background export mode for large datasets, alongside the existing synchronous
export. The file is generated on the queue and downloaded from a per-user panel —
so a big Excel no longer blocks the request or times out.

- **`queueExport($format)`** on BaseCrud (opt-in via `exportConfig.asyncExport.enabled`).
  It resolves the filtered/ordered **ids** through the same `buildBaseQuery()` as the
  listing (so it respects exactly the active filters/search/company scope), snapshots
  them into a `ptah_exports` row, and dispatches `GenerateCrudExportJob`. The job only
  generates the file from those ids — the query is **never** rebuilt outside Livewire.
- **Auto-degrade.** With `queue.default=sync` or no `ptah_exports` table, `queueExport`
  falls back to the synchronous download (with a toast); the toolbar shows a "needs a
  queue" hint. A package never assumes a worker is running.
- **`ExportsPanel`** Livewire component (`<livewire:ptah-exports-panel />`): lists the
  user's exports with status badges, download and remove; polls only while something is
  queued/processing.
- **`ptah:export-prune`** removes expired exports (and stale queued/processing/failed
  rows past the TTL) plus their files.
- **Config** `ptah.export` (`disk`, `path`, `ttl_hours`, `async_max_rows`) and a new
  `exportConfig.asyncExport` block (`enabled`/`excel`/`pdf`, default off).
- **`exportConfig` is now validated** by `ConfigSchemaValidator` when present (absent
  section = valid, non-breaking).

### Security
- The queued export enforces the **same allowlist + read-permission gate** as the
  synchronous download, extracted to `Ptah\Services\Export\ExportAuthorizer` and applied
  **inside the job before any file is generated**. The export always records the
  **resolved** model class (the one that collected the ids), never a client-supplied
  value, and `BaseCrud::$model` / `$companyFilter` are now `#[Locked]` — closing a
  cross-model / cross-tenant leak where a forged request could pair one screen's scoped
  ids with another model's data. Download re-checks ownership **and** the active company.

### Database
- New migration `create_ptah_exports_table` (**prepared, not run** — execute
  `php artisan migrate` in each environment). Consistent with the other `ptah_*` tables.

### Tests
- 40+ tests across `tests/Feature/Export/`: queue dispatch, auto-degrade (sync / no
  table), job file generation + failure, **cross-model/allowlist refusal (no file
  written)**, `#[Locked]` client-mutation guards, multi-tenant id scoping, gated download
  (owner / permission / expiry / **active-company**), panel, prune, schema validation.

## [1.10.1] — 2026-07-18

### Fixed
- **`ptah:forge` — humanised index title.** The generated `index.blade.php` used the
  raw StudlyCase entity name for the page title, `<h1>` and breadcrumb (e.g.
  `ProductStock`). It now runs through `LabelHumanizer` (the same engine used for
  column labels — camelCase split + pt-BR dictionary/accents), so the title reads
  `Product Stock` / `Usuário` etc. The Livewire `model` identifier is unchanged
  (stays canonical). Affects newly generated views only.

> If you published `stubs/ptah/view.index.stub`, re-sync it (it now uses the new
> `{{ entity_title }}` placeholder) to pick up the humanised title.

## [1.10.0] — 2026-07-18

### Added — `<x-forge-modal>` native `wire:model` support (dual-mode)

`<x-forge-modal>` now accepts `wire:model` (and modifiers, e.g. `wire:model.live`)
and manages its own open/close state via `@entangle`, so it no longer requires a
parent `<div x-data="{ open: @entangle('prop') }">` wrapper:

```blade
<x-forge-button wire:click="$set('showX', true)">Open</x-forge-button>
<x-forge-modal wire:model="showX" title="…"> … </x-forge-modal>
```

- **Backward-compatible.** Without `wire:model`, the component behaves exactly as
  before — it reads `open` from the parent Alpine scope; no `x-data` is emitted, so
  the existing wrapper pattern (used by every built-in Ptah modal) is unchanged.
- The two modes are mutually exclusive (don't wrap the `wire:model` form in a parent
  `x-data` — the modal's own scope would shadow the parent's `open`); documented in
  the component.
- `wire:model` is kept off the root element's attribute spread; `wire:modelable` does
  **not** trigger the self-contained mode.

> **Clarification re v1.5.0:** the v1.5.0 accessibility pass did **not** remove
> `wire:model` from `<x-forge-modal>` — git history shows the component never read
> `wire:model`; it always required `open` from the parent scope, and the a11y pass
> only added `role`/`aria-*`/`focus-visible` without touching the open mechanism.
> Projects that used `<x-forge-modal wire:model="…">` were relying on unsupported
> behavior; this release adds that support natively.

### Tests
- `ForgeModalDualModeTest`: parent-scope mode emits no `x-data`; `wire:model` mode
  emits `@entangle` and drops `wire:model` from the root; `.live` modifier passes
  through; `wire:modelable` does not trigger self-contained mode.

## [1.9.1] — 2026-07-17

### Changed — standalone SearchDropdown internals

Refinement of the v1.9.0 relation-label feature.

- The `_raw` row returned by `search()` is now clean — the internal
  `_ptahLabel*` helper keys (used to carry a relation label resolved off the Model
  past `toArray()`'s relation-key snake-casing) are no longer mixed into it; they
  ride as siblings of `_raw`.
- `selectedItem()` reads the full result item and is **backward-compatible**: it
  accepts either the full item or a bare `_raw` row (`$item['_raw'] ?? $item`, type-
  guarded), so a stale published view never dispatches null — at worst a camelCase
  relation label degrades to an empty string for that stale-view case (value and
  plain labels always resolve). The dispatched `label` remains the **raw**
  (unmasked) value — now locked by a test.

> **Upgrade note:** if you published the standalone dropdown view
> (`resources/views/vendor/ptah/livewire/search-dropdown/search-dropdown.blade.php`),
> re-publish it (`--tag=ptah-views --force`) so it passes the full item to
> `selectedItem`. Not required if you didn't publish that view.

### Tests
- `selectedItem` now covered for plain labels, the raw-vs-masked dispatch contract,
  camelCase relations, labelTwo/labelThree, and stale-blade backward-compat.

## [1.9.0] — 2026-07-17

### Added — SearchDropdown (standalone) label from a relation column

The standalone `<x-ptah-search-dropdown>` component now accepts dot-notation for a
relation column in `label` / `labelTwo` / `labelThree` (model-mode), matching the
inline BaseCrud dropdown shipped in v1.8.0 — for entities whose display value lives
on a related model (e.g. `Client → user.name`).

- Label resolved via `data_get` **on the Model** (before `toArray()`), so relations
  named in camelCase (`ownerCompany`) resolve correctly — reading from the array
  would fail because `toArray()` snake-cases relation keys.
- Search filters via `orWhereHas` (relation column guarded by `SqlIdentifier::isSafe`).
- Relation is eager-loaded (`with`) to avoid N+1.
- **Limitation:** the standalone orders by a raw `orderByRaw` independent of the
  label, so there's no auto-fallback for a relation-column order (unlike the inline
  one) — order by a base-model column or use service-mode. Documented.

Additive/non-breaking: a plain `label` (no dot) keeps the column-limited `select`
and behaves exactly as before.

### Removed

- **`Ptah\Commands\Config\ConfigAssembler`** and **`Ptah\Models\CrudConfig::permissions()`**
  — dead code with no callers anywhere in the package (verified). `ConfigAssembler`
  additionally emitted a flat permissions structure incompatible with the runtime
  (which reads the nested `config['permissions']`). Neither was wired, documented, or
  functional; no external consumers. If you referenced either directly, drop the
  reference (the runtime never used them).

### Tests
- Standalone relation label + search, a **camelCase relation** (regression guard for
  the `toArray()` snake-case pitfall), plain-label regression, no-match, and a
  malicious-relation-column security case.

## [1.8.0] — 2026-07-17

### Added — SearchDropdown label from a relation column (model-mode)

`colsSDLabel` now accepts dot-notation for a relation column (e.g. `"user.name"`,
nested `"a.b.name"` supported) in model-mode — for entities whose display name
lives on a related model (a common "profile" pattern, e.g. `Client → user.name`).
Previously this silently produced an empty dropdown: model-mode read
`$item->{'user.name'}` (which doesn't traverse the relation) and searched/ordered
on a non-existent column, and the resulting SQL error was swallowed.

- Label resolved via `data_get` (traverses the relation; identical for plain labels).
- Search filters via `whereHas` (`LOWER(column) LIKE ?`, case-insensitive) — the
  relation column is guarded by `SqlIdentifier::isSafe` before interpolation and the
  term is bound; the relation path is resolved by Eloquent, never raw SQL.
- The relation is eager-loaded (`with`) to avoid N+1.
- **Limitation:** ordering by a relation column isn't supported (would need a JOIN);
  it falls back to `colsSDValor`. Use service-mode for relation ordering / complex
  JOINs. Documented in `docs/SearchDropdown.md`.

Additive and non-breaking: a plain `colsSDLabel` (no dot) behaves exactly as before.
The standalone `SearchDropdown` component is unchanged (separate surface).

### Tests
- Relation label + search, listing, no-match, plain-label regression, a
  table-qualified base-model order column preserved, and a security case (a
  malicious relation column is rejected by the guard, no error).

## [1.7.0] — 2026-07-17

### ⚠️ Security — config-driven RBAC gate was silently open by default

The runtime reads the per-screen RBAC gate from `permissions.permissionIdentifier`
(`authorizeCrudAction`, `getEffectivePermissions`, `ExportController`), but both
config producers — `ptah:forge` and the visual config editor — wrote the key under
`permissions.identifier`. Because `authorizeCrudAction` is **fail-open** when the key
is absent, **every screen generated or edited before this release ran without a gate**
whenever the permissions module was enabled. (The module is off by default, so only
installs that turned it on were affected — e.g. real ERPs relying on the gate.)

- **Producers canonicalised on `permissionIdentifier`.** `CrudConfigGenerator` and the
  visual editor now write the canonical key; the editor reads `permissionIdentifier ??
  identifier ?? default`, so opening and saving a legacy screen migrates it without
  losing the value. Model default aligned. **The runtime read side did not change** —
  no breaking change there.
- **`ptah:config:doctor` detects the legacy key** (config with `identifier` set but
  `permissionIdentifier` empty → error, non-zero exit) and **`--fix` migrates it**.

> **Upgrade (permissions module users), in order, staging first:** `ptah:config:doctor`
> → `ptah:config:doctor --fix` → `ptah:permission:sync --role=… --grant=…`. Step 2 flips
> the affected screens from fail-open to fail-closed, so non-master users lose access
> **until** step 3 grants it. Master users are unaffected. See `docs/Commands.md`.

### Added — ACL & config lifecycle tooling

- **`ptah:permission:sync`** — registers the RBAC objects (`PtahPage` + `PageObject`)
  the engine matches against, derived from the existing `crud_configs`, so delegating
  access to non-master roles no longer needs hand-seeding screen by screen. `--role`/
  `--grant` (`create,read,update,delete` or `all`) grants in one step via
  `RoleService::bindPageObject`; `--dry-run` previews. Idempotent, single transaction.
- **`ptah:config:relabel`** — re-humanises existing column labels through
  `LabelHumanizer` (fixes unaccented pt-BR labels like `Situacao` → `Situação`). Guard
  only relabels the unaccented form of the humanised label, so **custom labels are never
  overwritten** (`--all` bypasses the guard); `--dry-run` default, confirmation before
  writing, single transaction.
- **`ptah:config:doctor`** route-ambiguity message rewritten to explain the
  global-vs-route-specific fallback model.

### Tests
- Legacy-RBAC-key detection + `--fix`; editor round-trip migration (mount+save on a
  legacy config); `permission:sync` (creation, grant, idempotency, dry-run, mid-batch
  rollback); `relabel` (accent heuristic, custom-label preservation, rollback).

## [1.6.0] — 2026-07-16

### BaseCrud — URL filters (`?f[...]`) + export "Limite" badge

Ported from the ERP that ptah grew out of. Additive, non-breaking.

- **Filters via URL (`?f[field]=value`).** Open a BaseCrud screen already
  filtered from a link — for cross-screen navigation carrying context. Supports
  multiple filters, explicit operators (`?f[name][op]=LIKE&f[name][val]=ACME`),
  lists/`IN` (`?f[status][]=1&f[status][]=2`) and `BETWEEN`. Operators:
  `=,!=,>,>=,<,<=,LIKE,NOT LIKE,IN,NOT IN,BETWEEN`.
  - URL filters **override** saved preferences while active, are **never
    persisted**, and are dropped the moment the user touches the filter panel.
  - A yellow **"Filtros do link"** banner (`role="status"`) lists the active
    link filters with a **Clear** button.
  - **Whitelist:** only `colsIsFilterable` columns + custom-filter fields are
    accepted; any other name is ignored.
  - **Security:** the `urlFilters` property is `#[Locked]` (the client cannot
    rewrite it via a Livewire payload to bypass the whitelist); the
    company/branch scope and locked filters are applied independently, so a
    forged `?f[company_id]=…` can only narrow within scope, never escape it.
    Non-scalar smuggled values (`?f[x][val][][y]=1`) are discarded; `BETWEEN` on
    a non-numeric/date column is discarded rather than silently mismatched.
- **Export "Limite" badge.** The Export button shows a warning badge when the
  filtered total exceeds `exportConfig.maxRows` (suppressed on grouped
  listings, where `total()` counts groups).

### Fixed
- `ArrayFilterStrategy` / `RelationFilterStrategy` (direct-FK branch) now guard
  the field identifier with `SqlIdentifier::isSafe()`, matching the text/numeric
  strategies — hardening now that URL filters expose these paths to GET requests.
- `forge-alert` gained dark-mode variants (`dark:`) for every colour scheme; the
  warn alert is now legible on a dark background.

### Tests
- `CrudUrlFiltersTest` (19 tests: formats, whitelist, precedence, `#[Locked]`
  client-mutation guard, non-scalar smuggling, `BETWEEN` type resolution) and
  `CrudExportLimitBadgeTest`; `FilterStrategySecurityTest` extended.

### Docs
- `BaseCrud.md` documents URL filters (formats, behaviour, security, link
  generation) and the export "Limite" badge.

## [1.5.0] — 2026-07-16

### Config lifecycle — export / import / doctor + canonical keys

First-class tooling for versioning and auditing BaseCrud configs (they live only
in the DB), addressing the operational gaps hit while building a real ERP on ptah.

- **`ptah:config:export-all [path]`** / **`ptah:config:import-all [path]`** — dump
  every `crud_configs` row to a versionable directory (one pretty JSON per
  model/route, default `database/ptah/crud-configs`) and rebuild them on a fresh
  DB. `model`/`route` are read from file content, so the folder is git-diffable and
  the import is idempotent (doubles as a seeding step).
- **`ptah:config:doctor [--fix]`** — audits all configs and surfaces the silent
  failures the per-model tooling can't: **orphan (non-canonical) keys**, **malformed
  configs** (via `ConfigSchemaValidator`), **empty screens** (0 columns), and
  **route ambiguity** (a model with both a global and a route-specific config).
  Non-zero exit on errors (CI-friendly); `--fix` rewrites orphan keys.
- **Canonical model key (fixes the FQCN×slash footgun).** `ptah:config` now accepts
  either the FQCN (`App\Models\Catalog\Product`) or the runtime key
  (`Catalog/Product`) and always stores under the canonical key BaseCrud actually
  reads — so it no longer writes orphan rows the runtime never loads. New
  `Ptah\Support\ModelKey::canonical()` is the single source of truth (shared by
  forge, config and doctor).
- **Unified pt-BR label generation.** `ptah:forge` and `ptah:config` now derive
  column labels through one `Ptah\Support\LabelHumanizer` — it strips the `_id`
  marker consistently (no more "Category Id"), applies a built-in pt-BR dictionary
  (accented `Usuário`, `Observações`, `CNPJ`, …) and is extensible via
  `config('ptah.crud.label_dictionary')`.
- **Translation overrides without freezing.** New `ptah-lang-overrides` publish tag
  drops a minimal starter at `lang/vendor/ptah/{locale}/ui.php` where you list only
  the `ptah::ui.*` keys you change; Laravel merges the rest (and future keys) from
  the package. Publishing the full `ptah-lang` file (which freezes all keys) is no
  longer the only way to customise strings.

### UX/UI & accessibility (full interface review — sidebar → BaseCrud)

A dedicated UX pass covering the Forge components, the BaseCrud screens
(toolbar, table, cards, modal/form, filters) and the layout CSS.

- **Keyboard + screen-reader support across the Forge components.**
  `<x-forge-select>` is now a real combobox (`role`, `aria-expanded`,
  `aria-controls`, arrow/enter/space/escape navigation, active-option highlight);
  `<x-forge-modal>` exposes `role="dialog"` + `aria-modal` + `aria-labelledby`;
  `<x-forge-tabs>` (array mode) implements the tablist/tab/tabpanel pattern with
  roving `tabindex` and arrow-key navigation. Every interactive control gained a
  visible `focus-visible` ring.
- **Everything runs on the theme tokens.** Hardcoded blue focus states (inputs,
  select, filter panel) now derive from the configured primary via `color-mix()`
  tints — re-theming actually re-themes the whole UI. A `@custom-variant dark`
  makes `dark:` follow the `.ptah-dark` toggle instead of only the OS preference.
- **Contrast (WCAG AA).** Muted text (empty-state subtitle, card id, date-range
  labels) darkened in light mode; warn toasts, badges and avatars switched to dark
  text on amber (white failed); filter and count badges recolored to semantic
  tokens (`bg-primary`/`bg-warn`) instead of raw blue/amber utilities.
- **Focus & structure fixes.** Modal form fields no longer use a positive
  `tabindex` (which jumped focus ahead of the footer); the sticky action column now
  tracks row hover/selected state so the brand tint runs edge-to-edge instead of
  leaving a seam; grid cards use a dedicated `.ptah-c-card` surface rather than the
  table-wrapper class; `prefers-reduced-motion` disables animations.
- **Translatable microcopy.** Component close/aria labels (alert, modal,
  notification, spinner, password toggle) are now `ptah::ui.*` keys (pt-BR + en).
- **Back-compat `<x-forge-alert>` aliases.** Accepts `type="warning|info|error"`
  and `:dismissible` (mapped to `color`/`closable`), matching how the ERP screens
  already call it — covered by a new test.

### Tests
- ModelKey, LabelHumanizer, ConfigDoctor, and export-all/import-all covered;
  ConfigCommand tests assert the canonical-key storage. New forge-alert alias test.

### Docs
- `Commands.md` documents the three new commands + the canonical-key behaviour;
  `Configuration.md` replaces the hand-rolled export/import bash scripts with the
  native bulk commands; the README command table now lists `ptah:config` and the
  config-lifecycle commands.

## [1.4.1] — 2026-07-09

### Fixed — permission engine (found while expanding test coverage)
- **Deactivating a role now revokes access.** `buildPermissionMap()` — the source
  of truth for `check()` — did not filter `role.is_active`, so an inactive role
  kept granting permissions (inconsistent with `isMaster()`/`queryPermission()`,
  which already filtered it). Now only active roles grant.
- **Audit logging matched its documented intent.** `check()` had the condition
  inverted (`! $result || audit_denied`): granted accesses were logged only when
  `audit_denied` was on, and denials were logged even when it was off. Now `audit`
  logs granted accesses and `audit_denied` additionally logs denials, as the
  config documents.
- **Re-granting a removed role works again.** `syncRole()` tried to restore a
  soft-deleted assignment via a mass-assigned `deleted_at => null`, which Eloquent
  silently drops (not fillable), leaving the row trashed. It now restores through
  the SoftDeletes API.

### Tests
- 21 new permission tests across three files: engine state (inactive/soft-deleted
  assignments, `allow_guest`, `getPermissions()` shape, `getCompaniesForResource()`,
  `syncRole`/`detachRole`, cache scope), audit trail, and the `ptah.can` middleware.

### Docs
- `docs/Permissions.md` synced with reality: corrected the `PermissionServiceContract`
  signatures (`$user`-first `check`, `syncRole(array $companyIds)`, `detachRole`), the
  audit semantics (audit is the master switch; `audit_denied` adds denials), the
  generation-based `clearCache` (not cache tags), the `ptah.master` middleware and the
  master-gated ACL routes, and the real defaults (`cache_ttl=3600`, `multi_company=true`).

## [1.4.0] — 2026-07-03

### Security — audit batch 3 (medium/low)
- **Class-based lifecycle hooks are namespace-restricted.** A `@Class::method`
  hook in CrudConfig may now only instantiate classes under an allowed prefix
  (`ptah.crud.hook_namespaces`, default `App\CrudHooks`) — a crafted config can no
  longer instantiate an arbitrary class as a gadget. Short hook names resolve
  under the first allowed namespace.
- **Image uploads validate the real MIME type.** The client extension is
  spoofable, so image columns now check `getMimeType()` — the file must be an
  actual `image/*` and **never `image/svg+xml`** (scriptable → stored XSS on the
  public disk). Runs regardless of `colsUploadAllowedTypes`.
- **ACL screens are master-only.** New `ptah.master` middleware guards the
  permission-management routes (`/ptah-roles`, `/ptah-pages`, `/ptah-users-acl`,
  `/ptah-audit`, `/ptah-departments`, `/ptah-permission-guide`), which previously
  only required `auth`. **Breaking:** non-master authenticated users now get 403.

### Cleanup
- Removed dead/misleading config keys: `ptah.crud.export_driver` and
  `ptah.company.model` / `ptah.company.table` (never read — the model/table are
  fixed). `docs/Company.md` corrected to stop promising a configurable model/table.

## [1.3.0] — 2026-07-03

### Export — reworked to the print-style token model
- **Export now matches the listing exactly.** `BaseCrud::export()` /
  `bulkExport()` build the row set through the shared `buildBaseQuery()` /
  `applyGroupingAndSort()` (same search / filters / company scope / sort as the
  table), collect the ordered ids up to `exportConfig.maxRows`, and cache them
  under a short-lived, **user-scoped token**. The new
  `GET /ptah/export/download/{token}` (`ExportController::download`) resolves the
  model **server-side**, re-checks the CRUD allowlist + permission, and generates
  the file. The client no longer names a model or reapplies filters.
- **Removed** the naive `ExportController::applyFilters()` (ignored operators /
  relations / search / company scope), the duplicated request-based
  `export()`/`bulkExport()` controller actions and the `/ptah/export` +
  `/ptah/export/bulk` routes. A single `ptah:export-download` browser event
  replaces `ptah:export-sync` + `ptah:bulk-export`.
- **Removed** the dead async path (`asyncThreshold` / `Ptah\Jobs\BaseCrudExportJob`
  never shipped). Exports are synchronous, bounded by `exportConfig.maxRows`
  (default 5000). Sorting by a relation column degrades to primary-key order in
  the file.
- 4 tests rewritten for `download` (404 / 403-owner / allowlist / happy path).

### Skills — shipped and installable
- The package now bundles three agent skills under `resources/boost/skills`:
  `ptah-development` (conventions), **`ptah-scaffold`** (describe an entity/table →
  full CRUD runbook) and **`ptah-data-layer`** (BaseRepository / BaseService /
  BaseDTO usage + the `getData()` contract).
- New **`ptah-skills`** publish tag copies them into the app's `.claude/skills`,
  and `ptah:install --boost` now publishes them too (Laravel Boost does **not**
  auto-discover third-party package skills — the misleading success message was
  corrected).

## [1.2.1] — 2026-07-03

### Fixed
- **`ptah:config --list` crashed** with `Undefined array key "cacheTtl"` on any
  configuration that lacked `cacheTtl`/`cacheEnabled` (e.g. one built purely via
  declarative `--column` options). `TableFormatter::formatGeneral()` now guards
  both keys.

### Tests / docs
- First tests for the previously-untested **`ptah:config`** subsystem:
  `ColumnParser` (the `field:type:modifier:key=value` DSL) and `ConfigCommand`
  declarative `--non-interactive` mode (persist, `--set` casting, `--dry-run`,
  invalid model, `--list`).
- Fixed the bundled **`ptah-development` skill**: scaffolding examples used the
  non-existent `--soft-delete` flag (SoftDeletes is ON by default; disable with
  `--no-soft-deletes`).

## [1.2.0] — 2026-07-03

Security hardening from the audit + access-controlled config editor.

### ⚠️ BREAKING — CRUD config editor is now access-controlled

- The in-app configuration editor (`ptah-crud-config`) previously had **no
  authorization**: it was rendered for everyone and its `save()` was reachable by
  name, so anyone could persist joins / lifecycle hooks / link templates / custom
  methods (the inputs that feed the SQL and render sinks). It is now gated by
  `ptah_can_manage_config()`:
  - **permissions module ON** → master user or a `crud.config` **manage** grant;
  - **permissions module OFF** → the new `ptah.crud.config_editor` flag
    (`PTAH_CONFIG_EDITOR`), which **defaults to `false` (deny)**.
- **Upgrade note:** installs that use the editor **without** the permissions
  module must set `PTAH_CONFIG_EDITOR=true` (or publish the updated config) to
  restore access. The toolbar hides the trigger and both `openModal()`,
  `previewForm()` and `save()` re-check on the server.

### Security & correctness — hardening (batch 1)

- **SearchDropdown config properties are now `#[Locked]`.** `modelClass`,
  `serviceClass`, `useService`, `orderByRaw`, `value`, `label*`, `arraySearch`,
  `dataFilter`, `limit`, `mask*`, `listens` and `coringa` are server-set at mount
  and were client-mutable via the Livewire payload — turning them into SQLi
  (`orderByRaw`), arbitrary class/method execution (`serviceClass`+`useService`)
  and arbitrary-model/column exfiltration (`modelClass`+`label`) vectors. They are
  now locked; only the search term (a method argument) is user input.
- **Renderer XSS/scheme hardening.** `helperFlagChannel` now escapes its fallback
  (was stored XSS via `{!! !!}`); `renderLink` blocks `javascript:`/`data:`/
  `vbscript:` hrefs and escapes the URL; `renderQrcode` carries the value in a
  `data-` attribute (read via `$el.dataset.qr`) instead of interpolating it into a
  JS string literal.
- **SQL-identifier guards** added to the remaining config/client-driven column
  names: JOIN `select` aliases (raw `AS`), `configGroupBy`, totalizador columns
  (`sum/avg/…`), the `quickDateColumn` public property and `NumericFilterStrategy`
  (now matches `TextFilterStrategy`). Unsafe identifiers are silently discarded.
- **IDOR guard on single-record actions.** `openEdit`, `duplicateRecord`, the
  update path of `save()`, `deleteRecord` and `restoreRecord` now confine the
  client-supplied id to the current tenant (`companyFilter`) and master/detail
  lock (`lockedFilters`) via the new `scopedQuery()`/`recordInScope()` — the
  listing was scoped but these were not, so a crafted id could reach another
  company's rows.
- **Export endpoint allowlist + permission gate.** `/ptah/export` (and
  `/ptah/export/bulk`) now refuse any model that is not configured as a Ptah CRUD
  (blocks arbitrary `?model=User` dumps) and enforce the CRUD's `read` permission
  when the permissions module is active.

### Fixed
- **`afterCreate`/`afterUpdate` redirect was silently dropped.** `save()` called
  the global `redirect()` helper and discarded the result, so a hook returning a
  `RedirectResponse` never navigated. It now uses `$this->redirect()`.

## [1.1.1] — 2026-06-24

### Fixed
- **Config form preview crashed on `select` fields (HTTP 500).** Any BaseCrud
  screen whose `CrudConfig` had a savable `select` column returned a 500 for
  admins, because the preview iterated `colsSelect` as an array while the edit
  state holds it as a string (`"label;value;;…"`). The `foreach` over a string
  threw `foreach() argument must be of type array|object, string given`, and
  since the preview overlay is rendered (hidden via `x-show`, not `@if`), the
  page failed to load even with the preview closed.
  - `previewFormCols()` now returns `colsSelect` in array form (the edit-state
    string is left untouched so the editable input keeps working).
  - Extracted the string→array parsing into a shared `parseColsSelect()` helper
    reused by both the save path and the preview, so the two can't diverge again.
- 2 regression tests: `previewFormCols()` normalises the select string to an
  array (and leaves the edit state a string); opening the preview renders the
  `<option>`s without error.

## [1.1.0] — 2026-06-24

Dedicated print screen + nested relationship paths + shared query builder.

### Print screen (`/ptah/print`)
- **New: a dedicated print view** opened from the export menu. Unlike the old
  `window.print()` (which only printed the current paginated page), it renders
  **all filtered records** (up to `exportConfig.maxRows`, default 5000) on a clean,
  chrome-free HTML page in a new tab — ready to `Print` or `Copy (Excel)`.
  - **Same data as the listing**: the snapshot is built by the BaseCrud component
    itself (`printView()`), reusing the shared query and `formatCell()`, so badges,
    money, dates, select labels and nested relations render identically and respect
    the active search/filters/company scope. The component caches a ready payload
    under a short-lived, user-scoped token; `CrudPrintController` only displays it
    (no filter logic in the controller → it can never diverge).
  - **Totals footer**: totalizadores are shown per column, computed over the full
    filtered set (SQL aggregate, before the `maxRows` cut).
  - **Copy (Excel)**: copies the table as `text/html` (pastes into Excel / Google
    Sheets as a real table with split columns) plus a `text/plain` TSV fallback;
    uses the Clipboard API with an `execCommand('copy')` fallback.
  - A truncation note is shown when the result exceeds `maxRows`.

### Shared query builder — totals & export now honor every filter
- **Refactored**: extracted `buildBaseQuery()` + `applyGroupingAndSort()` from
  `rows()`; the listing, totals, export and print now all build the query through
  the **same single source of truth**.
- **Fixed (latent bug)**: `totalizadoresData()` previously applied only the form
  filters + date ranges, so the **footer totals could disagree with the visible
  rows** whenever a global search or company filter was active. Totals now reflect
  the exact same filtered query as the listing (search, company, locked, whereHas,
  quick date and custom filters all included).

### Filters/columns — nested relationship paths (`a.b`)
- **Fixed: a column whose `colsRelacao` is a nested path** (e.g.
  `purchaseIncomingInvoices.expeditionReceivingStatus` + `colsRelacaoExibe: name`)
  was broken on three fronts; now fully supported:
  - **Render** (`formatCell`): a dotted `colsRelacao` is resolved with `data_get`
    down the whole chain instead of a single magic-property lookup of the literal
    `"a.b"` key (which rendered empty).
  - **Filter** (`buildActiveFilters`): a selected id no longer filters the root FK
    (which points at the intermediate model, not the final one). Nested paths now
    always go through `whereHas` — numeric id matches the related primary key
    (`colsSDValor`, default `id`), text searches `colsRelacaoExibe` — both via
    Eloquent's native dotted `whereHas`. Single-level columns keep the FK shortcut.
  - **Sort**: nested relation columns are skipped by the relation-JOIN sort
    (`getOrderByRelationInfo` bails on a dotted path) to avoid an invalid table
    name / broken JOIN.
  - Eager loading already handled the dotted path (`with('a.b')`).
- 4 new tests: nested render via `data_get`, nested filter routing (numeric →
  whereHas on the related key, text → display column) and nested-column sort skip.

## [1.0.2] — 2026-06-11

Filter engine fixes + Laravel 13 compatibility.

### Compatibility — Laravel 13 / Livewire 4 / PHP 8.4
- Production constraints already allowed `laravel/framework: ^11|^12|^13` and
  `livewire/livewire: ^4.0`; this leg makes the **toolchain and CI actually cover
  Laravel 13**: `require-dev` widened to `orchestra/testbench: ^9|^10|^11` and
  `phpunit/phpunit: ^11|^12`, and the test matrix gained a Laravel 13 / Testbench 11
  job on PHP 8.4. README badge and requirements updated to 11 · 12 · 13.
- Bumped `guzzlehttp/guzzle` (≥7.12.1) and `guzzlehttp/psr7` (≥2.12.1) in the lock
  to clear three medium CVEs (transitive dev deps; `composer audit` now clean).

### Filters — relationship filtering fix + NULL operators + hardening
- **Fixed: filtering a relationship column by text was broken.** A column with
  `colsRelacao` + `colsRelacaoExibe` was filtered directly on the FK as text
  (`where fk LIKE '%name%'`), which never matches (the FK holds ids). Now
  `buildActiveFilters()` routes by value: a numeric value filters the FK directly
  (`where fk = id`, `!=` supported), and a text value searches the related display
  column via `whereHas(relation, col LIKE …)` through `RelationFilterStrategy`.
- **New: `IS NULL` / `IS NOT NULL` operators** — filter by a column with no value
  (e.g. "orders without an invoice"). Centralised in `FilterService::applyFilter()`
  so they work for every column type and inside AND **and** OR groups
  (`FilterService::NULL_OPERATORS` + `isNullOperator()`), guarded by
  `SqlIdentifier`. `FilterDTO::isValid()` accepts a value-less NULL filter;
  `buildActiveFilters()` keeps a filter that carries only a NULL operator;
  the filter panel exposes the two operators and disables the value input.
- **New: searchdropdown filters support `=` and `!=`** — the filter-panel
  searchdropdown gained an operator select, and `selectFilterDropdownOption()` now
  preserves a user-chosen operator instead of forcing `=` (e.g. "status different
  from finished"). The `!=` path reuses the relation FK-id branch. (Caveat noted in
  code: `fk != id` also excludes rows with a NULL FK.)
- **Hardening: an empty/"select…" operator no longer becomes an invalid clause.**
  `FilterDTO::fromArray()` and `buildActiveFilters()` normalise an empty or
  non-string operator to `=` (Laravel silently discards `where col '' value`).
- The `empty('0')` class of bug was already absent in ptah (strict `=== null/''`
  comparisons throughout); a regression test now locks that in.
- 19 new tests: NULL-operator behaviour (incl. OR groups + SQLi guard), FilterDTO
  operator normalisation / validity / `'0'` regression, and `buildActiveFilters`
  relation routing (numeric FK vs text whereHas) + NULL + empty-operator paths.

## [1.0.1] — 2026-06-11

Developer-experience release: theme your brand colors from config, preview the
form while configuring it, and publish views surgically.

### Theming — config-driven brand colors
- **New `config('ptah.theme.colors')`** (primary/success/danger/warn/dark, each
  with an `.env` override like `PTAH_COLOR_PRIMARY`). Ptah injects them as CSS
  custom properties (`--color-primary`, `--ptah-primary`, …) in the dashboard and
  auth layout `<head>` via a shared `partials/theme-colors` view. Because
  `ptah-components.css` derives every tint/ring/hover from `--color-primary` with
  `color-mix()`, setting one value rebrands the whole UI — no view publishing, and
  it survives `composer update`. The CDN-fallback Tailwind config in both layouts
  now reads the same config values, so colors are consistent with or without a
  Vite build.

### View publishing — granular tags (footgun prevention)
- Added **granular publish tags** so you no longer have to publish all 60+ views
  at once: `ptah-views-components`, `ptah-views-base-crud`, `ptah-views-auth`,
  `ptah-views-ai`. The blanket `ptah-views` remains but is documented as a last
  resort. Publishing a view means Laravel prefers your copy and `composer update`
  never refreshes it — the granular tags + a code comment + a README section make
  that trade-off explicit, steering devs to publish only what they edit (or
  nothing, since most customization is database-driven via CrudConfig).

### Config modal — inert form preview
- **"Preview form" button** in the CrudConfig modal footer opens an inert mirror
  of the create/edit form, built from the columns currently marked as savable
  (unsaved `formEditFields`): section headings (`colsFormBlock`), required marks,
  help text, per-type controls and the cascade gating hint — all disabled, no
  data binding, no validation, no queries, no actions. Lets the dev see the form
  layout while building it.
- **Discoverability fix:** moved `colsFormBlock` and `colsOnChange` from the
  "Mask" sub-tab (where they were easy to miss) to the "Basic" sub-tab of the
  column editor. Cascade fields stay in the "SearchDropdown" sub-tab.
- New `CrudConfig::previewForm()/closePreview()/previewFormCols()`, the
  `_config-form-preview.blade.php` partial, 7 i18n keys (en/pt_BR) and
  `ConfigFormPreviewTest` (3 tests).

## [1.0.0] — 2026-06-11

First public stable release on Packagist. Consolidates everything below: SOLID
scaffolding (`ptah:forge`), the database-driven BaseCrud (filters, master/detail,
group breaks, calculated fields, cascading dropdowns, card view, export, print),
the auth/permissions/menu/company/api/ai_agent modules, a security-reviewed
permission engine, the AI streaming chat, 339 passing tests, CI on PHP 8.2–8.4 ×
Laravel 11/12, and a brand-driven, dark-mode-ready UI.

### AI Agent module — streaming & token accounting
- **Streaming responses** — the chat widget now streams the answer token-by-token
  via a new `AiChatService::stream()` and Livewire `wire:stream`. Toggle with
  `ptah.ai_agent.stream` (default `true`). `send()` (blocking) remains for the
  toggle-off path. Shared guards/persistence were extracted so both paths behave
  identically. Note: the browser-side incremental render is verified manually; the
  PHP emission of stream directives is covered by tests.
- **Fixed token accounting** — `AiChatService` read `usage->inputTokens`/`outputTokens`
  which don't exist on Prism's `Usage` object, so `tokens_used` always recorded 0.
  Now reads `promptTokens`/`completionTokens`.
- Switched the Prism call from the deprecated `generate()` to `asText()`.
- `prism-php/prism` added to `require-dev` so the module is covered by the package
  test suite.
- Added tests: `AiChatServiceTest` (send + stream against `Prism::fake()`, token
  accounting, temperature forwarding, rate-limit/no-provider/guest guards, delta
  extraction for both the real `TextDeltaEvent` and the testing fake),
  `AiToolRegistryTest` (Prism Tool conversion) and `AiChatWidgetTest` (widget wiring
  + guest gating).

### Fixed
- **Base layer UI sentinels were not honored in `searchLike()`/`advancedSearch()`**
  — the methods filtered by the literal default value instead of skipping it, so a
  request with the default `searchLike`/`search`/`relations` returned no rows (or
  threw `RelationNotFoundException` for `relations`). The guards now match the
  documented contract.
- Renamed the base-layer sentinel values to English to match the documentation
  (`BaseLayer.md`) and the rest of the API surface: `search`/`searchLike` default
  sentinel is `Search` (was `Busca`), and the `relations` sentinel is `Relation`
  (was `Relacao`). `Incremental` is unchanged. **Breaking** only for REST clients
  that explicitly sent the old Portuguese magic words as no-op defaults.

### AI Agent module
- **Fixed: `temperature` was ignored** — `AiChatService` now passes the configured
  temperature to Prism (`->usingTemperature()`). Previously the column was stored
  and validated but never applied.
- **Fail-closed authorization on `AiModelConfigList`** — the `ai.config` permission
  is now re-checked on every mutating action (create/edit/save/delete/setDefault),
  not only on `mount()`. These records hold provider API keys.
- **Rate limit keyed by user** when authenticated (was session-only, bypassable by
  dropping the session cookie); the public `processAiMessage` listener re-checks
  provider availability.
- **Octane safety** — provider credentials applied at runtime are now restored after
  each request, so API keys don't bleed across requests on long-lived workers.
- **`getSystemInfo` tool** no longer leaks framework/PHP versions or the environment
  name unless `ptah.ai_agent.expose_system_details` is enabled.
- **Optional per-user daily token budget** via `ptah.ai_agent.daily_token_limit`.
- **`ptah.ai_agent.allow_guests`** (default `false`) — the chat widget and service
  are restricted to authenticated users unless explicitly enabled.
- Removed dead code from `AiToolRegistry` (`execute()`, `hasTools()`); clarified the
  `max_history` config docs (message count, not tokens).
- Added tests: `AiProviderConfigServiceTest` (config service, encrypted-at-rest API
  key, scopes) and `AiModelConfigListAuthTest` (fail-closed authorization).

### Security
- **Removed `eval()` from inline lifecycle hooks** (`HasCrudForm::executeInlineHook`).
  Inline hooks are now sandboxed Symfony ExpressionLanguage expressions — no
  arbitrary PHP execution, eliminating the RCE risk if a `crud_configs` row were
  tampered with. Adds `symfony/expression-language` as a dependency. **Breaking:**
  inline hooks that contained PHP statements must be migrated to a hook class;
  inline now only reshapes `data` (helpers: `merge`, `now`, `upper`, `lower`,
  `slug`, `uuid`). Class-based hooks are unchanged.
- **SQL injection hardening in dynamic filters** — column/identifier names are now
  validated by `Ptah\Support\SqlIdentifier` before being interpolated into raw SQL
  in `TextFilterStrategy`, `RelationFilterStrategy` (whereHas + aggregate/HAVING)
  and `HasCrudSearchDropdown`. Unsafe identifiers are rejected.
- **No insecure default admin password** — removed the hardcoded `admin@123`
  fallback from `config/ptah.php`, `ModuleCommand` and `DefaultAdminSeeder`. When
  `PTAH_ADMIN_PASSWORD` is unset, a strong random password is generated and shown
  once during installation.
- **XSS hardening in table actions** — `javascript:`/`data:`/`vbscript:` schemes are
  now blocked on `link`-type action columns in `_table.blade.php`.
- **Mass-assignment guard** — `save()` now strips `id`, timestamps and audit/`*_by`
  columns from submitted data regardless of the CRUD config.
- **Fail-closed authorization** — create/update/delete/restore now deny anonymous
  users when a `permissionIdentifier` is configured (previously the whole check was
  skipped for unauthenticated requests). Centralised in `authorizeCrudAction()`.
- **Rate limiting added** to password-reset requests (`ForgotPasswordPage`) and to
  the **2FA code challenge** (`TwoFactorChallengePage`), preventing brute-force of
  the verification code.

### Changed
- `ptah:forge --force` now asks for confirmation in interactive sessions before
  overwriting existing files.
- Generated migrations add an index on `deleted_at` when soft deletes are enabled,
  and `--no-soft-deletes` on a fresh migration no longer emits `softDeletes()`
  (the stub now uses a `{{ soft_deletes }}` placeholder controlled by the generator).
- Forge component docs (`forge-input`, `forge-button`) warn that icon props/slots
  render raw HTML and must never receive user-controlled data.

### Docs
- Standardised Livewire version to 4 across `BaseCrud.md` and `Modules.md`.
- Aligned admin-password documentation (no fixed default) across `Commands.md`,
  `Permissions.md`, `Modules.md` and `PetPlace-Prompt-Example.md`.
- Documented the `surname=`/`label=` field modifiers and added a `ptah:menu-sync`
  reference section in `Commands.md`.
- Rewrote the inline lifecycle-hook documentation in `Configuration.md` for the new
  sandboxed expression syntax.

### Permissions — cache invalidation rework (security)
- **Fixed: revoking a permission was not immediate.** Editing a role's object
  bindings (`RoleService::bindPageObject`/`unbindPageObject`/`syncPageBindings`)
  never cleared the permission cache, so a revoked action stayed effective for up
  to `cache_ttl` (default 1h). Replaced the broken tag-based invalidation (keys
  were never stored with tags, so `Cache::tags()->flush()` was a no-op) with
  **generation-based versioning**: every cache key embeds a global counter and a
  per-user counter; invalidation just increments a counter — O(1), works on every
  driver (file/database/redis/memcached), no key enumeration. Revocation now takes
  effect on the next check.
- Invalidation is wired via model observers (`Role`/`RolePermission` → global bump,
  `UserRole` → per-user bump) **and** explicit bumps in `RoleService` (covers
  query-builder mass deletes that don't fire model events).
- **Action whitelist** — `check()` and `getCompaniesForResource()` now reject any
  action outside `create/read/update/delete` before it is interpolated into the
  `can_{action}` column (typo + SQL-injection guard).
- **MASTER permission map is now cached** (per global generation) instead of
  querying `PageObject` on every request for master users.
- `clearCache()` no longer needs the `$companyId` argument — a per-user bump clears
  every company-scoped map at once.
- Docs: documented MASTER being global (not company-scoped), `company_id = null`
  meaning cross-tenant, and the generation-based cache. 10 new tests
  (`PermissionServiceTest`) including the immediate-revocation regression that
  would fail on the old code.

### Power features (ScriptCase-inspired)
- **Master/Detail** — `masterDetail` config adds an expand arrow per row that
  mounts a nested BaseCrud filtered by the parent key via the new
  `lockedFilters` mount parameter: enforced on every query, immune to the
  child's Clear filters, `SqlIdentifier`-guarded. Multiple detail grids per row
  supported; first entry editable in the CrudConfig modal (General tab).
- **Group break ("quebra") with subtotals** — `groupBreak` config keeps rows
  individual but makes the field the primary sort and renders a header per group
  plus per-group subtotal rows (reusing the Totalizer columns), styled with the
  brand tint. Unsafe field names are ignored.
- **Calculated fields** — `colsOnChange` column option runs a sandboxed
  expression (same ExpressionLanguage engine as the lifecycle hooks; variables
  `data`/`value`) whenever the field changes, live (`.live.debounce.600ms` on the
  trigger input). Errors are logged and never break the form.
- **Form sections** — `colsFormBlock` groups adjacent fields under a section
  heading in the create/edit modal.
- **Card (mosaic) view** — toolbar toggle (persisted per user) switches the
  listing between the table and a responsive card grid sharing the same row
  states, selection, actions and pagination.
- **Duplicate record** — copy action per row opens the create modal pre-filled
  with the source row (guarded/audit fields never copied).
- **Print view** — Export menu → Print with a dedicated `@media print`
  stylesheet (chrome, selection and action columns hidden, sticky columns
  flattened).
- Actions column now shrinks to its icons (`width:1%` + nowrap) instead of
  absorbing leftover table width.
- 9 new tests (duplicate, formulas incl. sandbox failure, break sort/render/
  guard, locked filters, detail toggle, view mode); 26 new i18n keys; docs in
  `BaseCrud.md`.

### Cascading (dependent) search dropdowns
- **New `colsSDDependsOn` / `colsSDFilterColumn` column options** — make a
  searchdropdown depend on another form field (Country → State → City, unlimited
  depth). The child is disabled with a "Select {parent} first…" placeholder until
  the parent has a value; its options are filtered by `WHERE {filterColumn} =
  {parent value}` (column guarded by `SqlIdentifier`); changing the parent clears
  the entire descendant chain (value, label, cached results) — including when the
  parent is a plain select, via the `updatedFormData` hook.
- Works in the **filter panel** as well: dependent searchdropdown filters follow
  the same gate/filter/reset rules against the active filters.
- Configurable in the CrudConfig modal (SearchDropdown tab → Cascading section);
  documented in `BaseCrud.md`; covered by 5 new tests (gate, parent filtering,
  recursive reset on both scopes).

### Visual refresh — modern, brand-driven styling
- **Single brand source** — every accent in `ptah-components.css` (focus rings, sort
  arrows, filter chips, active buttons, saved filters, quick-date buttons, bulk bar,
  selected dropdown items, modal icon) now derives from the host's
  `--color-primary` token via `color-mix()` tints. No more hardcoded blue clashing
  with a purple primary: change the token once and the whole CRUD follows.
- **Elevation** — dropdown menus gained a layered shadow + hairline ring and a
  scale/fade open transition; toolbar, filter panel and table wrapper have a subtle
  ambient shadow; modals are `shadow-2xl`; solid forge-buttons get `shadow-sm` and a
  visible `focus-visible` ring.
- **Row states** — hover is now neutral (`slate-50`); brand color is reserved for
  the new selected-row state (soft brand background + 2px brand edge) so bulk
  selection finally has visual feedback.
- **Badges modernised** — badge/pill/boolean renderers moved from the old
  `bg-*-100/text-*-800` look to the current soft + inset-ring idiom
  (`bg-green-50 text-green-700 ring-1 ring-inset ring-green-600/20`); the duplicated
  color map was extracted to a shared `badgeColorClasses()` helper.
- **Radius hierarchy** — containers (toolbar, table, filter panel, dropdowns)
  are `rounded-lg`, modals `rounded-xl`; controls keep `rounded-md`.
- **Detailing** — column drag-grip appears only on header hover (cleans 8× visual
  noise); thin styled scrollbar on the table wrapper; circular ringed empty-state
  icon; modal footer separator actually visible.

> After updating, rebuild the host assets (`npm run build`) so the new classes and
> CSS variables are picked up.

### BaseCrud UX overhaul (12 usability improvements)
- **Undo on delete** — the post-delete toast now shows an inline *Undo* button for
  soft-deletable records (calls `restoreRecord`); toasts with Undo stay visible for 6s.
- **Toasts now stack** — multiple notifications no longer overwrite each other; each
  has its own timer and dismiss button (`aria-live=polite`).
- **Native `confirm()` dialogs replaced** with styled, theme-aware dialogs for bulk
  delete / permanent delete (with an "irreversible" warning) and for the
  unsaved-changes check when closing the form modal (Esc first closes the warning).
- **Row links behave like real links** — Ctrl/Cmd-click and middle-click on a row
  with `configLinkLinha` open the record in a new tab (`ptahRowNav`).
- **"Save & add another"** button on the create modal (`saveAndNew()`): persists and
  reopens a blank form — for batch data entry. Covered by 3 new tests.
- **Keyboard shortcuts** — `/` focuses the global search, `n` opens the create modal
  (ignored while typing or with a modal open).
- **Accessibility** — column sort is now a real `<button>` with `aria-sort` on the
  `<th>` (keyboard sortable); `aria-label` on every icon-only button (row actions,
  refresh, clear filters, search clear), on bulk checkboxes and the per-page select.
- **Larger touch targets** — row action buttons gained padded hitboxes and the column
  resize handle is 2× wider without visual change.
- **Sticky actions column** — the default actions column stays visible during
  horizontal scroll (with row-hover background preserved via `group-hover`).
- **Floating bulk bar no longer covers pagination** (spacer when a selection is active).
- **i18n fix** — the "N active" filter badge was hardcoded in Portuguese; now uses
  `trans_choice` with proper pluralisation in both locales (11 new lang keys).

### Tests — BaseCrud save, renderers, search dropdown and export (P1, section 5 complete)
- **`CrudSaveTest`** (6) — end-to-end `save()` through the real component: create with
  mask transform applied, required-field validation blocking the insert, guarded
  fields stripped even when marked savable, inline sandbox hook reshaping data,
  edit-update without duplication, `prepareCreate()` state reset.
- **`CrudRenderersTest`** (14) — XSS safety first: plain values, badge fallbacks and
  config labels are always escaped; badge color maps (named + hex), boolean truthy
  variants and custom labels, money per currency, BR date formatting with
  unparseable passthrough, select label mapping, cell class/icon wrappers,
  conditional row styles (==, >), unknown-renderer fallback.
- **`CrudSearchDropdownExportTest`** (11) — model-backed lookups with value/label
  pairs, `colsSDLimit` cap, empty-query reset, option selection filling
  formData/labels, filter-panel selection + clear flows; export sync dispatch,
  disabled gate and bulk-export selection requirement.

### Tests — BaseCrud concerns and filter pipeline (P1, section 5)
- **`FilterServiceTest`** (15) — AND/OR composition against real rows, plain-array
  to DTO conversion, invalid-filter skipping, date-range form parsing (`_start/_end`,
  explicit operators, legacy `_from/_to`), custom-filter config parsing (whereHas,
  CSV `IN`), global search building (OR LIKE for text/select, whereHas for relations).
- **`FilterStrategiesTest`** (12) — Numeric (array/CSV BETWEEN, partial bounds,
  comparison operators), Date (same-day range covers startOfDay..endOfDay, out-of-window
  exclusion, whereDate equality, null no-op), Array (whereIn, CSV normalisation,
  NOT IN, blank CSV no-op).
- **`CrudMaskTransformsTest`** (13) — `money_to_float` for BR/EN/comma-only formats,
  `digits_only`, `plate_clean`, BR↔ISO dates (invalid input passes through untouched),
  case/trim, unknown transform and skip rules.
- **`FormValidatorServiceTest`** (13) — required (incl. legacy `'S'`), optional-empty
  short-circuit, numeric min/max vs string lengths, digits, in/notIn, regex,
  confirmed cross-field, email, CPF check digits, first-error-only per field.
- **`CrudDeletionTest`** (8) — the real BaseCrud component mounted with a DB config
  row: soft delete + `deleted_by` stamping, restore, trashed count, confirm/cancel
  flow, fail-closed delete for anonymous users.
- **`CrudQueryTest`** (7) — HasCrudQuery pipeline through the real component:
  global search, sort ASC/DESC, form filters, operator filters, per-page pagination,
  quick date filter.

### Fresh-install validation (bugs found and fixed)
Validated the full flow on a brand-new Laravel 12 app (create-project →
path-repository require → `ptah:install` → modules → `ptah:forge` → migrate →
serve). Two real bugs surfaced and were fixed:

- **Generated controller was missing imports** — `controller.stub` referenced
  `Store{Entity}Request`, `Update{Entity}Request` and `RedirectResponse` without
  `use` statements, fataling on store/update calls (subfolder entities included:
  requests now import from the sub-namespace). Covered by the new
  `ControllerGeneratorTest`.
- **Generated web route had no `auth` middleware** — anonymous visitors hit the
  controller and received the permission 403 instead of being redirected to
  `/login`. When `ptah.modules.auth` is active, `ptah:forge` now appends
  `->middleware('auth')` to the generated route.

> **Upgrade note:** stubs are published to `stubs/ptah/` on install and take
> precedence over the package copies. After upgrading, re-publish to get these
> fixes: `php artisan vendor:publish --tag=ptah-stubs --force` (or delete the
> stubs you did not customise).

### Tests — commands (P1)
- **`ScaffoldCommandTest`** (8 tests) — full web artefact set on disk (model, DTO,
  repository + interface, service, requests, resource, view, migration, route,
  binding, crud_configs row), subfolder entities, `--no-soft-deletes`, `--api-only`
  (no views/CrudConfig, API requests + apiResource route), `--force` confirmation
  abort, skip-without-force preserves user edits, acronym table naming
  (`POSSale` → `pos_sales`).
- **`MakeHooksCommandTest`** (4) — generated class implements `CrudHooksInterface`
  with the four hook methods, subfolder namespaces, `--force` semantics.
- **`MenuSyncCommandTest`** (4) — flat links/groups/children synced, idempotent
  re-run, `--fresh` clears stale rows, missing registry fails.
- **`ModuleCommandTest`** (2) — `--list` succeeds, unknown module fails.

### Contribution sandbox
- **`sandbox/docker-compose.yml` + `setup.sh`** — disposable Laravel app with the
  local package symlinked via path repository: `cd sandbox && docker compose up`
  and edit the package with instant feedback. No local PHP/Composer/Node required.
  Documented in `sandbox/README.md` and linked from `CONTRIBUTING.md`.

### Tests — generators (P1)
- **`GeneratorTestCase`** + 9 test files (36 tests) covering Model, DTO, Repository +
  Interface, Service, Requests (web + API), Resource, Routes, Binding, CrudConfig and
  View generators: generated content assertions, `php -l` lint checks, idempotency of
  route/binding injection and the `shouldRun` gates for api-only mode.
- **Fixed: generated DTOs emitted optional constructor parameters before required ones**
  (deprecated in PHP 8). `EntityContext::dtoProperties()` now orders required properties
  first; safe because `fromArray()` uses named arguments.

### Static analysis
- **PHPStan baseline reduced from 208 to 140 errors** via root-cause fixes: typed
  model-event closures in `HasAuditFields` (-40), removed compile-time `App\Models\User`
  references in favour of config-driven strings (-12), `@property` annotations on `Menu`,
  fixed malformed array-shape PHPDoc and declared `#[Computed]` properties in `RoleList`.
  A single documented `ignoreErrors` rule replaces 20 identical baseline entries for the
  package-namespace `view('ptah::…')` false positive.

### Community / DX
- Added `CONTRIBUTING.md`, YAML issue forms (bug / feature), `PULL_REQUEST_TEMPLATE.md`
  and `CODE_OF_CONDUCT.md` (Contributor Covenant 2.1 by reference).
- Added `docs/QuickStart.md` — first CRUD in 5 minutes (SQLite, one entity), linked
  from the README.

### Tests — P0 security regression suite
- **`FilterStrategySecurityTest`** (`tests/Unit/Services/Crud/FilterStrategySecurityTest.php`)
  — 10 tests: `TextFilterStrategy` and `RelationFilterStrategy` discard every class of
  malicious column name (SQL injection, semicolons, leading-digit, unquoted spaces, single
  quotes) and still apply safe identifiers and table-qualified names.
- **`CrudFormSecurityTest`** (`tests/Feature/Crud/CrudFormSecurityTest.php`)
  — 8 tests: `guardedFormFields()` lists all 8 audit/PK columns that must never come from
  form data; inline hooks with `merge(data, {...})` and `upper()` work correctly; arbitrary
  PHP (`file_put_contents`) and invalid syntax are rejected by the ExpressionLanguage sandbox
  without propagating exceptions; `authorizeCrudAction()` is fail-closed (anonymous users
  denied with `permissionIdentifier` set, allowed when module is off or identifier is absent).
- **`AuthRateLimitTest`** (`tests/Feature/Auth/AuthRateLimitTest.php`)
  — 6 tests: `LoginPage` blocks after 5 attempts and allows under the limit;
  `ForgotPasswordPage::sendLink()` throttles after 3 attempts; `TwoFactorChallengePage::verify()`
  throttles after 5 failed codes and keys the counter by `userId|ip` (different users have
  independent counters).
- **`DefaultAdminSeederTest`** (`tests/Feature/Seeders/DefaultAdminSeederTest.php`)
  — 4 tests: seeder never sets `admin@123`; uses `PTAH_ADMIN_PASSWORD` when provided;
  idempotent on second run; generated random password is strong (len > 20, not a dictionary word).

### Added
- **Unit tests — `BaseRepository`** (`tests/Unit/Base/BaseRepositoryTest.php`)
  - 28 test cases: full CRUD contracts (`find` → null, `findOrFail` → exception, `update` →
    fresh record, `delete` → exception), all four `findBy` signatures (string, array, Closure,
    Builder), `findByIn`, `allQuery` skip/limit, `searchLike` operators `}` (≥) and `{` (≤),
    `whereIn` and `additionalQueries` params, `advancedSearch` sentinel guard, `updateBatch`,
    `createQuietly`/`updateQuietly` event-suppression, `replicate`
- **Unit tests — `BaseService`** (`tests/Unit/Base/BaseServiceTest.php`)
  - 9 test cases: `destroy` returns `false` for missing ID (vs `delete` which throws),
    `destroy` removes existing record, `show` returns model or null, `getData` routing
    (search → advancedSearch, searchLike → searchLike, default → findAllFieldsAnd),
    `limit`/`direction` respected, `relations` sentinel `'Relacao'` produces empty array
- **Unit tests — `HasCrud`** (`tests/Unit/Traits/HasCrudTest.php`)
  - 7 test cases: end-to-end delegation chain for every method (`all`, `paginate`, `find`,
    `findOrFail`, `create`, `update`, `delete`), confirming no method-name typos or
    signature drift against `BaseRepositoryInterface`
- **Unit tests — `HasAuditFields`** (`tests/Unit/Traits/HasAuditFieldsTest.php`)
  - 14 test cases (new: `nao_preenche_deleted_by_em_force_delete` regression guard for [M-2])
  - All boot events, `=== null` guard, guest behaviour, `NoFillable` tolerance,
    hard-delete safety, `forceDelete` safety, updating-event semantics and all three
    `createdBy` / `updatedBy` / `deletedBy` relationships
  - Dedicated stub tables in `tests/migrations/` (`has_audit_stubs`, `no_soft_delete_stubs`)
  - Self-contained stub models (`AuditableStub`, `AuditableNoFillableStub`,
    `AuditableHardDeleteStub`) defined inline in the test file
  - Uses `#[Test]` attribute (PHPUnit 11 style) instead of deprecated `@test` docblock
- **`CHANGELOG.md`** — full version history using Keep a Changelog format
- **`tests/migrations/2024_01_10_000002_create_base_crud_stubs_table.php`** — `items` stub
  table (id, name, status, amount, timestamps) shared by BaseRepository, BaseService and
  HasCrud test suites
- **`BaseRepository::findByBuilder()`** — clean replacement for `findBy(Builder)` branch,
  accepts `(Builder $query, string $column, string $operator, mixed $value)` with explicit
  operator as first-class param; old Builder-union in `findBy()` removed [M-3]
- **`BaseRepository::getTableColumns()`** — public memoised helper (static cache per table)
  that returns validated column names for the model's table; replaces the former private
  `tableColumns()` anonymous function; exposed via `BaseService::getTableColumns()` delegation
- **`BaseService::getData()`** — renamed from `getDados()` with English name and
  `orderByRaw("{$col} {$dir}")` replaced by `orderBy($col, $dir)` with column/direction
  whitelisting [C-1]; `getDados()` kept as `@deprecated` alias for backward compatibility
- **`BaseRepositoryInterface`** — 15 previously unspecified methods added: `advancedSearch`,
  `searchLike`, `findAllFieldsAnd`, `autocompleteSearch`, `allQuery`, `findBy` (updated
  signature), `findByBuilder`, `findByIn`, `updateBatch`, `updateQuietly`, `createQuietly`,
  `truncate`, `replicate`, `useIndex`, `buildSelectFields`, `getTableColumns`, `getKeyName` [A-1]
- **`FilterDTO`** — now extends `BaseDTO` and implements `fromRequest(Request): static` [B-2]

### Changed
- **`BaseRepository::mountFieldsToSelect()`** renamed to `buildSelectFields()` — now also
  intersects requested fields against real table columns preventing column enumeration [A-3];
  deprecated alias `mountFieldsToSelect()` retained for backward compat
- **`BaseRepository::getWherehas()`** renamed to `applyWhereHas()` (protected internal method)
- **`BaseRepository::findBy()`** — removed `Builder` union type and `$boolean` parameter;
  use `findByBuilder()` for Builder-based queries [M-3]
- **`BaseRepository::truncate()`** — now multi-DB aware: MySQL/MariaDB uses
  `SET FOREIGN_KEY_CHECKS = 0/1`, PostgreSQL uses `TRUNCATE … RESTART IDENTITY CASCADE`,
  other drivers (SQLite, etc.) use plain `DB::table()->truncate()` [C-4]
- **`BaseRepository::useIndex()`** — returns plain Builder on non-MySQL/MariaDB drivers
  instead of injecting a MySQL-only hint; no behaviour change on MySQL [C-5]
- **`BaseService::destroy()`** — race condition removed: single `findOrFail()` inside
  `try/catch ModelNotFoundException` replaces the separate `find()` + `if(model)` pattern [A-2]
- **`BaseService::resolveRelations()`** — filters requested relations against
  `$allowedRelations` whitelist when non-empty; default `[]` means all allowed (backward
  compat) [A-4]
- **`HasAuditFields` `deleted` event** — guard changed from
  `method_exists($model, 'getDeletedAtColumn')` to `method_exists($model, 'trashed') &&
  $model->trashed()`; `forceDelete()` now correctly skips the raw UPDATE since
  `deleted_at` was never set and `trashed()` returns false [M-2]
- **`PtahServiceProvider`** — `SchemaInspector` singleton registered only when
  `runningInConsole()`; avoids unnecessary reflection overhead in HTTP requests [B-5]
- **`PtahServiceProvider`** — `setLocale()` now opt-in via `ptah.force_locale` config
  (`PTAH_FORCE_LOCALE` env); does not override host app locale by default [A-6]
- **`PtahServiceProvider`** — `loadMigrationsFrom()` only called when at least one module
  is enabled; no migrations auto-loaded on fresh installs with no modules [C-6]
- **`PtahServiceProvider`** — demo route removed from `staging` environment; available only
  in `local` and `development` [B-3]
- **`SchemaInspector::fromDatabase()`** — replaced MySQL-only `SHOW FULL COLUMNS FROM`
  with portable `Schema::getColumns()` (Laravel 10.23+); works on MySQL, PostgreSQL and
  SQLite; `parseDbColumn()` updated to accept `array` instead of `object` [B-1]
- **`config/ptah.php`** — added `force_locale` key; removed `'admin@123'` default from
  `admin_password` (now `null` unless `PTAH_ADMIN_PASSWORD` is set) [M-6]; config section
  comments translated to English

### Fixed
- **`TestCase.php`** — replaced non-functional `loadLaravelMigrations()` (Testbench 10 ships
  an empty `laravel/database/migrations/` directory) with a dedicated test migration
  `tests/migrations/2014_10_12_000000_create_test_users_table.php`; this also unblocked
  `CompanyModelTest` which was silently broken for the same reason
- **`CompanyModelTest`** — migrated from deprecated `@test` docblock to `#[Test]` attribute
- **SQL injection** — user-supplied column names and operators in `searchLike`
  (`whereIn`, `additionalQueries`) and `findAllFieldsAnd` validated against
  `getTableColumns()` whitelist [C-2, C-3, M-4]

---

## [1.0.0-rc.5] — 2026-03-03

### Added
- **`HasAuditFields` trait** (`src/Traits/HasAuditFields.php`)
  — automated audit stamping via Eloquent boot events:
  - `created_by` / `updated_by` on `creating`; `updated_by` always refreshed on `updating`
  - `deleted_by` via raw SQL on the `deleted` event (after soft-delete commits — prevents
    stale stamp if the soft-delete transaction fails)
  - Guard: `=== null` check (not `empty()`), so user ID `0` is never overwritten
  - `Auth::id()` cached once per event callback — no double call
  - Relationships: `createdBy()`, `updatedBy()`, `deletedBy()` resolved from
    `config('auth.providers.users.model')`
  - Tolerant: silently skips columns absent from `$fillable`
- **All package models** now use `HasAuditFields`:
  `Company`, `Department`, `Role`, `Menu`, `CrudConfig`, `PtahPage`, `PageObject`,
  `UserRole`, `RolePermission`
- **`model.stub`** — `use HasAuditFields` added to every scaffolded model
- **`migration.stub` + `MigrationGenerator`** — `created_by`, `updated_by` (always) and
  `deleted_by` (when `--soft-delete`) injected automatically into every generated migration
- **`EntityContext`** — `fillableList()`, `castsList()`, `resourceFields()` include audit fields
- **`BaseCrud`** — belt-and-suspenders: explicit audit injection in `save()` and
  `deleteRecord()`; `bulkDelete()` uses `->each()` so Eloquent events fire per-record
- **ALTER migration** `2024_01_05_000000_add_audit_fields_to_ptah_tables` — idempotent
  `hasColumn()` guards for upgrading existing installations
- **`--api` combined mode** (`ptah:forge … --api`) — generates web + API artefacts in a
  single command; pre-existing Model is preserved and only `@OA\Schema` is injected
- **`--api-only`** flag retains the legacy "API only, no web views" behaviour
- **i18n** — full `en` / `pt_BR` support via `PTAH_LOCALE`; 58 translation keys in
  `ptah::ui`; `ptah:install --locale` option; `ptah-lang` publish tag

### Fixed
- Post-install retrospective: `--boost` crash, immutable migration guard, Horizon docs,
  accent-encoding in publish paths, idempotent `ptah:install`
- `HasAuditFields` — three bugs found in peer review:
  1. `empty($model->created_by)` → `=== null` (falsy-ID-0 false-positive)
  2. `deleting` event → `deleted` (atomicity: stamp only after soft-delete commits)
  3. `Auth::id()` called twice per callback → cached in `$userId`

### Changed
- All Portuguese code comments in `HasAuditFields` translated to English

---

## [1.0.0-rc.4] — 2026-01-15

### Added
- **API module** (`ptah:module api`) — installs `darkaonline/l5-swagger`, publishes
  `BaseApiController`, `BaseResponse`, `SwaggerInfo`; full Swagger annotations generated
  by `ControllerApiGenerator`
- **Subfolders** in `ptah:forge` — `ptah:forge Product/ProductStock` generates all
  artefacts under `Product/` sub-namespace; model key stored as `Product/ProductStock`
- **`surname=` / `label=` modifiers** in `--fields` — override the display label shown in
  the BaseCrud column header without renaming the database column
- **`BaseRepository`** and **`BaseRepositoryInterface`** modernised — type-safe generics,
  `getDados()` intelligent search (OR/AND/searchLike/pagination), three type-error fixes
- **`forge-page-header`** and **`forge-tab` / `forge-tabs`** Blade components (slot +
  Livewire dual-mode)
- **Company Switcher** — horizontal tab bar in navbar, session-aware, hidden when single
  company; `forge-input` eye-toggle for password fields
- **Laravel Boost integration** (`ptah:install --boost`) — installs `laravel/boost`
  automatically; `SKILL.md` and `guidelines/core.blade.php` rewritten with full SOLID
  rules, design tokens, and post-scaffold checklist
- **Livewire 4** support (`^3.0|^4.0`); Livewire component aliases migrated from
  `ptah::` namespace to `ptah-` prefix for compatibility

### Fixed
- `forge-auth` layout `@extends` `ParseError`
- Alpine.js duplicated with Livewire 3 in forge-auth layout
- Route `[login] not defined` accessing protected routes
- Profile page blank — component/view alignment
- `seedDefaultAdmin` and `seedDemoData` check `Schema::hasTable` before running
- `installBoost` now checks `vendor/laravel/boost` path instead of exit code
  (false-positive on Windows)
- `forge-pagination` uses `\/\` instead of custom `@props`
- `relationshipsUse()` generates `// TODO:` placeholder instead of guessing import path

### Changed
- Boxicons + FontAwesome set as the official icon standard; legacy SVG inline map removed
- `ptah:install` now creates the default company and admin user automatically

---

## [1.0.0-rc.3] — 2025-10-20

### Added
- **Módulo auth** — login, logout, forgot + reset password, 2FA (TOTP + e-mail OTP),
  recovery codes, active session management
- **Módulo menu** — `config` (default, zero migrations) and `database` drivers; tree with
  accordion groups; `MenuService` with cache + Observer invalidation; management screen
  `/ptah-menu`; Boxicons/FontAwesome CSS icon support
- **Módulo company** — full company + department CRUD, `CompanyService` with session
  context, multi-company support, `DefaultCompanySeeder` (idempotent)
- **Módulo permissions** — hierarchical RBAC: roles, pages, page objects (button/field/
  section/api/report/tab/link/page), `can_create/read/update/delete` + JSON `extra`;
  `PermissionService` with Redis tag cache; `RoleService` with MASTER bypass and batch sync;
  global helpers `ptah_can()`, `ptah_is_master()`, `ptah_permissions()`; `Permission` Facade;
  `@ptahCan` / `@ptahMaster` Blade directives; `ptah.can` middleware; 5 admin screens;
  `DefaultAdminSeeder` (idempotent)
- **`ptah:module`** command — interactive module activation with migration publish + run
- **Dark mode** — full `ptah-dark` class coverage across all Forge components and module
  views; OS preference detection; `ptah:install --demo` seeds demo data
- **Rule::unique demo** and `CompanyModelTest` (factory-based unit tests)
- `forge-stat-card` component; profile photo upload (`WithFileUploads`)
- Admin dropdown in navbar; `storage:link` in `ptah:install`

### Fixed
- Multiple dark mode inconsistencies in navbar dropdown, search fields, page-list
- `forge-button` light contrast in light mode
- Company list column/wire:model issues
- Menu active-state matching exact path + sub-routes only

---

## [1.0.0-rc.2] — 2025-07-08

### Added
- **Configurable JOINs** — LEFT / INNER JOINs declared in `CrudConfig`; full filter, sort
  and export support without Eloquent relationships
- **Renderer DSL** — `badge`, `pill`, `boolean`, `money`, `link`, `image`, `truncate`
  with `colsRenderer` / `colsRendererBadges`
- **Masks** — `cpf`, `cnpj`, `phone`, `cep`, `currency`, `percent` + `colsMaskTransform`
- **12 new validations** and **8 new renderers** in `FormValidatorService`
- **`colsMetodoCustom`** multi-param support + `colsMetodoRaw`
- **Cell styling** — `colsCellStyle`, `colsCellClass`, `colsCellIcon`, `colsMinWidth`;
  icon shown in both `<th>` header and cell
- **Nested relations** — `colsRelacaoNested` with dot notation (`category.parent.name`)
- **Bulk Actions** — multi-select, bulk delete (with Eloquent event safety), bulk export,
  custom bulk actions
- **Advanced search** — multiple criteria with AND/OR logic
- **Quick date filters** — today / week / month / quarter / year toggle
- **Saved filters** — named filter sets persisted per user
- **Column visibility** — per-user show/hide with drag-and-drop reorder and resize;
  preferences persisted via `UserPreferences`
- **Drag-and-drop column reorder + resize** with persistence
- **CrudConfig modal** — full in-app CRUD configurator (columns, actions, filters, styles,
  general, permissions tabs)
- **SearchDropdown** — select2-like UX in both form modal and filter panel
- **Totalizadores** — sum/count/avg/max/min per column
- **Export** — sync and async (Job-based) export to Excel/CSV
- **Broadcast / real-time** — Echo listener configurable per model
- **Display name** — configurable via `CrudConfig`
- **`forge-tabs`** dual-mode (slot + Livewire)

### Fixed
- Searchdropdown results not showing after Livewire re-render
- Custom filter field name mapping (`colsFilterType`, `defaultOperator`, `field_relation`)
- `getRowStyle` field key mismatch + silent skip for non-existent fields
- Action rendering wrong field names (`actionIcone` / `actionCall`)
- Stale Livewire snapshot in `boot()` — reload `crudConfig` on hydration
- `BaseCrud` layout `ParseError` (form_lbl token corrupted by PowerShell)

### Changed
- S/N string flags replaced with `true`/`false` booleans across all `ColDef` fields
- Scaffold: removed create/edit/show views and full CRUD controller — BaseCrud modal
  manages all mutations; controller is now a single-method index wrapper

---

## [1.0.0-rc.1] — 2025-04-01

### Added
- **Initial package structure** — `composer.json`, `PtahServiceProvider`, `ptah:install`
- **`ptah:forge` scaffolding generator** — single command creates Model, Migration, DTO,
  `RepositoryContract`, `Repository`, `ServiceContract`, `Service`, `Controller`,
  `StoreRequest`, `UpdateRequest`, `Resource`, `CrudConfig` (DB), view index with
  `@livewire`, web route — 14 artefacts total
- **FK auto-detection** — `_id` suffix + `unsignedBigInteger` type generates
  `foreignId()->constrained()->cascadeOnDelete()` in migration and `belongsTo()` in model
- **`BaseCrud` Livewire component** — dynamic table with sort, pagination, global search,
  modal create/edit with validation, soft delete + restore, company multi-tenant filter,
  `whereHas` parent-entity pre-filter, error recovery (clears corrupted preferences),
  cache with per-model invalidation
- **26 Forge Blade components** (`<x-forge-*>`) — layout dashboard, layout auth, sidebar
  with collapse/expand, navbar, button, input, select, modal, alert, badge, table,
  pagination, stat-card, and more — all Tailwind v4 + Alpine.js 3, dark mode ready
- **Design tokens** — `primary` `#5b21b6`, `success` `#10b981`, `danger` `#ef4444`,
  `warn` `#f59e0b` as CSS custom properties
- **`stubs/ptah/`** — publishable stubs for all generated artefacts
- **`config/ptah.php`** — full configuration reference
- **`phpunit.xml`** with Orchestra Testbench + SQLite `:memory:` setup
- **`docs/`** — `BaseCrud.md` (1 500+ lines), `AI_Guide.md`, `Modules.md`,
  `Company.md`, `Permissions.md`
- **`SKILL.md`** for Laravel Boost — SOLID layer rules, design tokens, scaffolding guide,
  BaseCrud JSON reference, Livewire input conventions
- **`ptah:install`** — publishes config + stubs + migrations, runs migrate, optionally
  seeds demo data (`--demo`), installs Boost (`--boost`)

---

[Unreleased]: https://github.com/jonytonet/ptah/compare/v1.28.0...HEAD
[1.28.0]: https://github.com/jonytonet/ptah/compare/v1.27.0...v1.28.0
[1.27.0]: https://github.com/jonytonet/ptah/compare/v1.26.0...v1.27.0
[1.26.0]: https://github.com/jonytonet/ptah/compare/v1.25.0...v1.26.0
[1.25.0]: https://github.com/jonytonet/ptah/compare/v1.24.0...v1.25.0
[1.24.0]: https://github.com/jonytonet/ptah/compare/v1.23.0...v1.24.0
[1.23.0]: https://github.com/jonytonet/ptah/compare/v1.22.0...v1.23.0
[1.22.0]: https://github.com/jonytonet/ptah/compare/v1.21.0...v1.22.0
[1.21.0]: https://github.com/jonytonet/ptah/compare/v1.20.0...v1.21.0
[1.20.0]: https://github.com/jonytonet/ptah/compare/v1.19.0...v1.20.0
[1.19.0]: https://github.com/jonytonet/ptah/compare/v1.18.0...v1.19.0
[1.18.0]: https://github.com/jonytonet/ptah/compare/v1.17.0...v1.18.0
[1.17.0]: https://github.com/jonytonet/ptah/compare/v1.16.0...v1.17.0
[1.16.0]: https://github.com/jonytonet/ptah/compare/v1.15.2...v1.16.0
[1.15.2]: https://github.com/jonytonet/ptah/compare/v1.15.1...v1.15.2
[1.15.1]: https://github.com/jonytonet/ptah/compare/v1.15.0...v1.15.1
[1.15.0]: https://github.com/jonytonet/ptah/compare/v1.13.2...v1.15.0
[1.13.2]: https://github.com/jonytonet/ptah/compare/v1.13.1...v1.13.2
[1.13.1]: https://github.com/jonytonet/ptah/compare/v1.13.0...v1.13.1
[1.13.0]: https://github.com/jonytonet/ptah/compare/v1.12.0...v1.13.0
[1.12.0]: https://github.com/jonytonet/ptah/compare/v1.11.2...v1.12.0
[1.11.2]: https://github.com/jonytonet/ptah/compare/v1.11.1...v1.11.2
[1.11.1]: https://github.com/jonytonet/ptah/compare/v1.11.0...v1.11.1
[1.11.0]: https://github.com/jonytonet/ptah/compare/v1.10.1...v1.11.0
[1.10.1]: https://github.com/jonytonet/ptah/compare/v1.10.0...v1.10.1
[1.10.0]: https://github.com/jonytonet/ptah/compare/v1.9.1...v1.10.0
[1.9.1]: https://github.com/jonytonet/ptah/compare/v1.9.0...v1.9.1
[1.9.0]: https://github.com/jonytonet/ptah/compare/v1.8.0...v1.9.0
[1.8.0]: https://github.com/jonytonet/ptah/compare/v1.7.0...v1.8.0
[1.7.0]: https://github.com/jonytonet/ptah/compare/v1.6.0...v1.7.0
[1.6.0]: https://github.com/jonytonet/ptah/compare/v1.5.0...v1.6.0
[1.5.0]: https://github.com/jonytonet/ptah/compare/v1.4.1...v1.5.0
[1.4.1]: https://github.com/jonytonet/ptah/compare/v1.4.0...v1.4.1
[1.4.0]: https://github.com/jonytonet/ptah/compare/v1.3.0...v1.4.0
[1.3.0]: https://github.com/jonytonet/ptah/compare/v1.2.1...v1.3.0
[1.2.1]: https://github.com/jonytonet/ptah/compare/v1.2.0...v1.2.1
[1.2.0]: https://github.com/jonytonet/ptah/compare/v1.1.1...v1.2.0
[1.1.1]: https://github.com/jonytonet/ptah/compare/v1.1.0...v1.1.1
[1.1.0]: https://github.com/jonytonet/ptah/compare/v1.0.2...v1.1.0
[1.0.2]: https://github.com/jonytonet/ptah/compare/v1.0.1...v1.0.2
[1.0.1]: https://github.com/jonytonet/ptah/compare/v1.0.0...v1.0.1
[1.0.0]: https://github.com/jonytonet/ptah/releases/tag/v1.0.0
