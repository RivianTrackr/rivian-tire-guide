# Changelog

All notable changes to the Rivian Tire Guide plugin will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

## [2.3.2] - 2026-09-05

### Added
- **The review page can live in WordPress.** `RTG_Tire_Review::prefer_real_page()` on the `request` filter: when a published page exists at the review slug (`tire_review_slug`, default `tire-review`), the request is handed to that page instead of the plugin's route, so the owner can put `[rivian_tire_review]` on a real page and give it a title, description and indexing through the SEO plugin. The route's rewrite rule sits at the top of the stack and used to shadow such a page; it also forced noindex. Without a page nothing changes. The shortcode output now carries the Mediavine blocklist like the other tire pages. README documents the setup.

### Tests
- `tests/test-meta.php`: the route keeps the request with no page or a draft, hands it to a published page at the slug, and leaves other requests alone.

Nothing visible to owners until they create the page.

## [2.3.1] - 2026-09-05

### Fixed
- **Empty stars were invisible on dark screens.** Every empty star was painted `--rtg-star-empty` (#2c2f34) at 35% outline opacity on a #16191e card: ink on ink. A reader said so. A new `--rtg-star-empty-visible` token lifts the empty color 65% toward the muted text color, and outlines draw at 80%, on every star in the plugin: the guide cards' rating row, the review modal, the user-reviews page's mini stars, the review page's overall, detail and recap stars and its tire card, and the tire page's glyph stars (empty and the half-star gradient). Done as a mix rather than a new default because the admin theme settings can store the empty color; a saved #2c2f34 still ends up readable. Nothing visible to owners beyond stars they can now see; no What's New entry, at the owner's request.

## [2.3.0] - 2026-09-05

### Added
- **Write a Review on the guide.** A link in the filter header beside Clear All, in the Changelog button's old slot and size, with the accent on its icon and border. It goes to the review page's landing state (`tire_review_slug`, read in `tire-guide.php`), where the visitor picks a tire. The guide had no way to reach the review page; the only paths were a tire page's button and the star click on a card.
- **A guide footer.** Under the pagination: "Tire Guide <version> · Changelog" on the left, the Rivian Roamer credit on the right. `.rtg-guide-footer` in `rivian-tires.css`.

### Changed
- **Changelog moved from the filter header to the footer.** Same `#rtgWhatsNew` element and `.rtg-whats-new-dot`, so `whats-new.js` is untouched; a new `.rtg-whats-new-link` variant renders it as a text link with the dot riding the word. Release notes are an announcement, not a filter control, and the header now holds one action out (Write a Review) and one reset (Clear All).
- Under 480px the Write a Review button drops its label to the icon, the way Changelog did.
- No What's New entry, at the owner's request ("Nothing visible to owners" for the release check).

## [2.2.2] - 2026-09-05

### Added
- **No ads on tire-related pages.** `RTG_Theme_Render::ad_blocklist_markup()` prints Mediavine's per-page opt-out (`<div id="mediavine-settings" data-blocklist-all="1">`) at the top of every routed page (tire pages, compare, the review page, the changelog) via `filter_the_content()`, and at the top of the guide and user-reviews shortcodes. The `rtg_block_ads` filter returns ads when set to false. Nothing visible to owners is announced: no What's New entry, at the owner's request.

### Tests
- `tests/test-meta.php`: the markup, a routed page leading with it, the guide shortcode leading with it, and the filter turning it off.

## [2.2.1] - 2026-09-05

### Changed
- **R3 dropped from the review form's vehicle picker.** `RTG_Database::REVIEW_VEHICLES` is now R1T, R1S, R2 (the JS fallback list matches); it comes back when the R3 is on the road. A stored `R3` on an older row is not in the whitelist any more, so an edit that reloads it lands on "no vehicle" rather than a button that no longer exists. Nothing visible to owners beyond one fewer button, and no What's New entry at the owner's request.

## [2.2.0] - 2026-09-05

The Review a Tire page, rebuilt around the people who actually use it: guests. Sign-up is off on the site because of spam, so nearly every review comes from someone without an account, and the page now says so up front, asks for name and email last, and remembers them. A review also knows more than one star now, which is the first half of roadmap R2 and the data R1 and F9's vehicle filter need.

### Added
- **A landing state.** Under the search box, a vehicle switch over the size map (R1, R2, R3) and the six most-reviewed tires for that vehicle, from the new `RTG_Database::get_review_counts_by_tire()`. A returning guest is greeted by the name the page remembers on their device, with "Not you? Clear". The page used to be a heading and an empty search box unless you arrived from a tire page.
- **What a review knows beyond one star.** Migration 25 adds `vehicle`, `miles`, `is_owner` and six nullable `rating_<axis>` columns (range, noise, comfort, wet, snow, wear) to `rtg_ratings`. `RTG_Database::normalize_review_details()` whitelists all of it (vehicle in `REVIEW_VEHICLES`, miles clamped, axes 1–5 or NULL) and both `set_rating()` and `set_guest_rating()` take it as a sixth / seventh argument; the AJAX submit handlers pass `$_POST` straight through, so a malformed detail is dropped, never a rejection. `get_tire_reviews()` and `get_user_ratings()` return the columns.
- **The form in five numbered sections.** Overall stars with a word label and arrow keys (the only required part), six optional detail rows of five small stars each (pressing the current star clears the row), your setup (R1T / R1S / R2 / R3, miles on the set, an "I own this tire" toggle), title and review, and for guests name and email last, prefilled when remembered, with a sign-in link back to this page for the few with accounts.
- **Returning guests hear it early.** New `wp_ajax_nopriv_rtg_check_guest_review` answers whether an email already reviewed a tire with the star and the month only (what the tire page shows beside the name), rate-limited by fingerprint at 20 per window. The page asks as the email is typed and explains "one review per email per tire" instead of letting someone write everything and hit "You have already reviewed this tire." at Submit. Deliberately no guest self-update: anyone knowing an email could otherwise replace that guest's review.
- **Signed-in users see their existing review.** A banner with the month and stars, "Load it to edit" (fills every field including the details), "Start fresh", and "Delete my review" through the existing `delete_tire_rating` action. The form used to start blank and overwrite silently.
- **Tire page review cards show the setup.** `rtg_tire_page_review_meta()` in the template and `renderReviewMeta()` in `tire-page.js` render a verified-owner tag, the vehicle and "N mi on this set" under the reviewer's name. Nothing for older reviews.

### Changed
- **Inline validation.** Star, words, name and email errors sit on the field with `role="alert"`; the footer line is for server errors only. The first bad field gets focus.
- **Success screen is honest about the queue.** Guests read that every review gets a quick look, which email will hear when it is live, and a recap of what was captured; the button is "See the tire page" (the review is not live yet). Admins get "Your review is live" and "See your review" straight to the reviews section. Both link through the tire slug, now localized as `tirePageBase`.
- **Copy for guests first.** "No account needed" in the subtitle, "Goes live after a quick check, usually the same day. We email you when it is up." in the footer. The signed-in banner covers the handful of accounts and says whether their words post at once (admins) or wait.
- **Login link lands back on the review page**, not the home page.
- **The tire card's size and category are chips** in the tire page's chip style, with "See its page".

### Tests
- `tests/test-database.php`: details persist on both write paths and come back on the list and the user's own review; a blank edit clears them; normalization drops an unknown vehicle, clamps miles, blanks an out-of-range axis; the guest summary never carries the words; per-tire counts only count approved reviews.
- `tests/test-ajax.php`: the guest check answers false, then true with star and month after a guest review, and refuses a malformed email; a guest submit stores the details and drops an out-of-range axis.

## [2.1.0] - 2026-09-05

Roadmap F9: the tire page's owner reviews can be sorted and filtered by star. The reviews drawer left with 1.6x and nothing replaced its browsing affordances; a page with forty reviews was forty cards, newest edit first, and nothing else.

### Added
- **Sort control on Owner Reviews.** Recent, Highest and Lowest in the guide's segmented-toggle style, beside "Write a Review". Rendered only when a tire has more than one review. `role="radiogroup"` with real `role="radio"` / `aria-checked` buttons and arrow-key movement, which is the pattern A11Y1 asks the guide's vehicle toggle to adopt.
- **Star filter chips with counts.** All, then 5 down to 1, each with its approved-review count from the new `RTG_Database::get_tire_review_star_counts()`. A star with no reviews renders disabled, so a chip press never lands on an empty list. A caption under the chips (`role="status"`) says what the list is showing and offers "Show all N".
- **`sort` and `rating` on both review endpoints.** `wp_ajax_get_tire_reviews` and `GET /rtg/v1/tires/{id}/reviews` accept `sort` (recent|highest|lowest) and `rating` (1–5, 0 for all), echo what they applied, and return the filtered total so "Show more (N more)" stays right. `RTG_Database::normalize_review_args()` whitelists both; anything else falls back to the default list.

### Changed
- **"Recent" now means the date on the card.** `get_tire_reviews()` used to order by `updated_at`, which the table bumps on any edit, so a typo fix on an old review sent it to the top while its card still showed the original month. The default order is `created_at DESC, id DESC`; highest and lowest tie-break the same way. `get_tire_review_count()` takes an optional star.
- **Reviews loaded by the page script show their month**, matching the server-rendered cards. `renderReview()` in `tire-page.js` reads the month from the datetime as text so a browser time zone cannot shift a review across a month boundary.
- **"Show more" is hidden, not removed**, once every review is on the page, so a sort or filter change can bring it back.

### Tests
- `tests/test-database.php`: the three sorts, the star filter keeping the sort, the filtered count, the per-star counts with zeros, and argument normalization (an injection attempt and an out-of-range star both fall back).
- `tests/test-ajax.php`: `sort=highest` orders the response, an unknown sort answers `recent`, and `rating=4` filters both the rows and the total.

## [2.0.7] - 2026-09-03

The 2.0 release, consolidated: 2.0.0 through 2.0.6 shipped over one day and are folded into this entry, which describes what is on the site now. The AI Tire Advisor, built the way the 1.18 one wasn't: grounded in the catalog, never a chat box, and useful without a key.

### Added
- **"Help me choose" on the guide.** A button beside Search opens a dialog with three questions (which Rivian, what matters most from seven priorities, budget) and an optional note. The server keeps only the tires that fit the vehicle (size map plus the load-index minimum) and the budget, ranks them with its own rules (`RTG_Advisor::candidates()`, one normalized component per priority plus a small owner-rating boost), and hands the top twelve to Claude with the real numbers. Back come up to three picks with a headline, a reason that cites the numbers, an honest trade-off, and a summary of one or two sentences written in the second person (the prompt says there is no "I" in this advice and asks for "minimum load index", never "floor"). Without an API key the same route answers from the rules with templated reasons and says so, so the button is never dead.
- **The picks dialog.** 800px wide. Each pick leads with the tire's name and the headline, the price on its own to the right (per tire, with the set beneath), then four tiles like the tire page's: efficiency (with the sample or "limited data"), load index (with the vehicle's minimum beneath), warranty and owner rating, each reading "No data", "Not listed" or "No ratings" when empty; then the fitment, size, category and 3PMS chips, the reason, the trade-off with an icon, and "View tire" and "Show in guide" (applies the vehicle, size and brand to the live filters and scrolls to the card with a highlight). The results have no footer: "Start over" sits in the header beside the title and the X closes. The question form's footer is one full-width "Find my tires" that becomes "Thinking…" with a spinner while the request runs, an error in red above it. Tiles fall to two columns below 820px; on phones the dialog is the full-screen sheet with "Start over" at the top right.
- **"What owners say" on tire pages.** Once a tire has two written, approved reviews, its page fetches a cached summary after paint: at most sixty words, up to three strengths and three weaknesses, only claims the reviews support, and never a reviewer's name (the prompt carries ratings, titles, text and month only). Cached thirty days, keyed on the review count and the newest edit, so a new review refreshes it. Placed between the reviews heading and the list.
- **"In plain words" on the compare page.** One paragraph of at most ninety words comparing the tires on the page, loaded after the grid so the grid never waits, cached a day per set of tires. Absent without a key.
- **`RTG_Advisor`.** A raw-HTTP client over `wp_remote_post()` (the plugin's one convention for outbound calls) to the Messages API: structured JSON output through `output_config.format` so every answer is schema-valid, a cache breakpoint on the stable system prompt, `effort: low` on the models that take it, and the server-side refusal fallback (`fallbacks: "default"`) on Opus 5. Every failure path returns the one result shape; the last outcome is stored in `rtg_ai_state` for the settings page. Grounding is enforced after the call too: `validate_picks()` drops any pick that names a tire outside the candidate list. Answers cache in `rtg_ai_*` transients keyed on the inputs, the catalog version (count, newest `updated_at`, rating total) and the model, which `uninstall.php` already sweeps. `hydrate_picks()` carries `load_index` and `mileage_warranty` to the dialog.
- **Routes.** `POST /rtg/v1/advise`, `GET /rtg/v1/tires/{id}/review-summary`, `GET /rtg/v1/compare-summary?ids=`. Public, rate limited per visitor through `RTG_Rate_Limiter` (`rest_ai_*` buckets; the advise limit is a setting, default 10 a minute), the two cached routes behind `RTG_Lock` so a cold cache is written once.
- **Settings.** An "AI Tire Advisor" card on the settings page: the toggle (on by default), the key (stored in the option, or `RTG_ANTHROPIC_API_KEY` in `wp-config.php` which wins and is never shown; the field renders empty and clearing is explicit, the CJ token's rule), the model (Opus 5 default, Sonnet 5, Haiku 4.5), the per-visitor limit, and the last call's outcome with token usage. The legacy 1.45 key names (`ai_enabled`, `ai_api_key`, `ai_model`, `ai_rate_limit`) are reused, so a site that had them set gets them back.
- **Share Image setting** (Settings → Display), read by `RTG_Meta::share_image()`, defaulting to `RTG_Meta::DEFAULT_SHARE_IMAGE` (the site's OG image). The guide page's default social tags use it too; the `?tire=` deep link keeps the tire's own image.
- **Analytics.** Every advise call logs a search event of type `ai` ("R2 · range, quiet · under $350", result count = picks), so the "AI Queries", "Search vs AI Usage" and "Top AI Queries" panels that survived 1.46.0 show data again. The AJAX `track_search` allowlist accepts `ai` once more.

### Changed
- **Load index on the card.** A "Load Index" row in the card's specs, between Size and Mileage Warranty, showing the number alone, so the difference between a 113 and a 121 reads at a glance without opening the tire. "Not listed" when the catalog has none; the fitment warning above the price still flags a shortfall against the vehicle's minimum.
- **The changelog dialog has no footer.** "Open as a page" sits at the end of the list; the X closes.
- **The advisor's footer rules use compound selectors** (`.rtg-review-modal-footer.rtg-adv-footer`), because the review modal's phone rules sit later in the stylesheet at equal specificity and re-stacked the footer in 2.0.2 and 2.0.5.
- Version 2.0.7. No schema change; `search_type` has carried `ai` since migration 10.

### Fixed
- **Tire pages have a social preview image.** The theme-rendered pages (`RTG_Theme_Render`) never emitted `og:image`; a virtual post has no featured image, so every SEO plugin fell back to its site default or nothing. `render()` takes an `image`, handed to All in One SEO through `aioseo_facebook_tags` / `aioseo_twitter_tags`, to Yoast through `wpseo_opengraph_image` / `wpseo_twitter_image`, to Rank Math through its two image filters, and printed as `og:image` / `twitter:image` (with `og:title`, `og:description`, `og:url` and a `summary_large_image` card) when no SEO plugin is present. Tire pages and the changelog page pass `RTG_Meta::share_image()`.

### Tests
- `tests/contract/advisor.php` (plain PHP, in the lint job): input cleaning, the candidate set (minimum, size, budget, ranking by priority, the cap), the request body per model (structured output for all, effort and fallbacks only where they belong, no slugs or scores in the prompt), `validate_picks` dropping an invented tire and a duplicate, `parse_response` on 401, 429, 5xx, refusal, `max_tokens`, non-JSON text and a non-JSON body, the rules fallback's shape, the other two prompts, and the catalog version changing on any edit.
- `tests/test-advisor.php` (PHPUnit, HTTP stubbed through `pre_http_request`): defaults and settings, the settings form (key kept on an empty field, cleared on request), the rules answer without a key plus its `ai` search event, the model answer with a key (invented tire dropped, request shape, cache hit, state recorded), a 401 falling back to the rules, the toggle, an honest empty answer, the review summary needing a key and two written reviews and caching, no reviewer name in the prompt, the compare summary's id rules, and the localized settings. `tests/test-ajax.php` covers `track_search` keeping `ai`.
- `tests/test-meta.php`: the share image default and override, the renderer's image reaching the AIOSEO tag arrays and the Yoast/Rank Math filters, and the printed `og:image` / `twitter:image` when no SEO plugin is present.

## [1.92.1] - 2026-09-03

### Changed
- **The pill says "Changelog".** The button label, its accessible name, the dialog title, the page heading and the document title all read "Changelog" instead of "What's new". The URL, the file and the class names are unchanged.

## [1.92.0] - 2026-09-03

Owner-facing release notes, in the guide and on their own page.

### Added
- **"What's new" in the guide.** A pill in the filter header, left of Clear All, opens the release notes in a dialog built on the review modal's shell (Escape closes, Tab is trapped, focus returns to the pill; a full-screen sheet on phones). The pill carries a dot until this browser has opened the newest release, remembered in `localStorage` under `rtg_seen_version` the way the vehicle toggle is. Under 480px the pill collapses to its icon.
- **`/tire-guide/whats-new/`.** The same notes as a page inside the theme, via `RTG_Theme_Render` like the compare page, with a canonical URL so it can be shared and indexed. Migration 24 flushes rewrite rules on sites updated in place (no schema change).
- **`WHATS-NEW.md`.** The notes' source, written for owners rather than developers: one `## version - date` heading per release, an optional intro line, then bullets that open with a bold lead. Releases with nothing an owner would notice are left out. `CHANGELOG.md` stays the developer record; the two are different documents on purpose, so the public one never has to be filtered out of this one. Backfilled from 1.85.0.
- **`RTG_Whats_New`.** Parses the file (bracketed or plain version, indented continuation lines, preamble and sub-headings skipped) into a transient keyed on the plugin version and the file's mtime, so a release or an edit refreshes it and nothing is flushed by hand. Inline rendering escapes first and then allows bold, code and http(s) links only. One view model feeds the page (server-rendered) and the modal (client-rendered from `GET /wp-json/rtg/v1/whats-new`, which returns the same pre-rendered fields), so the two can't drift. The guide localizes `whatsNewUrl`, `whatsNewRest` and `whatsNewVersion`.

### Tests
- `tests/contract/whats-new.php` runs on plain PHP in the lint job: the parser against a sample covering every shape the format allows, the renderer against a stray tag, a `javascript:` link and a non-http link, and the shipped `WHATS-NEW.md` itself (newest first, every release dated with at least one bullet, the newest not ahead of `RTG_VERSION`, no note naming a file, class, test or migration, and the release rule: the current version has a note or its changelog entry says "Nothing visible to owners", so a forgotten note is a red build). The rule is written up in `CLAUDE.md`'s release checklist. `tests/test-whats-new.php` covers the cache key, a stale cache from another version, the REST route, the rewrite and query var, and the localized settings.

## [1.91.2] - 2026-09-02

### Fixed
- **The tire page photo box now matches the info column's height.** 1.91.1 stretched the box, but the photo's own height was setting the row, so the box ran taller than the text and left dead space under the CTAs. The photo is positioned inside the box, so the info column alone decides the height.

### Changed
- **The passing fitment chip reads "Fits R2".** The load index tile already says "113, R2 minimum is 112"; the chip no longer repeats it. A failing fitment keeps the numbers, since there they are the warning.

## [1.91.1] - 2026-09-02

### Changed
- **The tire page hero shares one bottom edge.** The photo box now stretches to the info column's height instead of a fixed square, with the photo centered in it.
- **One size for every chip and tile value.** Chips are 30px tall with 12px text and 11px icons, the size chip included (it was 13px); a missing tile value reads in the same 20px as the others, muted rather than smaller and italic.

## [1.91.0] - 2026-09-02

The tire page as a product page.

### Changed
- **The model is the H1.** The brand stays in the eyebrow and the size becomes a chip beside the fitment pill, so "BFGoodrich" is no longer said twice and the title no longer reads like a database key. The page title, breadcrumb and JSON-LD keep the full "Brand Model (Size)".
- **One "at a glance" sentence under the title**, generated from the row: "An all-terrain tire that fits the R2, around $275 per tire ($1,100 a set), returning 2.81 mi/kWh across 65 owner vehicles on Rivian Roamer." It is the line a search snippet or an AI answer quotes, and it saves reading four tiles.
- **Four key-stat tiles, always four**: real-world efficiency (with the sample and the limited-data note), average price with the set, mileage warranty, and load index with the vehicle minimum. Weight moved down to the specs. A missing value is a muted "Not listed" rather than a missing tile.
- **One CTA row**: View at the retailer, the official review when there is one, Compare (+), Share. Write a Review lives with the reviews, where it already was.
- **A two-column body**: the specifications in one card with values in a fixed, left-aligned column (a label used to sit 600px from its value), and the related tires on the right as rows that carry efficiency and a "Fits R1/R2" chip, with a caption saying the similar list is sorted by efficiency.
- **Reviews show their month.** The reviewer's vehicle and a sort control wait on F9.
- **A buy bar on phones**: price and "View at …" pin to the bottom once the hero's CTA row scrolls away; hidden until then, and never above phone widths.

## [1.90.1] - 2026-09-02

### Changed
- **The compare button is a plus that becomes a check.** The scale icon read as "justice" before "compare". The button now says what the click does: a plus to add the tire to the comparison, a check once it's in, with the tooltip switching between "Add to comparison" and "Remove from comparison". The tire page's Compare link uses the same plus.

## [1.90.0] - 2026-09-02

### Changed
- **Compare and Share moved off the photo, into the title row.** Four translucent buttons in two corners of a dark tread photo were 55% black on black, and the OEM badge in the opposite corner split attention. The two actions most visitors see now sit right of the brand and model on card color, 32px on desktop and 40px on phones, and the photo carries only the OEM badge. The admin edit shortcut is gone from the card; the tire page keeps its edit bar.

### Removed
- **Favorites.** The heart on the card, the "my favorites" filter and its badge, the tire page's Save button, the three AJAX endpoints, the database helpers, and the `rtg_favorites` table creation. The feature wasn't used enough to earn its place on every card. Sites that already have the table keep it until uninstall, which drops it; migration 7 stays as a no-op so the numbered sequence is unbroken. Roadmap item F10 (guest favorites) goes with it.

## [1.89.1] - 2026-09-02

### Changed
- **"Limited data" shares the last sample line** ("1 vehicle · Limited data") instead of taking a third line of its own, which made that efficiency box taller than the price box beside it.

## [1.89.0] - 2026-09-02

A pass over what the guide card says, from a look at three of them side by side.

### Added
- **Which Rivian it fits.** "Fits R1" / "Fits R2" chips lead the chip row, derived from the stock-wheel size map already on the page, so the "All" view says which vehicle a card is for.
- **The retailer on the button.** "View at Tire Rack" instead of "View Tire". A synced price names its advertiser; a manual link names its host, following one hop into an affiliate tracking link for the destination. New `RTG_Retailer`, resolved server-side and carried at index 31 of the frontend row, so the card, the compare page and the tire page share one hostname map.
- **A small-sample hint on efficiency.** Under three vehicles or 2,000 tracked miles the mi/kWh figure is muted and a "Limited data" line sits under it; the tooltip says so too. A number from one vehicle over 756 miles no longer reads with the confidence of one from 64 vehicles over 68,000.
- **A scale icon on the compare box.** It was a bare grey square next to the heart with no hint of what it did.

### Changed
- **Prices are whole dollars on the card and the compare page.** The card printed the raw value, so one tire read "$442.4" beside two with cents. The guide calls the figure an average, so "$442" is the honest precision, matching the tire page hero.
- **"as of" only when it matters.** Every card carried the same recent date after a bulk edit, which was noise. The date now appears once the price is 30 days old (or stale), while the "may be outdated" warning still fires at the 90-day threshold.
- **3PMS moved from a spec row to a chip.** A boolean that mostly read "No" was spending a full row; it's now a snowflake chip that appears only when the tire is rated, as on the tire page.
- **Mileage Warranty reads "Not listed" when empty** instead of a dash that could mean "none".

### Removed
- **The "RIV" chip.** An internal tag that meant nothing to a shopper, hidden on the card and the compare page.

### Tests
- `RTG_Retailer` (advertiser name, host and subdomain match, the affiliate one-hop, unknown hosts), the "as of" gating in `freshness()`, the 32-column row with its retailer label, whole-dollar formatting and the `show` flag in the pricing module, and the sample-size judgement (`tests/test-efficiency.mjs`).

## [1.88.2] - 2026-09-01

### Changed
- **Compare page header cards stack the image above the details.** Side by side, four columns squeezed each tire's name into a narrow strip beside a thumbnail. The image now sits on top at full card width with the brand, model, size and price below it.

## [1.88.1] - 2026-09-01

### Changed
- **The efficiency box on the card carries its sample.** 1.88.0 gave the price box two extra lines (the set price and "as of"), which left the efficiency box beside it mostly empty around one number. The miles tracked and vehicle count that only the tooltip showed are now meta lines under the figure, so the two boxes read as a pair again.
- **"Add another tire" sits at the top of the compare page**, under the subtitle and above the tire headers, instead of below six sections of specs where nobody scrolled to find it.

## [1.88.0] - 2026-09-01

The advisor release and the tire page as a landing page: eight roadmap features (F1, F3, F4, F6, F7, F8, F12, F13) that turn data the plugin already stores into a decision on the card, and give the page a search visitor lands on somewhere to go next.

### Added
- **Load-index fitment warning (F1).** A tire whose load index is under the vehicle's floor (R1 116, R2 112 — the same floors Tire Discovery gates on) now says so on the card, the tire page and the compare page, instead of leaving the reader to find the rule in a tooltip. With a vehicle pressed, the card is judged against that vehicle; with none, against every vehicle whose stock or alternate sizes include the tire's size, so a 110 in an R1 size is flagged whichever toggle is pressed. The tire page lists every fitting vehicle pass or fail ("R1: load index 118 meets the 116 minimum · R2: …"), because a visitor from a search has pressed nothing. New `RTG_Fitment` and `frontend/js/modules/fitment.js` hold the one rule; the floors ride the localized settings.
- **Set-of-four pricing (F3).** "$289 ea · $1,156 / set of 4" on the card, in the tire page hero and in the compare page's price row and header cards. Nobody buys one tire.
- **Price freshness (F4).** "price as of Aug 28" under every price, from `price_synced_at` or, for a manually priced tire, `updated_at` — the same "last touch" rule the monthly stale-price report uses. Past the report's threshold (`rtg_stale_price_days`, 90 by default) it reads "may be outdated" with a hint to check the retailer. `RTG_Stale_Prices::stale_days()` and `::freshness()` give the report and the shopper one definition of old; the frontend row carries the two timestamps at indexes 29 and 30.
- **Tire page: compare, save, share, and show more reviews (F6).** A Compare link opens the compare page with this tire, Save toggles the favorite in place (a login link for guests), Share uses the native sheet or copies the canonical URL, and a reviews section past ten now has "Show more reviews (N more)" that pages through the existing reviews endpoint without a reload.
- **Tire page internal linking (F7).** "Other sizes of the {model}" (same brand and model, any other size, smallest rim first) and "Similar tires in {size}" (same category when there are at least three, otherwise the whole size, best real-world efficiency first) as link cards between the specs and the reviews. New `RTG_Database::get_other_sizes()` and `::get_similar_tires()`.
- **Compare page: tire-page links, per-column remove, add another tire (F8).** Each header's model name and image link to the tire page, an × on each column drops it from the URL and re-renders, and while fewer than four tires are selected an "Add another tire" search over the catalog appends one — or "Pick from the guide" carries the current selection back to the guide's checkboxes. The column map finally sees `slug`. A single-tire link renders as "One tire so far" with the add panel, rather than "Comparing 1 tires side by side".
- **Vehicle memory and cascade feedback (F12).** The vehicle toggle is remembered in `localStorage` and pressed again on the next visit when the URL doesn't name one (a shortcode's `vehicle=""` and browser back/forward are never overridden). When a vehicle change clears the size select, a polite live-region notice under the filters says which size went and why, instead of the filter vanishing silently. This closes the first half of A11Y8.
- **Distinct empty states (F13).** "No tires in 275/65R20 yet" — an honest answer, with "Browse all sizes" as the only way out — when the catalog has nothing in the size (or the vehicle's sizes), versus "No tires match your filters" with the relaxing suggestions when it does. Client mode decides from the size index; server mode gives the honest answer when nothing but vehicle and size are narrowing.

### Accessibility
- Per-review stars on tire pages carry a "Rated 4 out of 5" text alternative (A11Y6), and the stars loaded by "Show more" build the same markup.

### Changed
- `compare.js` is bundled by esbuild (it was the one frontend script served as a classic file) so it can import the fitment, pricing and vehicle-memory modules rather than carry copies of them.

### Tests
- `RTG_Fitment` (parsing every stored load-index form, named-vehicle vs fitting-vehicle judgement, shared sizes failing for both, verdicts, wording), the freshness label and stale threshold, the other-sizes and similar-tires queries (case-insensitive model match, category preference and widening, ordering, limits), and the 31-column frontend row. Node twins for the fitment and pricing modules (`tests/test-fitment.mjs`, `tests/test-pricing.mjs`) run under `npm test`.

## [1.87.0] - 2026-09-01

All twenty bugs from the v1.86.0 review (since folded into `ROADMAP.md`), plus the dead code that review listed, in one release.

### Fixed
- **The server-side price filter works above $600.** 1.85.0 removed the `< 600` sentinel from the client, but the SQL builder still gated on it and the AJAX handler defaulted an absent value to 600. With server-side pagination on, "under $700" applied no price constraint at all. Any positive ceiling now applies; an absent parameter means no constraint.
- **Sliders no longer run both pipelines in server-side mode.** The slider setup bound the client filter unconditionally and the entry point bound the server one on top of it; in server mode the client pass filtered an empty array, emptied the grid, and flashed "No tires match" until the fetch landed. One mode-aware binding now.
- **The no-results suggestions work in server-side mode.** Every suggestion action called the client pipeline directly; only "Clear all" branched on the mode. Each now re-runs whichever pipeline the page is in.
- **The compare page links to the real guide page.** Its breadcrumb and "Browse Tires" button pointed at a hardcoded `/rivian-tire-guide/` path and broke on any site with a different slug. Both resolve the page hosting the shortcode.
- **Price and link sync flush the cache once per run, not once per tire.** Every write flushed the tire, dashboard, feed and discovery caches, so a hundred price updates were four hundred transient deletes and a cold cache for the next visitor. `update_tire()` takes a flush flag; the syncs pass false and flush at the end.
- **Sync jobs can't overlap themselves or each other.** A five-minute Roamer tick that outlived its interval overlapped the next; "Run Discovery Now" could start while the nightly cron was mid-sweep, double-writing candidates and advancing the sweep cursor twice; the weekly link check and its button could race. Each run now takes a lock (`RTG_Lock`, atomic on both object cache and plain MySQL) that expires on its own if a run dies, and a second caller gets a `locked` result the admin pages show as a warning rather than a failure. Migrations take the same lock, so the requests that pile up after a plugin update no longer each run the same ALTERs.
- **The sort and moderation columns are indexed** (migration 23). `roamer_efficiency` — the default sort for both REST and AJAX — and `created_at` had no index, so every server-side page was a filesort; `review_status` and the search-analytics `session_hash` probe were unindexed too.
- **`bundle_link` can be set from the tire form.** It was a database column, a CSV column and a documented field, but the form had no input and the save handler never read it — CSV import was the only way in.
- **The CSV round-trip keeps `slug` and `roamer_tire_id`.** Export → re-import in update mode used to lose every stable public URL and every Roamer link. Both ride the file now; an imported slug goes through the slug setter (uniqueness, 301 from the old one), and a blank cell leaves the stored slug alone.
- **"Recalculate Efficiency" is reachable.** The handler and its success notice existed; nothing linked to it. It is a button on the tire list.
- **Editing a tire into a collision is caught.** The match-key duplicate check only ran on insert, so renaming one tire into an exact brand/model/size collision with another was accepted silently. The edit path now checks against every tire but itself, stashes the submission, and offers "Save anyway" the way the add form offers "Add anyway".
- **Tire Discovery and Roamer Sync settings save through a redirect.** Both views handled their own POST mid-render, so a browser refresh re-submitted the form. They now go through the same handler-and-redirect as every other screen, and the handlers are callable without the redirect so they are tested.
- **The bulk-delete confirmation is bound to the list form only.** A document-wide submit handler read the bulk-action select from anywhere, so submitting the search form while "Delete" happened to be selected popped the delete confirmation.
- **Sort and pagination links drop one-shot notice parameters.** They were built from the current URL, so re-sorting re-showed "Tire deleted" and kept a page number that may not exist under the new order. Sorting now starts from page 1 with the notice stripped; review pagination strips it too.
- **The delete confirmation says how many reviews go with the tire.** Deleting a tire deletes its ratings; "Delete this tire?" didn't say so. Single and bulk confirmations both name the count.
- **The menu badges no longer run two aggregate queries on every admin screen.** Review status counts and discovery candidate counts are each cached for five minutes and forgotten by every write that can change them (rating writes, status changes, tire deletions; candidate upserts, status changes, re-matching, pruning).
- **The guide's info tooltip no longer leaks a keydown listener per open** (it was removed only on the Escape path, not on "Got it" or the backdrop), traps Tab inside the dialog, returns focus to the (i) that opened it, and carries dialog semantics — the same shape the review modal already had.
- **Brand and model names with an ampersand read correctly to screen readers.** `escapeHTML()` output was written into `alt` and `aria-label`, which take plain text, so "AT&T" was announced as "AT amp semicolon T".
- **A hand-set URL slug survives edits to other fields.** Found by the new CSV test: `update_tire()` regenerated the slug whenever brand, model or size were *present* in the write — and every form save and CSV update carries all three — so editing a price reverted a manual slug to the generated one and recorded a redirect for it. The slug is regenerated only when one of those fields actually changes.
- **Review dates in the JSON-LD use `gmdate()`.**
- **The "Fetch from catalog" image action is rate-limited** (ten attempts a minute per admin). Each attempt can cost up to eight outbound requests at fifteen seconds apiece; the URLs come from the catalog table rather than the request, but nothing stopped a stuck retry loop from spending them indefinitely.

### Removed
- Dead code the review listed: the write-only card clone cache (`state.cardCache`), the search index and Levenshtein matcher nothing had read since typeahead suggestions were removed, the `hideSearchSuggestions` stub, the document-wide click handler that text-matched "Clear All", the handler for a compare modal no template renders, and an unused rating-system initializer. The JS test suite's private copy of the Levenshtein matcher went with it.

### Tests
- `RTG_Lock` (exclusivity, release, expired-lock takeover, and that the catalog, Roamer and link-check jobs report `locked` rather than running twice), the price filter above $600, the quiet `update_tire()` write, review-count cache invalidation, the new indexes and the idempotence of migration 23, the migration lock, the CSV slug/Roamer round trip, the edit-path duplicate check, and the catalog/Roamer settings handlers merging over the stored option.

## [1.86.0] - 2026-08-30

All fifteen medium-priority findings from the v1.84.2 full-codebase review, in one release.

### Fixed
- **The weekly link check now rotates through the whole catalog.** It checked the same alphabetically-first 50 links every run, forever — a catalog past 50 links left everything from ~"H" onward never cron-checked, while the results claimed a full pass. Runs now advance a cursor, carry forward verdicts for links outside the current slice, only email about breakage the run itself found, and the Affiliate Links page says when the last run was one slice ("50 of N links this run"). A manual full check resets the rotation.
- **The five-minute Roamer sync only writes tires whose data actually moved — and feed writes no longer count as edits.** Every run used to rewrite every matched tire: `updated_at` bumped on rows nobody touched (blinding the stale-price report, which reads it), and the hour-long tire cache was flushed every five minutes. Unchanged tires are now skipped, and Roamer columns are written through a dedicated writer that explicitly holds `updated_at` where it was. The per-tire timestamp column now honestly means "data last changed" and is labeled accordingly.
- **The nightly catalog sync raises PHP's time limit for itself.** The browser-started run always did; the cron run didn't, so on hosts where WP-Cron runs under the web default (30–60s) the nightly run was killed mid-flight with nothing recorded — the exact failure RTG_Health exists to diagnose, self-inflicted.
- **The Tire Discovery vehicle counts render again.** The page read `$status_filter` twenty-nine lines before assigning it — a PHP 8 warning, and a `WHERE status = ''` that matched nothing, so the "(N)" counts in the Vehicle dropdown had never rendered on any tab.
- **A partial CSV export is a real backup now.** `model_aliases` (which drive discovery matching, pricing, and delisting) and `bundle_link` were silently dropped from the export the page called re-importable. Both are in the CSV contract, import and export, with the aliases keeping their one-per-line shape; the column reference on the Import page also gained the `review_link` row it had always omitted.
- **A blocked duplicate save keeps everything you typed.** Colliding with an existing tire used to throw away every field except brand, model, and size (and the duplicate-ID case restored nothing at all). The whole submission is now stashed and the form comes back exactly as you left it — tick "Add anyway" and save, without retyping.
- **The compare page's escaping can no longer be bypassed by a stored value.** Any spec value starting with `<` was trusted as markup. Markup-producing renderers now declare themselves explicitly and everything else is escaped unconditionally — which also fixed best-value highlighting for the real-world efficiency row, whose markup had never compared as a number.
- **The two URL allowlists agree again, permanently.** The guide's copy and the compare/review pages' copy had drifted in both directions — five retailers rendered on the guide but their buy buttons vanished on the compare page, two vice versa. The canonical lists now live in one module (`allowed-domains.js`); a new test fails CI whenever `rtg-shared.js`'s copy drifts, the same construction that ended the image-extension drift in 1.83.2. The guide's image validator also accepts `cdn.riviantrackr.com` (it refused the CDN the shared validator allowed), and the shared one is HTTPS-only (it accepted `http:` images the guide refused).

### Changed
- **One rate limiter.** REST kept its own copy keyed on raw `REMOTE_ADDR` with non-atomic transients — behind a proxy or CDN that doesn't rewrite the address, the whole site throttled as one client. Both AJAX and REST now share `RTG_Rate_Limiter`: object-cache-aware atomic counting, keyed on the logged-in user or IP + user agent.
- **The public `/feed` endpoint serves a cached payload.** It's the API's expensive route — the full catalog plus a ratings join, `CORS *` — and was rebuilt on every hit. The built payload now lives in a transient for an hour and is flushed with the tire cache on any write.
- **The discovery queue is honest about truncation and cheaper to open.** The listing caps at 200 rows; a tab whose badge said "Dismissed (900)" silently showed 200. It now says "showing the 200 most recent of N" with the filters as the way in. Opening the page also re-keyed every candidate against the guide on every view — that pass is throttled to once per 10 minutes, shared with the nightly sweep's own pass, and a guide edit clears the throttle so a rename shows immediately.
- **The candidate fuzzy-match table is computed once per run.** Link sync, price sync, and the discovery page each rebuilt it with identical inputs — a similarity comparison of every candidate against every same-brand/size guide entry, tens of millions of string comparisons per sync at catalog scale. It's memoized per request; every candidate or guide write forgets it.
- **`build.sh` is a thin wrapper over the real pipeline.** It was a second, divergent build: 3 of esbuild's 8 targets (a checkout built with it 404'd every admin script) and a no-tooling fallback that stripped `//.*$` — corrupting any line carrying `https://` inside a string. It now runs `npm run build`, full stop.

### CI
- **Committed minified assets can't drift from source.** The build job now fails if `npm run build` changes any `.min.js`/`.min.css` — editing source without rebuilding used to ship stale minified assets silently.
- **PHPUnit runs on PHP 7.4, 8.0, and 8.2** — the plugin's whole supported range — instead of 8.2 alone, so a 7.4-incompatible behavior (not just syntax) fails before it ships.

### Tests
- Roamer change detection (MySQL string forms don't read as changes), the `updated_at`-preserving writer (NULL support, foreign columns ignored), the shared rate limiter (limits, bucket/fingerprint independence, guest UA distinction), candidate `count_matching` vs the listing cap, the CSV round trip for the new columns, the one-shot blocked-save stash, and the allowlist sync check that gates the drift.

## [1.85.0] - 2026-08-30

All ten high-priority findings from the v1.84.2 full-codebase review, in one release.

### Fixed
- **Saving the Settings page no longer wipes the Discovery, Roamer, and CJ configuration.** The handler rebuilt `rtg_settings` from its own 8 fields and replaced the whole option, destroying the ~25 keys the Roamer Sync and Tire Discovery pages store there — CJ credentials included. It now merges over the stored option, the way those pages always have.
- **A CSV import in update mode only writes the columns the file carries.** Every column absent from the header was written as an empty string, so a price-only file blanked size, category, warranty, links, image and tags on every matched tire — and then re-derived the efficiency grade from the emptied specs. Absent columns are untouched now, and the grade is derived from the stored row with the file's values merged over it.
- **Server-side pagination serves the same 29-column rows as everything else.** `get_filtered_tires()` still emitted the 28-column layout from before slugs existed, so `row[28]` was undefined and every card in server-side mode silently lost its link to the crawlable `/tires/{slug}/` page. All three row producers now share one `to_frontend_row()` builder, so the layout can't drift by copy again; a test holds all three to it.
- **Compare works in server-side mode, and compare links stay true over time.** Selections were keyed on positions in the client-side row array — an array that is empty in server-side mode (every checkbox mapped to −1 and did nothing) and that shifts with every catalog change (a shared `?compare=0,3,7` link quietly showed different tires later). Selections and URLs now carry tire ids. Old numeric links still render via a fallback, and the URL path now enforces the same 4-tire cap as the checkboxes.
- **The back button restores exactly the state the URL describes.** Filters whose parameter was absent from the URL were left as they stood, so navigating back to a clean URL kept filtering; the restored page number was then reset to 1; and the URL was pushed *during* popstate handling, adding history entries the back button had to fight through. Restoring now resets absent filters to their defaults, keeps the page number, and writes no history.
- **The price filter works above $600.** The adaptive slider raises its ceiling to cover the priciest tire, but six comparisons still hardcoded the old $600 default — dragging to $650 under a $750 ceiling applied no price constraint at all, and the badge count, chips, no-results hints, analytics and shared URLs each disagreed about whether a price filter was active. Every site now asks the slider for its live ceiling.
- **A cleared "tires per page" field can't take the guide down.** The value was stored unclamped, so an emptied field saved 0 — `LIMIT 0` plus a division by zero (HTTP 500) on every server-side request. Saves now clamp to the form's own 4–48 range, and the AJAX handler guards against any bad value already stored.
- **Every rating request settles.** Calls to the batched ratings loader inside its 50 ms debounce window cancelled the previous caller's timer — and with it the only path to that caller's `resolve`, leaving cards stuck on "No reviews" and, under sort-by-rating, hanging the render pipeline. All callers in a window now share one flush and settle together.

### Added
- **Regression tests for the paths that broke.** The settings merge (CJ credential survival), rows-per-page clamping, partial-CSV column preservation, skip/insert modes, and the 29-column row contract across all three producers. The settings and CSV handlers were restructured so their logic is callable without the redirect-and-exit wrapper — which is what had kept them untested.

### Notes
- Guide URLs that carried the old position-based `?compare=` values were session-scoped (the guide page rebuilt them constantly); only the compare page itself kept long-lived links, and those fall back as before.

## [1.84.2] - 2026-08-29

### Added
- **A listing stranded in Added can be sent back to review in one click.** 1.84.0 returns a listing automatically when the tire it became is deleted, but only for imports that recorded which tire that was. Anything imported before then, and since removed from the guide, was left in the Added tab with no way out — the tab has never carried an action beyond the listing link. Those rows now say so and offer **Return to review**, which puts the listing back through the same door a restore uses: to the queue if it still qualifies, to near misses if it doesn't.

### Notes
- Only rows that genuinely have nowhere to point are flagged. Re-keying runs before the page renders, so by then every imported row that still matches a tire has had that id recorded, and every one whose recorded tire was deleted has already gone back on its own. What remains has no tire answering to it.
- The wording doesn't claim deletion, because renaming a tire produces the same state and is undecidable from here: "removed, or renamed since it was added". If it was renamed, an alias on that tire re-links it; if it was removed, the button is the answer.
- No new endpoint. The button is the existing candidate-status action, which already declines to put a listing back in the queue when it would no longer qualify.

## [1.84.1] - 2026-08-29

### Fixed
- **The "same tire?" hint stopped proposing to merge two different tires.** "Goodyear Wrangler AT/S" was offered as an alias for "Wrangler TrailRunner AT" — they share a family and a type word and nothing else, which scored 0.533 against a floor of 0.5. A hint that is wrong is worse than no hint here, because acting on it files two tires as one. The floor is 0.65 now, named and set from the scores actually observed: 0.533 for that pair, 0.640 for "All Terrain T/A KO2" against "KO3" — both different tires — and 0.667 for "…Adventure with Kevlar" against "…Adventure Kevlar", which is one tire spelled two ways and the case the hint exists for.
- **The hint only appears on a row still awaiting review.** It was rendering on rows already added to the guide, where "same tire? add an alias" is advice about a decision that was taken — the row in question was in the Added tab, with no action left on it but the listing link.

### Notes
- Nothing about matching changed. This governs only what the queue suggests; the threshold that actually files a listing under a guide tire is untouched.
- 2 tests, one for each rejected pair, alongside the existing one proving a real spelling difference is still offered.

## [1.84.0] - 2026-08-29

### Added
- **Delete a tire from the guide and its listing comes back to the queue.** "Imported" was permanent: a listing added to the guide and then removed from it stayed marked as added, so it was in neither the guide nor the review queue. Nothing said so, and nothing would ever have — the one way a tire could go missing silently. Every re-key now checks that the tire an imported listing became is still there, so removing a tire puts its listing back where it can be reconsidered.
- **The tire a listing becomes is recorded at import.** That id is what makes the deletion recognizable later; a listing imported before this simply notes the tire it still matches the next time the queue reconciles, and is covered from then on.

### Notes
- **Only the recorded id decides it.** Editing the model on the way into the guide is routine — the queue's own hint suggests it — and re-matching by name would resurface every tire that had been renamed, which is not a deletion. An id that is no longer in the guide can only mean one thing.
- A returned listing answers to the rules again rather than landing in the queue by right: one that would no longer qualify goes back to near misses.
- A dismissal is untouched. It is a standing judgement about the listing, not a claim about the guide, so nothing in the guide revisits it.
- The check runs wherever re-keying already does — opening Tire Discovery, and the nightly sweep — so no new schedule and no new query.
- 5 tests: the round trip, a returned listing still failing the rules, a rename **not** resurfacing anything, a dismissal surviving, and an older import recording its tire and then being covered by a deletion.

## [1.83.2] - 2026-08-26

### Fixed
- **An AVIF image downloaded fine and then wasn't shown.** The importer accepts and saves six formats — jpg, jpeg, png, webp, gif and avif — but the guide's own URL check only allowed four of them. A retailer's AVIF was fetched, named and written into the images folder exactly as intended, and the card then refused to display it. Both lists now hold the same six.
- **It failed in the worst possible way, which is why it looked like a download problem.** A card whose image URL doesn't pass renders with no image area at all rather than a broken one, so a refused format is indistinguishable on screen from a picture that was never fetched. The two warnings that exist for this case listed their own extensions and didn't include AVIF either, so nothing reached the console.

### Changed
- The extensions live in one exported list (`IMAGE_EXTENSIONS`) that the URL pattern and both warnings are built from, so a format can't be half-supported again. The list names `RTG_Tire_Images::KNOWN_EXTENSIONS` as the set it has to match.
- The copy of this check in `tests/test-validation.js` had drifted the other way — it allowed GIF where the real code didn't — so it would have passed a URL the guide rejected. It's built from the same list now.

### Notes
- SVG is still refused, and refusing it is the point of having a list at all: it can carry script. Host and protocol checks are unchanged.
- Nothing else was affected. The compare and review pages use the shared validator, which checks host and protocol but not extension, so they were showing these images all along — the guide's cards were the only place they vanished.
- If an AVIF still doesn't render after this, the next thing to check is the server: a host whose MIME table predates AVIF serves the file as `application/octet-stream` and the browser won't paint it. That's a one-line `AddType image/avif .avif`, not a plugin change.

## [1.83.1] - 2026-08-26

### Fixed
- **Image requests are made in HTTP/1.1.** WordPress asks in HTTP/1.0 unless told otherwise, and no browser has spoken 1.0 in decades — to anything watching for automated traffic that is a louder signal than any header could undo. It was the one clear anomaly left in a request that already carried a browser's user agent, referer and Accept.
- **The first attempt is shaped like typing the URL into the address bar.** The retailer refusing us serves that same image to a browser navigating straight to it, so the first ask now matches what demonstrably works: a document `Accept`, `Sec-Fetch-Mode: navigate`, `Sec-Fetch-Site: none`, no referer. The retry keeps the hotlink shape — image `Accept`, referer, `Sec-Fetch-Dest: image` — which is what a CDN checking for hotlinking looks for. Two attempts, each mimicking a request that gets served for a different reason.

### Notes
- Client-hint headers (`sec-ch-ua` and friends) travel with both, consistent with the user agent already claimed rather than a mixture.
- **This may not be enough, and the reason is worth stating.** A filter keyed on the TLS or HTTP/2 fingerprint of the client rather than on anything in the request cannot be satisfied from PHP, whatever the headers say. Where that is what's happening the honest answer is a file placed in the images folder by hand — an existing file always wins over a download, so "Fetch from catalog" then picks it up without touching the network — and meanwhile the retailer's own URL keeps working for visitors, since browsers are exactly who it serves.

## [1.83.0] - 2026-08-26

### Added
- **A retailer that won't hand over its picture no longer costs the tire an image.** The catalog knows every retailer carrying a tire, but the import only ever tried one URL — so a Tire Rack image answering with a page meant no image at all, even when SimpleTire was listing the same tire with a working one. Both the Add-to-Guide import and "Fetch from catalog" now work down every image URL known for the tire, freshest first, and keep the first that downloads.
- **Requests ask for an image the way a browser does**, with `Accept` and `Accept-Language` headers. Sending neither is one of the tells a CDN's bot filter uses before answering a picture request with a page.

### Fixed
- **The refusal message no longer blames the feed for the server's answer.** 1.82.1 said the URL "points at a page rather than a file" — but the URL that prompted this is `…/content/dam/tires/pirelli/pi_scorpion_winter_full.jpg`, a real file on the retailer's own image domain. The server is refusing us, which is a different problem with a different fix, and the message said the wrong one.
- **It names the page that came back instead.** A CDN's bot wall and a soft 404 both answer 200 with HTML and are told apart by almost nothing else, so the page's own title now travels with the reason — "Pardon Our Interruption" against "Page Not Found | Tire Rack". That distinction is the whole diagnosis.

### Notes
- When every URL is refused the reason says how many were tried, so a run of refusals can't read as "the catalog has no image for this tire".
- Ordering is by freshest sighting of each distinct URL, so a retailer that reshuffled its CDN still sinks below one that hasn't.
- **At most four sources are tried.** A refused URL costs two requests — plain, then as a browser — at 15 seconds apiece, and "Fetch from catalog" answers a browser waiting on AJAX. Four is two minutes of worst case, inside a default PHP limit; the reason says how many of how many were reached.
- 8 tests added, including the reported URL and its exact symptom, that the message doesn't claim the feed pointed at a page, the cross-retailer fallback saving the second retailer's file, and the cap.

## [1.82.1] - 2026-08-26

### Fixed
- **A tracked link in the feed's image field is unwrapped before the download.** A network's click URL isn't a file: following one lands on its redirect page — HTML, not an image, which is exactly what a "text/html" refusal looks like — and registers an affiliate click nobody made. The plain destination is fetched now, through the same unwrapper the product links already use. A URL that isn't wrapped comes back untouched, query string and all.
- **A page where an image should be is retried once as a browser.** The other way a CDN answers an image request with HTML is hotlink protection: no referer, so it decides this is a bot and serves a 403 or a 200 "access denied" page. The retry sends a referer from the image's own origin, which is what the CDN expects to see. A 404 or a transport error is not retried — asking again more politely won't conjure a file that isn't there.

### Changed
- **A refusal that survives both attempts names the URL it actually fetched**, and says the referer was tried, so the next report of this diagnoses itself instead of starting another round of questions. The remaining cause a retry can't fix — a feed whose image field holds a product page rather than a picture — is visible in that line.

### Notes
- Everything the downloader already refused, it still refuses: a body that isn't a readable image whatever the header claimed, a truncated file at the size cap, an unwritable folder. The two new attempts feed the same checks.
- 6 tests added: the tracked link unwrapped and fetched, an ordinary URL left alone, HTML and 403 each retried with a referer, a 404 not retried, and the failure sentence naming what it fetched.

## [1.82.0] - 2026-08-26

### Fixed
- **An alias pasted with the brand on the front now works.** The queue's own hint says to add "this listing's name" as a model alias, and the name it shows carries the brand — "Toyo Open Country A/T III EV All Terrain". Keys hold the brand separately, so pasting that put the brand in twice and the alias matched nothing: the tire stayed in the review queue with no sign of why. Both forms are indexed now, so the natural paste works and a model-only alias is unchanged. The trap was ours, not the admin's.
- **A load rating that disagrees stops a name-only match.** "Scorpion XTM AT" sits inside "Scorpion XTM AT Elect All Terrain", so the name-drift pass filed an EV listing under the non-EV tire and the queue linked to the wrong one — 116 against the listing's 119. Two tires of one brand and fitment whose ratings differ are the ordinary shape of a variant, not two spellings of one tire, so the rating is now checked before a name comparison may claim a match.

### Changed
- **The near-name hint says what to paste, and what doesn't add up.** It names the exact alias text rather than "this listing's name", and where the ratings differ it says so — "Guide already has Scorpion XTM AT in this size at load 116, where this listing is 119 — usually a different tire" — instead of proposing an alias that would bury the listing under a tire it isn't.

### Notes
- Only a rating known on both sides counts against a match. A feed that omits one is silent, not contradictory, and an exact name is never overruled by a rating — a feed reporting one loosely must not un-match a tire the guide names exactly.
- The rating narrows rather than empties: where the guide holds both the 116 and the EV-specific 119, the listing now finds the right one instead of being turned away as ambiguous.
- 6 tests added over both reported rows, the guards around them, and what counts as disagreement (blank, zero, and a dual "119/116" reading as its single-wheel figure).

## [1.81.0] - 2026-08-26

### Changed
- **Sizes with no tires leave the list too.** The exemption 1.80.1 made for size is gone: a "(0)" is noise wherever it appears, and the size list is already scoped to the chosen vehicle's fitments, so what drops out of it is a size that vehicle takes but nothing in the current filters comes in.
- **A wheel-size heading with nothing under it goes with them.** Sizes sit in `19" Wheels` / `22" Wheels` groups, and a heading left standing over an empty group reads worse than the zeroes did. It comes back, in its place, when one of its sizes does.

### Fixed
- **Restored options land where they belong, not where an off-by-N put them.** Position was being read out of a list that the previous detach had already shortened, so every option stamped after the first removal recorded the wrong index — enough to quietly break alphabetical order in the brand list once two or more brands came back. Positions are now recorded before anything moves. Caught by the new test, not in the browser.

### Notes
- Restoring is by remembered position rather than by re-sorting, because these lists aren't ordered the same way — brands read alphabetically, sizes sit in numbered wheel groups — and an option belongs where it was, not where a comparison would put it.
- Changing vehicle rebuilds the size list from scratch, so anything set aside from the old list is dropped rather than reinserted as a stale node. The test drives the real rebuild to prove it.
- `tests/test-dropdown-options.mjs` is up to 17 checks, now covering grouped lists end to end: an emptied heading leaving and returning, a selected size holding its place under its heading at zero, and the vehicle rebuild.

## [1.80.2] - 2026-08-26

### Fixed
- **The empty brands actually go now.** 1.80.1 marked them `hidden`, which browsers honour unevenly inside a native select popup — Safari renders such options regardless — so on macOS the "(0)" rows were still on screen. They are removed from the list instead, which is the one thing every browser agrees on, and put back in alphabetical order the moment they have tires again.

### Added
- **The guide's first DOM-level test** (`tests/test-dropdown-options.mjs`, wired into `npm test`). Both bugs here were invisible to reading, so the option-list logic is now driven directly against a mini-DOM: empties leave, the selected option survives at zero, restored options land in order rather than at the bottom, size keeps its zeroes, and an option a cached page left `hidden` is un-hidden on the way back in.
- The reconciliation moved into `applyOptionCounts( select, counts, hideEmpty )` — a select and a Map rather than a read of the page — which is what makes it drivable at all.

### Notes
- Detaching an option has one hazard the test now pins down: setting `select.value` to something that isn't in the DOM silently clears the select instead. Anything restoring a filter from outside the UI — the back button, a URL, a shortcode — reattaches the full list first, and the next render sets aside whatever is still empty.
- Size still keeps its zeroes, unchanged from 1.80.1.

## [1.80.1] - 2026-08-26

### Changed
- **Brands and categories with nothing to show are hidden, not listed as "(0)".** Filtering to a size left the brand list mostly zeroes — a couple of dozen "Falken (0)", "Kenda (0)", "Toyo (0)" rows to scroll past to reach the four brands that actually make a tire in that fitment. An option that would return nothing isn't an answer, it's noise, so it now leaves the list until it has something in it again.
- **Size is deliberately exempt.** People arrive knowing their fitment and look for it by name; "your size, and nothing in it" is a real answer and worth seeing, where a brand that doesn't make that size never was.

### Notes
- The option you have selected stays listed even at zero — a filter you can't see in the list is one you can't reason about or undo from it. That's the only case where a "(0)" still appears.
- Counts are computed with each dropdown's own filter removed, so switching brands never changes what any brand counts: the list only shifts when the filters around it do.
- Server-side pagination mode is unaffected — it has never rendered counts, since the browser holds one page of rows rather than all of them.

## [1.80.0] - 2026-08-26

### Added
- **Edit a tire from the front of the site.** Spotting a wrong spec while browsing the guide meant remembering the tire, opening the admin, finding it in the list, and editing it there — enough friction that small corrections didn't get made. Tire cards now carry a pencil beside Share, and the full-specs page carries an "Edit this tire" row above the breadcrumb. Both open the tire's edit screen in a new tab, so your scroll position and filters survive the trip.
- **The edit screen answers to a tire_id.** It had only ever been addressed by database row number, which is the one identifier nothing outside the admin holds — a card, a tire page and a REST payload all carry the public `tire_id`. It now takes either, and a tire reached by `tire_id` still reports the row number the form posts back, so the save path never learns there were two ways in.

### Notes
- Visitors are never sent the control, not merely shown a hidden one: the cards are built in JavaScript from localized settings, and the edit URL is localized as an empty string for anyone without the capability — so there is nothing in the page to reveal, and nothing for the script to remember to hide. The capability is the same `manage_options` every admin screen here is registered under, named once (`RTG_Admin::EDIT_CAPABILITY`) so the two answers can't drift apart.
- The tire page's row is dashed and labelled "Admin — only you can see this", because an editing control that looks like site furniture is one someone eventually screenshots.
- Both surfaces render for logged-in users only, and page caches bypass logged-in requests, so a cached page can't carry an admin's edit link to a visitor.
- Suite up by 5 tests: the capability answer for admin, subscriber and logged-out; the URL shape and its base; resolution by either address; unresolvable addresses meaning "adding" rather than a fatal; and that a visitor's localized guide data carries no edit URL at all.

## [1.79.0] - 2026-08-26

### Fixed
- **Tires already in the guide stopped arriving as new options.** Two of them were sitting in the review queue with an Add to Guide button beside tires the guide has carried for months. The matcher only ever recognized a listing spelled exactly the way the guide spells it, and retailers don't: Goodyear's "Wrangler All-Terrain Adventure with Kevlar" is listed as "Wrangler All-Terrain Adventure", Pirelli's "Scorpion Zero All Season" as "Scorpion Zero". A key built from the shorter name misses the tire that already carries it, and the queue then offers a tire you stock as something to review — which is the queue lying about the one job it has.
- **A name-drift pass now runs when the exact keys miss.** Same brand, same fitment, and a model name that is the other one with words added or dropped — the containment rule the coverage screen already uses to report a tire as "listed under another name". Both screens now hold one opinion about what counts as the same tire instead of two.
- **The queue reconciles with the guide when you open it.** Re-keying only ran inside the nightly sweep, so a tire added or renamed by hand didn't reach the queue until the next run — the admin's own edit sat unnoticed for a day. Opening Tire Discovery re-points every stored match at the guide as it stands, so what the page shows is the guide as it is, not as the last sweep left it.

### Added
- **A near-name hint on rows still called new.** When a listing's brand and fitment sit on a guide tire whose name resembles it but not closely enough to match — "Wrangler All Terrain Adventure Kevlar" against "…All-Terrain Adventure with Kevlar" — the row says so and links to that tire, so the fix is one alias rather than a second entry nobody notices for months.
- **The hand-add duplicate guard reads the same drift.** Typing a tire in under a retailer's shorter name now collides with the guide entry carrying the manufacturer's full one, with the same "Add anyway" override for a deliberate second entry.
- **Retailer coverage counts a drifted name too.** A retailer carrying the tire under the shorter name now counts as carrying it, so coverage says so and the tire's price has somewhere to come from.

### Notes
- Two claimants mean no match. A listing called "Scorpion" sits inside both "Scorpion Zero" and "Scorpion Verde", and picking either would file it under a tire it may not be — so ambiguity resolves to "new", which costs one dismissal. A false "new" is visible and cheap; a false "already have it" hides a genuine find.
- A match made on the name rather than the guide's own spelling says so on the row and keeps its Add to Guide button, so a reading of two names as one tire stays overrulable rather than final.
- Suite up by 11 tests covering the drift pass, its guards, the ambiguity rule, reconciliation, and that a person's dismissal still outranks whatever a re-key concludes.

## [1.78.3] - 2026-08-26

### Added
- **A "Fetch from catalog" button on the tire edit form.** The automatic image import only runs when a tire is first added from discovery — there was no way to retry a failed download or backfill an existing tire's image, and clearing the field and re-saving (as 1.78.2's notice wrongly suggested) did nothing. The button works on any tire, any time, from the form's current field values: it finds the freshest catalog image for the brand, model (aliases included) and size, downloads it into the images folder, fills the field, and shows the preview — or tells you exactly why it couldn't, right next to the button. No save round-trip; failure is a sentence, not silence.

### Notes
- The freshest sighting wins when several candidate rows carry images — a retailer that reshuffled its CDN leaves stale rows pointing at dead URLs. A tire the catalog has never seen says so honestly instead of failing vaguely.
- The 1.78.2 fallback notice now points at the button as the retry path.
- Textareas now match every other form control — the shared input styling covered every input type and `select` but never `textarea`, so the model-aliases box rendered with browser defaults beside fields that all match.

## [1.78.2] - 2026-08-26

### Fixed
- **A failed image download now says why, instead of silently hotlinking.** The first real Add-to-Guide import fell back to the retailer's image URL with no explanation, leaving nothing to fix. Every refusal in the downloader now names its failing step — request error, HTTP status, wrong content type, empty or over-cap body, unreadable bytes, folder can't be created, folder not writable, write failed — and the tires screen shows the reason in the "added" notice when a fallback happened, with the recovery path (fix the cause, re-save with the image field cleared).
- **The download sends a browser user agent.** Image CDNs routinely answer HTTP 403 to WordPress's default agent as bot traffic — the single most likely cause of that first failure.
- **A body that reaches the size cap exactly is refused as truncated.** `limit_response_size` cuts a too-large file off mid-stream without erroring; saving it would store a broken image that looks fine in a directory listing.

### Changed
- **The recurring status text now states facts instead of teaching lessons.** The status areas had been written like documentation — every run re-explaining why each number is true, forever. The explanations earned their place the first time each situation appeared; after that they were ink. Tightened across Tire Discovery and Affiliate Links: the run line ("Run: 65.0s of 75s (browser cap — the nightly run gets 120s)"), the housekeeping line ("Pruned 15,421 near misses (15,421 off-fitment, 0 unseen 60+ days)"), the fitment-coverage preamble (down to the two rules that matter: complete means an absence is real; a red Distinct means "complete" means re-read), the uncovered-tires explainer, the delisted notice, the brand-policy hint, the Link Sync card (counts up front, rules pointed at rather than restated), and the page intro.
- Where the long form stays, deliberately: **settings help** (read rarely, on purpose) and **error messages** (when something is wrong, the why is the point). No behavior changed — copy only; suite unchanged at 219 tests, 560 assertions, green.

## [1.78.1] - 2026-08-25

### Fixed
- **Run Discovery Now no longer dies with a misdiagnosed 524.** Cloudflare answers 524 when the origin hasn't replied within about 100 seconds — a proxy timeout, not a PHP error — but the run-failure message filed every 5xx under "an error inside the run; check the PHP error log," sending the admin to a log with nothing in it. Two fixes, one for the message and one for the cause:
  - Browser-started runs now cap their budget at 75 seconds (`INTERACTIVE_BUDGET`), whatever the configured budget says, so the whole request — sweep, re-key, prune, link sync, price sync — answers before the proxy hangs up. The nightly cron run doesn't answer to a proxy and keeps the full configured budget; the rotation cursors mean a capped run still makes progress, never loses coverage. The run stats now record which budget actually applied, and the status line says when and why it was capped instead of showing a ceiling the run never had.
  - A 524 or 504 now gets its own message: a timeout at the proxy, not an error in the run — which usually keeps finishing on the server, so the status below shows it after a refresh.
- The likely trigger: minting tracked links (`linkCode`, since 1.76.0) makes CJ measurably slower per request, which pushed a previously ~54-second sweep past the proxy's limit. The cap absorbs that; the suite lands at 219 tests, 560 assertions, green.

## [1.78.0] - 2026-08-25

### Added
- **Duplicate tires can no longer be hand-added by accident.** The discovery queue was already safe — a candidate matching a guide tire files under Existing before anyone sees an Add button — but a hand-typed tire had only the tire-ID uniqueness check, and IDs auto-generate, so the same physical tire could quietly get a second entry. Saving a new tire now runs the same recognition the matcher lives by: brand and size normalized, punctuation squashed ("Defender LTX M/S 2" collides with "Defender LTX M/S2"), and model aliases expanded on **both** sides, so a retailer's spelling collides with the guide tire that carries it as an alias.
- **Blocked, not silently merged.** A collision bounces back to the form with the brand, model, and size still filled in, a notice naming the existing tire with an **Edit the existing tire** link — and an explicit "Add anyway — this is deliberately a separate entry" checkbox for the rare legitimate case (an OEM variant kept separate, for instance). The default path protects the guide; the override is one visible tick, never assumed.

### Notes
- The check runs only on *new* tires — editing an existing tire never trips it, and the discovery Add-to-Guide flow passes through it too, as a second net behind the queue's own Existing filing.
- The suite lands at 218 tests, 554 assertions, green — the new tests pin the guard's reach (punctuation, aliases in both directions) and its restraint (a different size is a different entry; an unkeyable tire can't be blocked).

## [1.77.0] - 2026-08-25

### Added
- **Add to Guide now brings the product image with it.** The catalog has always captured each product's image URL; importing a tire still meant finding a photo by hand. Now, saving a tire from discovery with the image field left blank downloads the candidate's product image into the site's tire images folder, named the way the hand-added ones are (`brand-model` slug, extension from what the server actually sent) — so imported tires get a permanent, self-hosted image instead of a hotlink to a retailer CDN. The Add form shows a preview of the catalog image with a note saying exactly what saving will do.
- **A file you placed always wins.** If an image by that brand-model name already exists in the folder, it is reused without a single network request — which also means a second size of the same model shares its sibling's photo, and imports slot into the existing naming convention instead of inventing a parallel one.
- **Tire names in the discovery queue link to the retailer's product page**, in a new tab — and deliberately to the *plain* page, not the tracked link, so reviewing a tire never registers in the affiliate network's click statistics. The unwrapping handles CJ's both link shapes (destination in a query parameter, including double-encoded, and appended to the path) and passes a direct link through untouched.

### Notes
- The image URL comes from the affiliate feed — external data — so the fetch is defensive, and the tests pin the refusals: `wp_safe_remote_get` (no redirects into private address space), an image content type required, the bytes verified as an actual image whatever the header claims, a 5 MB cap, and only over HTTP(S). If the download fails for any reason, the tire keeps the remote URL rather than nothing — visible on the edit screen, replaceable any time.
- Typing a filename or URL into the image field disables all of this for that save; the automation only fills silence, never overrides a choice.
- The folder path (`assets/tire-guide/images/` under the WordPress root) is filterable via `rtg_tire_images_dir` for installs whose site root and WordPress root differ.
- The suite lands at 215 tests, 549 assertions, green.

## [1.76.0] - 2026-08-25

### Added
- **Automatic affiliate link sync.** Every daily sweep now fills in and upgrades tire links from the CJ catalog, using the same classification as the Affiliate Links page. A tire with **no link** gets the cheapest tracked listing for its exact tire; a tire with a **regular retailer link** is upgraded to the tracked equivalent — but only for the *same retailer*, so a deliberate Tire Rack link is never silently swapped to Discount Tire just because it's cheaper. A tire that already has an affiliate link is otherwise never touched.
- **Delisted links move to a retailer that still carries the tire.** The one exception to "never touch an affiliate link", and it exists because a link to a delisted product still resolves — and earns nothing. When the retailer a link points to has dropped the tire (unseen for 3+ days in a completely-read fitment) while another retailer lists it with a tracked link, the link moves to the cheapest such listing — in either direction between retailers, from affiliate and regular links alike. Two refusals keep it honest: a fitment the sweep didn't read completely can never support the claim (our own gap must not masquerade as the retailer's decision — the same rule delisting detection lives by), and a retailer the catalog *never* listed the tire under is not "delisted" (it may simply not be in CJ), so a hand-placed link to one stays put. Delisted with nowhere to move lands in the report, not in silence.
- **Tracked links from CJ itself.** The catalog query now asks CJ to mint the tracked click URL (`linkCode`) for each product, keyed to a new **Website ID (PID)** setting — the first number in an existing link like `click-101098512-13697786`. Until the PID is entered, CJ returns plain retailer URLs and link sync correctly refuses to apply them; the settings help text and the sync's own skip reasons both say so.
- **A Link Sync card on the Affiliate Links page.** Shows the last run, how many links were set, upgraded, and moved off delisted retailers, and — more usefully — an expandable list of every tire link sync looked at but could not fix, each with the reason: not in the catalog, catalog listing gone stale, retailer only listed elsewhere, delisted with no tracked alternative, or no tracked link because the PID isn't set.

### Notes
- Guardrails, stated as rules the tests pin: an affiliate link is never overwritten; catalog listings older than 3 days are ignored (a delisted product's leftover row can't donate its link); retailer identity is compared through the same normalization the price sync uses, so "The Tire Rack" and "Tire Rack" are one retailer; and when the only candidates are untracked, the skip reason names the missing PID setting instead of vaguely failing.
- Link sync runs *before* price sync in the daily sweep, so a freshly applied link gets a price attributed in the same run rather than a day later.
- The toggle lives next to the other sweep settings on Tire Discovery, on by default — it only ever adds or upgrades links, never removes one.
- The suite lands at 205 tests, 529 assertions, green — the 14 new tests are written refusals-first: what the sync must *not* do (touch affiliate links, use stale rows, switch retailers on a live listing, apply untracked URLs, claim a delisting from an unread fitment or against a retailer the catalog never knew) is pinned before what it does.

## [1.75.0] - 2026-08-25

### Removed
- **The direct-lookup pass, wholesale.** Shipped in 1.68.0, disabled by default since 1.70.0 after its own instrumentation proved the approach unworkable — a single brand-and-model keyword matched 81,653 products, of which one request reads about 1%. It has been dead weight since: three settings, a second budget, a rotation cursor, a per-term fetch path and a status panel, all guarding a pass nobody runs. Roughly four hundred lines gone across the source, the sync, the settings screen and the tests.
- **The Google product category filter.** Documented as a trap since 1.64.1 — CJ applies it server-side, Tire Rack sends no category, so any configured value silently drops that retailer while the falling match counts look like success. A setting whose only safe value is blank is not a setting; it is an accident waiting for a click. A test now pins its absence rather than its default.
- **The JSON fixture source and its feed-URL setting.** Development scaffolding from before the CJ adapter existed. Its fallback seeded the bundled sample into real queues whenever CJ was unconfigured — which is where the "Sample Retailer" rows in production came from.

### Notes
- Migration 22 sweeps up what the retired features stored: the targeted-pass cursor option, the five orphaned settings keys, and every fixture-sourced candidate row (the "Sample Retailer" entries disappear with it).
- Health no longer flags "read zero products" when no source is configured at all — with the fixture fallback gone that is a setup state, not a breakage, and the settings screen already says so.
- Kept deliberately, despite looking quiet: the editable GraphQL document (the escape hatch that recovered the schema mismatch), and the keyword probe with its offset (the instrument that settled what CJ's search actually does). Diagnostic tools earn their keep on the bad days.
- The suite lands at 191 tests, 497 assertions, green — smaller than 1.74.0 by exactly the tests that covered what was removed.

## [1.74.0] - 2026-08-25

### Added
- **The full PHPUnit suite now runs — locally and as a gating CI job.** The ~75 database-backed assertions written across this feature had never executed anywhere (no WordPress test library, no MySQL), a caveat restated in a dozen changelogs while three real defects shipped through the gap. A CI job now stands one up (MySQL service + the WordPress test library from wordpress-develop) and gates every PR. First execution found and fixed: a test helper colliding with PHPUnit's own `at()`, one test whose fixture contradicted its own name, and one asserting pre-vehicle-aware behavior the design had deliberately replaced. The suite ends this release at **206 tests, 529 assertions, green.**
- **Model aliases.** Retailers spell a model their own way — "Ridge Grappler LT" for the guide's "Ridge Grappler" — and matching, coverage, pricing and delisting all key on the model. A tire now carries alternate names (one per line on the edit form), every consumer keys on all of them through one helper, and the coverage report's "likely listed under another name" rows grow a one-click **Adopt as alias** button. Writing the tests caught a real bug before it shipped: splitting an empty alias field yields one empty line, which would have given every alias-less tire a bogus `brand||size` key — colliding same-brand tires in the guide index and matching any candidate whose model failed to parse.
- **Bulk queue actions.** Dismiss (or restore) everything the current filter matches — the database query, not the visible page, and the confirmation says so. A brand filter with counts joins the queue filters, because queue volume clusters by brand: a page of one budget brand is one decision, not sixty. A hint above the queue counts how many waiting tires are from brands outside the curated list and points at the existing brand policy's *reject* setting. Bulk writes only `dismissed` or `new`, over queue or dismissed rows — an import can never be bulk-overwritten, and a mistaken sweep is reversible by the same route.
- **Stale-price visibility.** Covered tires re-price daily; the rest update only when a person edits them, and nothing measured how long ago that was. The uncovered-tires table now shows each price's age (red past 90 days), and a monthly email lists the untouched ones oldest-first. A sync or a manual edit both reset the clock — the report is a checklist, not a nag.
- **Candidate retention.** Each sync now deletes near misses that can never become anything else: rejected rows in fitments the guide doesn't stock (wrong fitment is permanent — ~18,000 of these had accumulated), and rejected rows unseen for 60+ days (the catalog itself dropped them). Only machine-rejected rows are ever touched — dismissed and imported rows are human decisions and are kept forever — and an empty size list deletes nothing, since "off-fitment" would be undefined. Counts appear in the sync status; a pruned product that reappears is simply re-filed by the next sweep.

### Notes
- Migration 21 adds `model_aliases` to the tires table.
- The alias, bulk, stale-price and prune behaviors are all covered by executing tests, guardrails first: imports untouchable in bulk, human decisions unprunable, the empty-size-list refusal, both clock-reset paths.

## [1.73.0] - 2026-08-25

### Added
- **The pipeline now reports its own failures.** Everything downstream of the sweep — pricing, coverage, delisting detection — degrades silently when the sweep stops, and every failure this feature has actually had would have been invisible until someone opened the admin: a rotated CJ token failing every run with a 401 (which has already happened once), a GraphQL schema change erroring every run, WP-Cron simply not firing, a fitment quietly no longer read to completion. The digest email only fires on success, so success was loud and failure was mute. `RTG_Health` now judges each run's own records and emails the admin — **once when a problem appears and once when it clears**, so a week-long outage is two emails, not seven. A rejected token is named as such, with its fix, rather than reported as a generic failure.
- **Delistings email as they are detected**, rather than waiting as a badge for someone to visit: the daily sync compares the delisted set against what was already reported and mails only the difference. A tire that recovers and is dropped again alerts again.
- **A dead schedule is caught from admin visits.** The sync's own cron hook cannot report a schedule that never fires it, so any wp-admin visit also probes, throttled to once per six hours. The settings page now also documents the genuinely reliable setup: a real server cron hitting `wp-cron.php` with `DISABLE_WP_CRON` set.
- A Health Alerts toggle beside the digest setting, on by default.

### Notes
- The evaluation is a pure function of the run's stats, and both it and the once-per-outage email lifecycle are pinned by `tests/contract/health.php`, which executes in CI. Verified in both directions: the check was run against a sabotaged build with the alert memory wiped, where four assertions fail — it is not decorative.
- Writing that check caught a real gap before it shipped: the first version's guard against flagging coverage on stats that carry none used "at least one fitment was read completely" as its proxy, which silenced the worst case — every fitment regressing at once. The guard now tests for the presence of coverage data itself, and both cases are pinned.

## [1.72.0] - 2026-08-24

### Added
- **Delisting detection on the Affiliate Links page.** A broken-link check asks whether a URL still resolves, and a tire dropped from the affiliate catalog passes it: the retailer's page is still there, the link still redirects, and the product has quietly been removed from the feed the commission and the price come from. That is invisible from the URL and plain in the sweep's own history, which records when each listing was last seen. Tires now carry a **Delisted** badge with the date and the retailer, a **Not in catalog** badge for one no sweep has ever seen, a stat card, a filter tab, and a notice when any exist.
- A tire still listed by one retailer and dropped by another reads as listed, and says which one stopped.

### Notes
- The distinction that makes this trustworthy is the one it would have been easiest to skip: **a listing can go stale because the retailer dropped it, or because our own sweep never read that fitment.** Only a fitment the last sweep read completely can support a delisting claim; anything else is reported as not yet known. Announcing a delisting that is really our own coverage gap would send someone to renegotiate a link that was never dropped.
- Three days is the threshold. The sweep runs daily, so one missed day is ordinary — a slow run, a failed request — and is not a delisting.
- The first pass at the label named every retailer that had ever listed a tire, which credited one that dropped it a month ago as though it still carried it. Both that and the delisting date, which was being taken from the wrong retailer when two dropped weeks apart, are pinned by tests.

## [1.71.0] - 2026-08-24

### Added
- **The sweep now counts distinct products, not records returned.** Counting what came back is not the same as counting what is new, and the difference decides whether "complete" means anything. If paging does not advance — an offset ignored, pages overlapping — every page returns the same products, the received count still climbs to the reported total, and the fitment is declared complete having seen one page of it. The dedup key hides that perfectly, since a repeat simply overwrites itself. The coverage table now shows **Distinct** beside Read and flags it when the two diverge.
- **The connection probe takes an offset,** so the same keyword can be read at two depths and the pages compared. Whether paging advances at all was not otherwise observable: a sweep re-reading page one looks identical to one reading the whole match set.

### Notes
- What prompted this: a per-fitment, per-retailer breakdown of stored candidates showed **247** products in 275/60R20 and **2** in 255/55R21 — every fitment reported complete off 5,000+ matches, and both retailers stock both sizes heavily. Real catalogs are not shaped like that; a paging fault is.
- This supersedes the conclusion recorded in 1.70.1. "Every fitment read completely" was taken as evidence the missing tires are absent from the feed. That reading required paging to have worked, which is exactly what was never checked. The 532 products stored across the nine guide fitments may be one page of each ranking rather than the whole of it.

## [1.70.1] - 2026-08-24

### Fixed
- **A disabled direct-lookup pass no longer reports itself as a failure.** With the pass off, the status still rendered "ran 0 of 99 model search(es) — 0 uncovered tire(s) were actually found" in red, which reads as a run that tried and failed rather than one that never started. It now says the pass is off and why turning it on is unlikely to help.

### Notes
- The first run with the whole budget on the sweep read **every fitment completely** — 45,707 products across nine sizes in 53.8s of a 120s budget:

  | Size | Read | Matches |
  | --- | --- | --- |
  | 255/65R19 | 5,643 | 5,643 |
  | 255/60R20 | 6,102 | 6,102 |
  | 275/60R20 | 5,231 | 5,231 |
  | 275/65R20 | 5,091 | 5,091 |
  | 255/55R21 | 5,643 | 5,643 |
  | 275/55R21 | 5,075 | 5,075 |
  | 285/50R22 | 5,605 | 5,605 |
  | 275/50R22 | 5,323 | 5,323 |
  | 305/45R22 | 1,994 | 1,994 |

  Coverage stayed at 119 tires with 115 unmatched. That combination is the answer the coverage table was built to make available: every product CJ returns for every fitment the guide stocks has now been read, and those 115 tires are not among them. A retailer selling a tire on its own site is not the same as that tire being in its CJ product feed, and no further querying reaches a product the feed does not carry.

## [1.70.0] - 2026-08-24

### Changed
- **Direct lookups are off by default.** The run that settled it reported a single brand-and-model keyword matching **81,653** products. A thousand records is 1.2% of that ranking; covering one search would take 82 requests and the guide's models thousands. The pass spent the entire run budget, read 50 of 99 searches, and found nothing. The sweep's fitment keyword is better by an order of magnitude — a size reports around 5,000 matches, readable in a handful of requests — so the budget goes there. The pass stays available for a catalog that behaves differently.

### Added
- **Per-fitment sweep coverage**, as a table rather than a sentence inside an error. How much of a size's match set has been read is what decides whether "no retailer is carrying it" can be believed at all: a fitment read completely means the guide's tires in that size either arrived or genuinely are not in the feed, and a fitment read partially means neither conclusion is available. Each run resumes where the last stopped, so a size completes over successive runs.

### Fixed
- **"0 were in fitments the guide has no use for and were left out" was false.** 1.69.1 moved that filtering earlier, into the fetch, and the status kept reading the later counter — which the earlier filter had emptied. It reported nothing discarded while tens of thousands were. Both counters are now reported together.
- The truncation message advised setting a Google product category to narrow a sweep. That filter is a documented hazard here — Tire Rack sends no category, so applying one would drop the retailer entirely — and the advice now points at what actually helps: the rotation, and the whole-run budget.

### Notes
- The contract check pins the new default and fails if direct lookups run without being switched on. It caught the change the moment it was made, which is what it is for.

## [1.69.1] - 2026-08-24

### Fixed
- **Run Discovery Now died with "Network error during discovery."** Each pass honoured its own budget, which is not the same as the run having one: a 240-second sweep and a 120-second lookup pass, each able to start a final 30-second request, add up past what a web request survives — and price sync and the re-key still had to happen afterwards. That stayed invisible while a lookup read fifty records and both passes finished early; reading a thousand made both run to their limit. A run now shares one ceiling across every pass, stops on it, and reports what it did not reach. The rotation cursors mean a ceiling costs time-to-complete, never coverage.
- **The discovery request never raised PHP's execution limit,** though the link checker beside it does for the same reason. It now allows the run's budget plus the final request and the writes that follow.
- **A failed run now says what failed.** "Network error" covered a timed-out request, a PHP fatal and a permissions failure alike, so a run that outlived its request looked identical to one that crashed. The reply now distinguishes no-reply-at-all from a server error and names the status.

### Changed
- **Lookup answers are filtered to fitments the guide stocks before being carried any further.** A thousand records of which one or two are useful is a large array to build and hold for nothing, and this pass now reads twenty times what it used to. What was dropped is counted rather than passed over.
- A new **whole-run budget** setting, documented as the first thing to lower if a manual run returns nothing.

### Notes
- The contract check covers both: that a caller's ceiling is obeyed and the remaining terms reported pending, and that the pre-filter keeps only wanted fitments and counts what it dropped. It uses the real qualifier rather than a stub, since stubbing the parser that decides what reaches the queue would test the stub.

## [1.69.0] - 2026-08-24

### Fixed
- **Direct lookups were reading 50 records of a ranking thousands deep, and saying nothing about it.** The run that prompted this reported 99 searches, 0 tires found, and 4,924 products &mdash; an average of **49.7 per search**, which is every single answer truncated at the cap. The limit was set to 50 on the reasoning that a keyword naming a tire is a precise query whose answer is a handful of listings or none; the keyword probe disproved that reasoning and the constant was never rechecked against it. CJ scores a keyword and returns a ranking, so the tire being searched for can rank below where reading stops. The limit now matches the sweep's, and it is configurable.
- **A truncated answer is now reported.** Each search compares what came back against the match count CJ states, and the status names how many answers were cut off and how deep the deepest ranking ran. Without that comparison a truncated answer is indistinguishable from a complete one &mdash; which is exactly how this went a release unnoticed, and is the same silent ceiling the sweep carried at 100 records until 1.63.1.
- The "no tire was found, so the feed must not carry it" notice no longer appears when answers were truncated. That conclusion is only available once the whole answer has been read, and showing it earlier would argue for giving up on evidence that does not support it.

### Changed
- Search answers are consumed as they arrive rather than collected first. At a thousand records a search and a hundred searches, holding every response would mean a hundred thousand products in memory to keep the few dozen in a fitment the guide uses.

### Notes
- The contract check now runs against a stub that reports a match count far larger than the page it returns, so a reintroduced cap fails the build rather than reaching a release.

## [1.68.0] - 2026-08-24

### Changed
- **Direct lookups now search by brand and model, and filter the fitment here.** The keyword probe settled how CJ treats a term: asking for `Michelin Defender LTX M/S2 305/45R22` returned that exact model from Tire Rack in **285/45R22** and **275/45R22**. The words match — the model is in the feed, from the right retailer — but the size is scored rather than applied. Carrying it in the term only diluted the part that works, and the previous release then discarded both results for being the wrong fitment. The size comes out of the term; anything returned in a fitment the guide uses is kept.
- **A search covers every uncovered size of its model at once.** Terms are deduplicated by brand and model, so a model the guide stocks in four sizes is one request rather than four. The status reports both figures — searches run, and uncovered tires they cover.
- **The fitment guard accepts any size the guide uses,** not only the one that prompted the search. A model search returns that model in every size CJ holds, and some of those are other guide tires — dropping them would discard a match the next search was about to go looking for.

### Notes
- What the probe returned is now the fixture the contract check runs against, including the off-guide fitments, so the behaviour that drove this change is pinned rather than described.
- Still open, and visible in the same status line: whether the fitments the guide wants exist in the feed at all. A model that comes back only in sizes the guide does not stock is a retailer catalog gap, and no query change reaches a product the feed does not carry.

## [1.67.1] - 2026-08-24

### Fixed
- **1.67.0 shipped half-applied and broke the direct-lookup pass.** `RTG_Catalog_Sync` was rewritten to read `$lookup['by_term']` while `RTG_Catalog_Source_CJ` still returned `products` — so the targeted lookup ingested nothing at all. The keyword probe was affected the same way: `test_connection()` never took the keyword parameter, so the box on the settings screen was ignored and every probe silently searched the first guide size instead. Both halves are now applied, and the probe reports the keyword it actually used so a fallback can never again be mistaken for an answer.

### Added
- **Contract checks that actually execute** (`tests/contract/`, run in CI). The PHPUnit suite needs a WordPress test library and a database and so does not run on every change, and `php -l` only proves a file parses — a mismatch between what one class returns and what another reads is perfectly valid PHP. That is exactly what shipped. These run on plain PHP with a stubbed HTTP layer: they call the real `fetch_terms`, assert the shape the sync depends on, exercise the fitment guard against a response shaped like a live one, and confirm the probe honours its keyword. Verified against the broken 1.67.0 build, where they fail immediately.

## [1.67.0] - 2026-08-24

### Fixed
- **The direct-lookup success metric could not tell success from noise.** It counted a term as found when the request returned *any* product. The first live run scored **111 of 111** on that measure while covered tires stayed at 119 and not one guide tire matched — CJ ranks a multi-word keyword exactly as it ranks a bare size, so every term drew a few dozen loosely related products and the counter called each one a success. The status now reports how many lookups **came back with the tire that was asked for**, which is the only outcome that means the pass worked, and says so in red when that number is zero against answered lookups.
- **Direct lookups no longer fill the queue with other fitments.** Storing everything a ranked answer returned added **3,996 rows** in one run — near misses climbed from 15,349 to 18,057 — without covering a single additional tire. A targeted lookup asks about one fitment; products in other fitments are left out and counted, since canvassing fitments is the sweep's job. Nothing is silently dropped: the status reports how many were set aside.
- **A coverage label mixed two scopes.** "1 Michelin 305/45R22 listing(s) arrived from SimpleTire and The Tire Rack" paired a count of that *brand's* listings with the retailers selling that *size*. Both halves now describe the same set.

### Added
- **The Test Connection button takes a keyword.** Whether CJ matches a term or merely ranks against it was being inferred from a sync's aggregate counts; now a tire's full name can be typed in and the reply lists the titles that came back, so the question is answered directly. The reply also reports the match total alongside what it shows, so a ranked answer is recognizable on sight.

### Notes
- This release deliberately adds no new matching strategy. The last one shipped a mechanism and a metric that agreed with each other and not with reality; the instrument comes first this time, and what it reports decides what to build next.
- Rows already stored from the previous run are left alone. They are near misses rather than bad data, and pruning them is a destructive operation worth asking about rather than assuming.

## [1.66.0] - 2026-08-24

### Fixed
- **The coverage diagnostic claimed things it had not checked.** It reported a Michelin Defender LTX M/S2 as "listed, but as *Pilot Sport S 5*" — an all-season truck tire against a summer performance tire — because both are Michelins in 305/45R22. Sharing a brand and a fitment says nothing about being the same model, and 62 of the 115 uncovered tires were labelled that way on that basis alone. A listing is now only called this tire under another name when the names actually resemble each other; everything else says plainly that the brand and fitment arrived but this model did not, and shows what *did* come in as evidence.
- Model names are compared by squashed containment ("Ridge Grappler" inside "Ridge Grappler LT"), not edit distance. Edit distance was tried and rejected on real data: `NT420V` and `NT421Q` are two substitutions apart and are unrelated tires, so any threshold loose enough to catch real variants also merges model codes differing by a digit. Shared words order what is shown but never promote a listing — "Open Country R/T Trail" and "Open Country A/T III EV" share two words and are two different tires.
- Near matches now lead with the closest name rather than whichever row the database returned first.

### Added
- **Direct lookups for tires the sweep never finds.** Correcting the diagnostic exposed the real problem underneath it: across 16,044 stored products there was exactly **one** Michelin listing in 305/45R22, a fitment Tire Rack demonstrably sells several of. A sweep asks CJ for a bare size, and CJ answers with a relevance ranking thousands deep rather than a filter, so a guide tire can rank below where paging stops and simply never arrive. Each uncovered tire is now asked for by brand, model and size — one request, a precise question — after the sweep. Budgeted and rotated like the sweep, so a long uncovered list completes over successive runs, and the status reports how many were asked, how many returned a listing, and how many are left for next time.
- Settings for the direct-lookup pass: an on/off toggle and its own time budget, spent after the sweep's so a slow sweep shortens this pass rather than cancelling it.

### Notes
- The fitment sweep and the direct pass share one ingest path, so a product reaching the queue by either route is judged and stored identically.
- A run is only reported as failed when it read nothing at all, direct lookups included — a sweep that timed out while the lookups still brought tires in did work, and calling it an error would hide that.
- The classifier was run against the exact listings behind the reported screenshot before shipping: all five mislabelled cases now classify as "brand and fitment carried, this model not", and a genuine variant still surfaces as actionable.

## [1.65.0] - 2026-08-24

### Fixed
- **Retailer coverage was read from a column that goes stale.** A candidate's `matched_tire_id` is written during a sweep, and only for the rows that sweep happened to revisit — but the sweep is time-budgeted and rotates through sizes, so most rows go days between visits. That lost coverage two ways. A tire added or renamed in the guide today did not retro-match the candidate rows already stored for it; they kept an empty `matched_tire_id` until a sweep saw those products again. And `build_guide_index()` maps one key to one tire, so where two guide rows share a brand, model and size — the same tire in two load ratings — only the last one indexed was ever written to a candidate, and the other could never be covered at all. Coverage and pricing now compare match keys at read time, so both reflect the guide as it stands rather than as the last sweep left it.

### Added
- **Every uncovered tire now says why it isn't matched**, because "no retailer match" was one line covering situations that need completely different responses. Each tire is now classified: the fitment never reached the queue, the fitment is carried but not from that brand, the brand and fitment are both listed under a different model name, or the guide row itself can't be keyed on. Only the third is fixable by hand, and it was invisible among the rest — so when it applies, the model names the retailers actually use are listed alongside, and aligning the guide's model to one of them matches the tire on the next run.
- A one-line summary above the list breaking the gaps down by type, so the shape of the problem is legible before reading 131 rows.
- **Stored matches are re-keyed against the guide on every sync**, so a tire added or renamed today stops showing its candidate rows as "awaiting review" for something already stocked. A status a person set is left alone; only the machine ones follow the new match.

### Notes
- The coverage lookups select only the columns they read. Each candidate row also stores an untouched copy of the source product node, and pulling those for sixteen thousand rows to compare prices would have cost tens of megabytes to answer a question that needs none of it.
- The classifier is a pure function of a prepared index, so `tests/test-coverage.php` exercises it without storage — including a check that the count in "N listings reached the queue" counts listings rather than retailers, which it did not on the first pass.

## [1.64.2] - 2026-08-24

### Fixed
- **No price was ever refreshed from Tire Rack.** CJ names the advertiser "The Tire Rack"; a purchase link resolves to "Tire Rack"; the two were compared exactly, so every Tire Rack tire was judged as "that retailer isn't listing this" and skipped. The first run after prices went live reported **103 covered tires and 0 updated**, which is what tipped it off — a real catalog does not leave every price unchanged. Retailer names are now compared with spacing, punctuation, case and a leading "the" removed, so the two spellings meet in the middle. Distinct retailers still differ, since collapsing them would attach one's price to the other's link — the failure the whole rule exists to prevent.

### Notes
- The same comparison decides which retailer may set a price, so this was silent rather than noisy: every affected tire was reported as "not carrying", a legitimate-looking outcome that happened to be false for a whole retailer.
- Covered by `tests/test-price-sync.php` using the real affiliate link and CJ's own advertiser spelling, alongside a check that two different retailers are still told apart.

## [1.64.1] - 2026-08-24

### Changed
- **The Google product category filter is now documented as a hazard rather than recommended.** Checking it against real data settled the question the wrong way: SimpleTire tags its tires `Vehicles & Parts > … > Motor Vehicle Wheel Systems > Motor Vehicle Tires > Automotive Tires` (id 6093), while **Tire Rack sends no category at all**. CJ applies the filter server-side, so setting one excludes every product that declares no category — removing Tire Rack in its entirety, the very retailer whose missing listings prompted the investigation. It would also have looked like a success, because the match counts would have collapsed exactly as a working filter makes them. The setting remains for a catalog where every advertiser tags its products, but it defaults to blank, the admin carries an explicit warning, and the previous copy calling it "the single most useful setting here" is gone.

### Notes
- Discovery does not need the filter. At 1,000 records a page and an allowance of ten pages, a sweep reaches 10,000 per size against a worst observed volume of ~5,600 — so paging already covers a size completely. The filter only ever saved requests.
- A test pins both properties: that no category is applied unless one is explicitly configured, and that the page allowance still exceeds the match volume actually seen.

## [1.64.0] - 2026-08-24

### Added
- **Discovery pages through a size's full match set** rather than reading the first page and stopping. Introspecting CJ's schema settled what its documentation wouldn't: `shoppingProducts` accepts `offset` and `limit`, so the sweep now pages until the reported total is reached, a page comes back empty, or the page allowance is spent — and says which, per size.
- **A Google product category filter**, and it is the setting that matters most here. The first live sweep showed why: CJ reported **5,643 matches for `255/65R19`** and the same figure for `255/55R21`. A keyword search *ranks by relevance rather than filtering*, so a tire size returns thousands of products that are not that fitment and mostly are not tires. Left unfiltered, a full sweep means paging through most of a retailer's catalog to find a handful of matches. Configurable one category per line; blank applies no filter, and the settings screen explains how to find the working value with Test Connection.
- **The sweep now starts where the last one stopped.** A run that can't finish inside its time budget previously covered the same leading sizes every time and never reached the rest — with nine sizes and a budget that fits perhaps five, the last four would never be checked at all. Coverage now completes across successive runs, and the status names the sizes left for next time.
- **A page allowance per size** (default 10), so one size's unfiltered match set can't consume the entire budget. Whatever is left unread is reported with its counts.

### Notes
- The category value is left to configuration rather than hardcoded: CJ's taxonomy naming could not be verified from the development environment, and a wrong guess baked into the query would silently return nothing. Test Connection reports the match count, which is the fastest way to confirm a value works — the right one makes the count fall sharply while still returning tires.
- "No category configured" is sent as null, not an empty list: an empty list would ask for products belonging to no category at all.

## [1.63.1] - 2026-08-24

### Fixed
- **Discovery was silently discarding most of each retailer's catalog.** Products were requested 100 at a time per tire size with no pagination, and although the query asks CJ how many matched, the adapter never read the answer. A fitment carrying several hundred tires therefore came back capped, and everything past the first hundred vanished without a word — which is how a Michelin Defender LTX M/S2 that Tire Rack plainly lists in 275/60R20 showed up under "no retailer match". The reported match count is now compared against what arrived, and a shortfall is named per size in the discovery status ("Results capped — 275/60R20 (100 of 412)") instead of passing unnoticed. The per-size limit defaults to 1000, enough to cover a fitment in one request.
- **The sweep time budget was sized for five tire sizes**, not a real fitment list, so a guide covering a dozen was having most of them skipped each run. Raised from 45 to 240 seconds and made configurable, for hosts whose PHP execution limit needs it lower — or raised further when the status reports sizes went unchecked.

### Notes
- "Didn't report a count" and "reported none" are kept distinct, so a response that simply omits the figure isn't mistaken for a truncated one.
- Existing installs carry whatever "Records per size" was saved. **Raise it under Tire Discovery → Discovery Settings and re-run** — the default only applies where the setting was never stored.

## [1.63.0] - 2026-08-24

### Added
- **Guide prices refresh on each daily discovery run.** A price is taken only from the retailer the tire's own purchase link points to. Both retailers often carry the same tire at different prices, and a tire shows one price beside one buy button — taking the cheaper figure from the other retailer would put a number on the page that doesn't match what a reader sees on click, which is worse than a stale price. A tire linked somewhere discovery doesn't price (Amazon, a manufacturer) is left alone. Where the linked retailer has several listings, the cheapest wins, since the reader can reach it through the same link.
- **Affiliate redirects are resolved**, so the rule works on real links. A CJ deep link goes to a tracking host with the destination buried in a query parameter, sometimes encoded twice; the retailer is read from the hostname, then that parameter, then the raw string, and resolves to nothing rather than guessing when the link leads anywhere else.
- **A retailer coverage report** on the Tire Discovery page: how many guide tires a retailer carries, and the full list of those none does — expected while affiliate links are still going in, and expected permanently for anything discontinued or sold elsewhere. Each unmatched tire shows where its link currently points.
- **Every price left unchanged is reported with a reason** — no link, link not priced, retailer not listing it, already current, or change too large — so "why didn't this tire's price move?" is answerable without a re-run.
- **Tires record where their price came from and when** (`price_source`, `price_synced_at`, migration 20), so a synced figure is distinguishable from one typed in by hand and a tire that quietly stopped syncing is visible.

### Notes
- A price that moves by more than a configurable threshold (default 50%) is reported rather than written. Tires are matched on brand, model and size, which can collide across load ratings, and a swing that large is likelier to be that collision than a real sale.
- The refresh runs off the same fetch as discovery rather than on its own schedule, so it costs no extra API calls.

## [1.62.1] - 2026-08-24

### Fixed
- **Tire Rack listings no longer arrive with no load index.** Retailers write the spec cluster in two shapes, and the parser only understood one: SimpleTire's `275/60R20 115T` reads straight after the size, while Tire Rack's `255/65R19 XL 114V` puts the **load range in between**. Reading only for digits immediately after the size found the first and gave up on the second, so every Tire Rack row came through blank and had to be confirmed by hand. Measured against the 181 real titles from the first live sweep, **102 failed to parse before this change and 0 do now** — dual ratings behind a letter load range (`D 115/112S`, `E 126/123Q`) included, where the single-wheel figure is the one the guide compares. The load range is now read from that anchored position too, falling back to a whole-title search only when it finds nothing, since a bare "E" elsewhere in a title is far more likely to belong to a model name.
- **Legacy sizes that carry the speed rating inside them** — `255/45VR15`, `255/50ZR16` — now parse; only `ZR` was recognized before.
- **Percent-encoded titles are decoded**, so `Trail-Terrain T/A%2B` becomes `Trail-Terrain T/A+` in the model name instead of carrying the escape through. Decoded only when an escape is actually present, so a title containing a literal `%` isn't mangled.

### Added
- **Superseded listings are flagged.** Tire Rack suffixes a replaced part number with " OLD" and keeps both rows in the feed, so the same tire arrives twice at different prices. The row still qualifies — the tire is real and may be the one you want — but carries a warning, since adding the superseded listing would bake a dead part number into the guide.
- **Candidates keep the upstream record exactly as it arrived**, under `_source_node`. The `raw_json` column previously held the *mapped* product, so a field the mapper didn't keep was unrecoverable afterwards: `description` was fetched from CJ on every request and discarded, which is what turned "why is the load index blank?" into a live re-run instead of a database query. Sources may now return the untouched node; it is stored and never interpreted.

## [1.62.0] - 2026-08-24

### Added
- **Discovery judges fitment per vehicle.** Size and load index used to be two independent gates, which could not express the thing that actually matters: a 275/65R18 at load index 114 clears a global floor of 112 while being illegal on the R1 that is the only platform taking that size. Each vehicle is now asked the same pair of questions — is this one of your sizes, and does it carry enough load for you — and a tire qualifies if any vehicle says yes. Candidates record the platforms they are legal on, shown as a **Fits** column in the review queue and carried in the digest email.
- **A vehicle filter on the review queue**, so the queue can be narrowed to just R1-legal or just R2-legal tires. A tire legal on both appears under each rather than being partitioned into one, since it genuinely is a candidate for both.
- **Per-platform load index floors**, replacing the single global one, defaulting to the real requirements (R1 116, R2 112). Vehicles and their sizes come from the Stock Wheels table — the same source the consumer-facing vehicle toggle already uses — so a platform added there appears in discovery on its own with no extra configuration. A blank field restores the built-in figure.

### Changed
- A near miss that fails on load now names the platform and the figure it fell short of ("Load index 114 is too low — R1 needs 116") instead of citing an anonymous global minimum, and the warning on an unlisted load index names the floor to confirm against.

### Notes
- With no stock wheels configured there is no vehicle map to judge against, so the flat size list and single global floor still apply and the settings screen says so. A site that never set wheels up cannot have its catalog rejected by this change.
- Existing candidate rows keep a blank **Fits** until the next discovery run repopulates them; backfilling in the migration would mean re-qualifying every row against rules the next run applies anyway.

## [1.61.0] - 2026-08-24

### Added
- **A brand-coverage policy for discovery**, settable under Tire Discovery &rarr; Discovery Settings. Retailer catalogs carry far more brands than the guide covers — the first live run put 216 tires in the review queue, a large share of them budget marques that would never be listed — but whether that is noise or discovery is a judgement call rather than a rule, so it is a setting with three positions. **Surface them, flagged** (the default) keeps every tire reviewable and marks one whose brand isn't in your list, so a newcomer worth covering still reaches you. **File them under Near Misses** keeps the queue tight, at the cost of never seeing a new brand until you add it to the dropdown. **Don't judge brand at all** restores the previous behaviour.
- Brand comparison ignores everything but letters and digits, so "BFGoodrich", "BF Goodrich" and "BF-Goodrich" are recognized as one manufacturer. This matters most under the rejecting policy, where a missed variant means a tire is never seen.

### Notes
- With no brand list configured the rule stays silent whatever the policy is set to, and an unset policy never rejects — a rule nobody has configured should not be able to hide tires.
- The default is deliberately the non-hiding option. Existing installs keep seeing everything they saw before, now annotated.

## [1.60.1] - 2026-08-24

### Fixed
- **Tires already in the guide are no longer filed as near misses.** The first live CJ run reported "0 already in the guide" while plainly having matched — Tire Rack listings that matched a guide tire were landing under near misses because qualification was judged before matching, so a listing that merely omitted its load index buried a tire already stocked. Matching now settles the status first: what you already own is "already in the guide" whatever the rules make of the listing's wording.
- **A missing load index no longer disqualifies a candidate.** Tire Rack routinely omits it, and treating that as a failure hid genuinely new tires among the near misses where they were never seen — defeating the point of watching the catalog. Rules now come in two strengths: a *failure* disqualifies (wrong fitment, load index below the floor, unidentifiable), while a *warning* travels with a qualifying candidate as something to confirm before adding. Warnings render on the row in every tab.
- **Ordinary speed ratings are no longer reported as unrecognized.** Every row from the first live run was flagged for a perfectly normal "V", "W", "H" or "T". The check compared against the site's saved dropdown with a strict match, and that list — edited as free text — carries stray line endings, so nothing ever matched. The configured list is now unioned with a canonical set of every speed rating in industry use, trimmed on both sides, and an unfamiliar rating warns rather than rejects.

### Notes
- Rejections for **"Size … is not a Rivian fitment" are correct and expected.** CJ's `shoppingProducts` keyword search matches loosely, so a query for one size returns neighbouring ones (285/45R22, 305/45R22) that the size rule then filters out. That filtering is the feature working, not a fault — though it does mean much of each request's record budget is spent on results that will be discarded.

## [1.60.0] - 2026-08-24

### Added
- **Tire Discovery now pulls from CJ Affiliate.** Both Tire Rack and SimpleTire run their affiliate programs on CJ, so one connection covers both retailers. Discovery sends one `shoppingProducts` request per tire size — five requests for a full sweep — scoped to the configured advertisers, and feeds the results through the same qualification, matching and review queue added in 1.59.0. With CJ configured, the JSON feed source stays out of the way unless a feed URL is set, so the bundled sample can't seed demo rows into a queue holding real finds.
- **Credentials are configured, never committed.** The personal access token is read from an `RTG_CJ_PAT` constant in `wp-config.php` when one is defined, which keeps it out of the database entirely; otherwise it falls back to a settings field that is write-only — the saved value is never rendered back into the form, an empty submission leaves it untouched (so an unrelated settings save can't wipe it), and clearing it is an explicit checkbox. The company ID is entered by the admin. Advertiser IDs default to Tire Rack and SimpleTire, both public directory identifiers, and are editable as `advertiserId|Name` lines.
- **A Test Connection button** that runs one real query against a real guide size, so a pass proves the whole path — authentication, query document, advertiser scope and field mapping — rather than mere reachability. On failure it surfaces CJ's own error text; on an empty-but-successful response it shows the raw body.

### Internal
- `RTG_Catalog_Source_CJ` implements the existing `RTG_Catalog_Source` interface, so nothing downstream of the source changed. Two things about it are deliberately soft, because CJ's schema reference sits behind a JavaScript-rendered portal and could not be verified against the live API from the development environment: the GraphQL document is overridable from settings, and the response mapping accepts several plausible field names per value (`clickUrl`/`buyUrl`/`link`, `price.amount`/`currentPrice`, `id`/`productId`/`sku`, and so on), unwrapping Relay-style `node` wrappers and locating the result list wherever it sits in the payload. **The shipped query document is a best effort and needs one live verification run**; a mismatch is a settings edit rather than a plugin release, and the Test Connection output names the field to change.
- GraphQL errors arrive inside HTTP 200 responses, so they are detected explicitly and passed through verbatim — CJ names the offending field, which is exactly what correcting the query needs. Authentication failures (401/403) are reported distinctly from other HTTP errors.
- A sweep carries a 45-second wall-clock budget. Five sequential requests can outlast PHP's execution limit on a web-triggered cron, so the sweep stops when the budget is spent and names the sizes it didn't reach, rather than being killed mid-run and silently reporting a partial catalog as complete. A size whose individual request fails is likewise recorded and skipped instead of aborting the sweep.
- Response mapping is covered by `tests/test-catalog-source-cj.php` — the riskiest part of the adapter given the unverified schema, so the shapes a product search plausibly returns are pinned to stop a correction to one spelling from breaking another.

## [1.59.0] - 2026-08-24

### Added
- **Tire Discovery — automatic monitoring of affiliate catalogs for new tires.** Finding out that Tire Rack or SimpleTire had started carrying a new tire in a Rivian fitment meant remembering to go and search for it, one size at a time; in practice that happened rarely and things were missed. A daily check now does it: every product it sees is judged against the guide's requirements, matched against the tires already listed, and anything both eligible and genuinely new is queued for review and emailed as a digest. A run that finds nothing new sends nothing.
- **A review queue at Tire Guide → Tire Discovery**, badged in the menu with the number awaiting a decision. Candidates are split across five views — Awaiting Review, Near Misses, Already in Guide, Dismissed and Added — and filterable by size. **Add to Guide** opens the normal Add New Tire form with brand, model, size, price, load index, load range, speed rating and purchase link already filled in, plus the diameter and max load the guide derives from them; category, warranty, weight and tread are deliberately left blank rather than guessed at. Saving the tire closes out the candidate.
- **Near misses are visible rather than silently filtered.** A tire that clears every rule but one — the right size at load index 109, say — is listed with the reason it was held back, because that is exactly the row worth a second opinion. Every failed rule is reported at once, so fixing one doesn't reveal another on the next run.
- **Dismissals are permanent.** A candidate you say no to never returns to the queue however many times the sync sees it again, which is what keeps the queue short enough to stay read. Statuses a person set (dismissed, added) always outrank whatever a later run concludes; machine-assigned ones are recomputed, so a tire rejected under a stricter load index floor surfaces on its own once the floor is lowered.
- **Settings for the discovery rules** on the same page: enable/disable the daily check, toggle the digest email, and set the minimum load index (defaulting to 112, the R2 floor, so an R2-legal tire still surfaces for review — R1 needs 116). The load index minimum is clamped to the range the load-index table actually covers, so a typo can't disqualify every tire at once.

### Internal
- Qualification lives in `RTG_Tire_Qualifier`, as pure functions of their inputs with the thresholds passed in rather than read from the database. Retailers describe a tire as marketing copy — "Michelin Defender LTX M/S2 275/65R18 116T" is a typical title and there may be no size field at all — so the class first pulls specs out of the text, then judges the result. The parser handles the notations both retailers actually publish: `LT`/`P` prefixes, `ZR` construction, a space before the `R`, and dual load indices with a spelled-out load range. Load index and speed rating are read only from the text *following* the size, because searching the whole title reads the "18" out of "275/65R18" as a load index.
- Sources sit behind an `RTG_Catalog_Source` interface and are registered through the `rtg_catalog_sources` filter, so adding a retailer means writing a `fetch()` and nothing downstream changes. A JSON-backed source ships with it, which makes the pipeline testable without affiliate credentials and doubles as a usable fallback for any retailer with no machine-readable feed.
- Tires are recognized across retailers by a match key that squashes the punctuation retailers are inconsistent about, so "Defender LTX M/S 2" and "Defender LTX M/S2" resolve to one tire. A key that misses costs one queue row a human dismisses; that's the right way round, since a false "already have it" would silently hide a genuine find, which is the failure this feature exists to fix.
- New `rtg_tire_candidates` table (migration 18) keyed on source + advertiser + product ID, holding what was seen, what was decided, and when it was first and last seen. Covered by `tests/test-tire-qualifier.php` and `tests/test-catalog-sync.php`.

## [1.58.6] - 2026-08-21

### Fixed
- **Roamer data for a tire linked to several IDs is no longer clobbered on every sync.** Roamer sometimes publishes the same physical tire under more than one `tire_id`, and a guide tire can carry a comma-separated list of them (from multi-assign). The sync wrote once per *feed entry*, so each linked ID overwrote the previous one and the tire ended up with whichever ID the feed happened to list last instead of the merged figures — a multi-assign's weighted average survived only until the next sync (every 5 minutes since 1.58.5). Feed entries are now grouped by target tire and written once, merged. A tire linked to two IDs reporting 2.0 mi/kWh over 1,000 km and 3.0 over 3,000 km now correctly reads 2.75 mi/kWh over 4,000 km, with vehicle counts summed and vehicle breakdowns combined per vehicle.
- **Auto-matching a second Roamer ID no longer drops the first.** When a feed entry auto-matched a tire that was already linked, the sync replaced `roamer_tire_id` outright; newly matched IDs are now appended to the existing list.

### Changed
- **A duplicate Roamer entry can be merged into an already-matched tire.** The strict 1:1 rule from 1.58.3 stopped the silent overwrite it was aimed at, but left no way to handle Roamer listing one tire twice — the duplicate could only be hidden, discarding its mileage. Assignment is now additive: a Roamer ID still belongs to exactly one guide tire, but a guide tire may carry several, and assigning to a linked tire merges the selection into its existing IDs. Both the "Assign selected to..." dropdown and the ambiguous-match candidate lists show already-linked tires again, labelled `— already linked (N)`; only an ID held by a *different* tire is rejected, naming that tire. To move an ID between tires, unlink it at its current owner first.

### Internal
- Merge math (distance-weighted efficiency, summed counts, combined vehicle breakdown) lives in one place, `RTG_Roamer_Sync::merge_entries()`, shared by the sync and manual assignment so the two can't drift. It also falls back to a plain mean when no entry reports distance, where weighting alone would have zeroed the efficiency. Covered by `tests/test-roamer-sync.php`.

## [1.58.5] - 2026-08-11

### Changed
- **Roamer efficiency sync now runs every 5 minutes** instead of twice daily. A `rtg_five_minutes` recurrence is registered alongside the existing `weekly` one, and `RTG_Roamer_Sync::schedule()` now repairs an already-scheduled event that is on a different recurrence, so existing installs move off `twicedaily` on upgrade rather than keeping the old event forever. Failure emails are unaffected — the mailer still throttles to one per reason per 12 hours. Note that on sites without a real system cron, WP-Cron only fires on page loads, so the effective interval is bounded by site traffic.

## [1.58.4] - 2026-07-04

### Added
- **Info (i) tooltips on individual tire pages**, matching the main guide: the Real-World Efficiency hero stat (including the per-tire tracked-miles context) and the Load Index, 3PMS Rated, and UTQG spec rows now carry the same info icons and explainer modals (dark modal, gold title, Escape/backdrop/Got-it close, focus return). Implemented self-contained in `tire-page.js` since the page can't load the guide bundle; the copy is duplicated from `tooltips.js` with a keep-in-sync note.

## [1.58.3] - 2026-07-04

### Fixed
- **Roamer assignments now enforce a strict 1:1 mapping.** A guide tire that already carries Roamer data can no longer be assigned again from either the Unmatched or Ambiguous sections — previously a second assignment would silently overwrite the existing link. Enforced in three layers: the "Assign selected to..." dropdown lists only unlinked guide tires, ambiguous-match candidates that are already linked are skipped (with a note when a row has no assignable candidates left), and the assign AJAX handler rejects already-linked targets server-side (re-assigning the same Roamer ID stays allowed as a no-op). To intentionally remap a tire, unlink it first.

## [1.58.2] - 2026-07-04

### Changed
- **Roamer Sync: the "Assign selected to..." dropdown now surfaces name matches first.** When unmatched Roamer tires are selected, guide tires whose names match (squashed-substring or ≥60% token overlap, so "M/S 2" matches "M/S2") float into a "Name matches" group at the top of the dropdown; everything else stays available under "All tires". With no matches the plain full list is shown, and the current pick is preserved across regroups.

## [1.58.1] - 2026-07-04

### Fixed
- **Admin tire-list filter bar cleaned up.** The search field and the three dropdowns rendered at mismatched widths (a base 400px input cap) and wrapped into a ragged two-row layout. The bar is now one tidy responsive row: search takes twice a dropdown's share, all controls share the same height (the Filter button included), and inline styles moved into the stylesheet.
- The bulk-edit screen (1.58.0) used the wrong wrapper class (`rtg-admin` instead of `rtg-wrap`), so its inputs and cards missed the plugin's admin styling.

## [1.58.0] - 2026-07-04

### Added
- **Slug visibility in admin.** The tire list gains a sortable Slug column (linked to the public page) and the row's View action now opens `/tires/{slug}/`; the edit form shows the slug with the full public URL and lets you edit it — manual edits are uniqueness-checked and create a 301 redirect from the old slug (the field regenerates automatically when brand/model/size change, and an untouched field never undoes that).
- **Bulk edit.** Select tires in the list, choose the new "Edit" bulk action, and set Category, Average Price, Mileage Warranty, and/or Tags (append or replace, deduplicated) across the whole selection — blank fields keep each tire's current value.

### Changed
- **Efficiency score de-emphasized in admin**, matching its removal from the frontend (1.51.0): the tire list's Grade column (replaced by Slug) and "Recalculate Grades" button are gone, the edit form's live-preview card and its AJAX calculator JS are removed, and the dashboard's grade-distribution chart is dropped. The score still auto-calculates on save and remains stored.
- **Dashboard's "Avg Efficiency Score" stat replaced with "Content Gaps"** — the count of tires missing images or links (highlighted red when non-zero), an actionable metric instead of one users never see.

## [1.57.1] - 2026-07-04

### Added
- **Official review link on individual tire pages.** Tires with a review link now show a secondary CTA in the hero — "Watch Official Review" (play icon) for YouTube/TikTok links or "Read Official Review" (article icon) otherwise — matching the guide cards. Clicks are tracked as `review`-type events, same as the guide.

## [1.57.0] - 2026-07-03

### Added
- **Affiliate click tracking on individual tire pages.** The tire page's "View Tire" CTA now reports to the same `rtg_track_click` analytics endpoint the guide cards use (via `sendBeacon`, so the outbound navigation is never blocked) — clicks from the SEO pages finally show up in the analytics dashboard. New `tire-page.js` bundle, enqueued only on tire pages.
- **Slug rename protection.** When a tire's brand/model/size edit changes its slug, the old slug is recorded (capped option map) and old URLs 301 to the new canonical — previously indexed or shared tire links can no longer 404 after a rename.
- **VideoObject structured data** on tire pages whose review link is a YouTube video (name, description, YouTube thumbnail, content/embed URLs). `uploadDate` is approximated from the tire's `created_at` since the video's true publish date isn't stored.

### Changed
- **`?tire=` deep links now canonical to the tire page.** The guide page's single-tire deep links point their canonical (AIOSEO/Yoast/Rank Math filters) and OG URL at `/tires/{slug}/`, so the query-arg URLs consolidate to the dedicated pages instead of competing with them.
- **Blocksy's hidden hero `<h1 class="page-title">` suppressed** on the in-theme plugin pages via the `blocksy:hero:custom-source` filter — removes the duplicate h1 from the SEO pages' DOM (no-op on other themes).
- **Price slider ceiling now adapts to the catalog.** The hardcoded $600 max silently hid any pricier tire from the default view; the slider max now rises to the most expensive tire (rounded up to $50) in both client and server modes, and Clear All / filter chips / no-results suggestions use the dynamic ceiling.

### Accessibility
- **Review modal**: focus is trapped inside the dialog (Tab wraps), Escape and button/backdrop closes all remove the key listener (it previously leaked), and focus returns to the opening element on close.
- **Focus-visible outlines** added for all tire-card interactive elements (CTAs, links, share/favorite buttons, info-tooltip triggers, and the compare checkbox via its overlay icon).
- **`aria-busy`** is set on the cards container during server-side fetches.
- **`prefers-reduced-motion`** coverage extended to remaining animated surfaces, including the tooltip and review modals (whose inline animation styles are now overridden) and toasts.

## [1.56.2] - 2026-07-03

### Fixed
- **Quieter empty-stat placeholder.** The 1.56.1 placeholder (large em-dash + separate "no data yet" line) drew more attention than real data. The info trigger now sits on the label row ("EFFICIENCY ⓘ") and the value is a single muted italic "No data yet" line — the placeholder recedes next to populated stats, and both blocks keep equal height. Applied to the missing-price case too.

## [1.56.1] - 2026-07-03

### Fixed
- **Cards without efficiency data no longer show one oversized price block.** The key-stats row now always renders both blocks — a missing Real-World Efficiency (or price) shows a muted em-dash with "no data yet" (the efficiency placeholder keeps its info tooltip), so the two-up grid stays consistent across every card and rows align.
- **Missing warranty/weight rendered as "0 miles" / "0 lb".** Row values are strings, so "0" passed the truthiness check meant to render a dash; the parsed number is compared instead.

## [1.56.0] - 2026-07-03

### Changed
- **The review count on guide cards now links to the tire page's Owner Reviews section** (`/tires/{slug}/#rtg-tp-reviews`) instead of opening the in-guide reviews slide-out. With the tire pages carrying full, server-rendered reviews, the drawer was a redundant second reviews UI.

### Removed
- **The reviews slide-out drawer.** `openReviewsDrawer` and its list/pagination renderers, the delegated open handler, and all drawer-specific CSS are gone (~350 lines). Kept: the review modal (star-click writing flow), the guest "Review Pending" badge, and the shared review-card styles used by the user-reviews page.

## [1.55.4] - 2026-07-03

### Fixed
- **The inline review count still rendered as a pill.** The 1.55.3 inline text styling was overridden by a leftover `.view-reviews-btn` pill block (tinted background, border, radius) that sat later in the stylesheet. That block is removed — the count now renders as plain muted underlined text next to the rating, exactly like the tire page's "· 1 rating". Still clickable (opens the reviews drawer); the focus-visible outline is kept for keyboard users.

## [1.55.3] - 2026-07-03

### Changed
- **Review count moved inline next to the star rating.** The "N reviews" pill below the stars is now plain text in the rating row — "4.0 · 3 reviews", matching the tire page's "5.0 · 1 rating" pattern. It's still clickable (underlined, opens the reviews drawer as before). The actions row below the stars now only appears for the guest "Review Pending" badge, saving another line of card height.

## [1.55.2] - 2026-07-03

### Added
- **Category + tag chips on guide cards.** Cards now show accent-tinted chips (matching the tire page's chip treatment) for the tire's category and tags (e.g. All-Terrain, EV Rated) under the spec rows — restoring category visibility that left the card with the 1.55.0 spec slimming, and replacing the old bottom "Tags" spec row. OEM stays as the corner badge.

### Changed
- **One primary CTA per card.** The full-width purple "Watch/Read Official Review" button is demoted to a small text link next to "Full Specs & Reviews" — it competed with the affiliate CTA and made cards uneven heights. Review-link click analytics keep working (the tracker now also matches the new link class).
- **"Write a Review"/"Edit Review" pill removed from cards.** Review writing lives on the tire page and the standalone review page; the card's interactive stars still handle quick ratings, the review-count pill still opens the drawer, and guests still see the "Review Pending" badge. Dead CSS for the removed pill and button was cleaned up.

## [1.55.1] - 2026-07-03

### Added
- **"Full Specs & Reviews →" link on guide cards.** Each card now ends with a gold text link to the tire's own page — a clear path to the full spec sheet and owner reviews now that cards carry only the decision drivers, and stronger internal linking into the SEO tire pages. Rendered only when the tire has a slug/page.

## [1.55.0] - 2026-07-03

### Changed
- **Guide cards slimmed to the decision drivers, with price and efficiency elevated.** Cards previously carried up to 9 spec rows; now that every tire has a dedicated page carrying the full sheet, cards show only Size, Mileage Warranty, Weight, and 3PMS (Category, Load Index, Speed Rating, and UTQG moved off the card). Average Price and Real-World Efficiency — the top decision factors, and efficiency is also the default sort — are promoted from a mid-list row and a small pill into a prominent two-up **key-stats row** under the rating (matching the tire page's hero stats; the efficiency stat keeps its info tooltip with tracked-miles context). Cards are roughly a third shorter, making the grid much easier to scan and compare. The now-unused Roamer pill styles (`.tire-card-eff*`) were removed.

## [1.54.3] - 2026-07-03

### Changed
- **The tire-card share button now copies/shares the canonical tire page URL** (`/tires/{slug}/`) instead of the legacy `?tire=` deep link on the guide page — shared links land on the dedicated, indexable tire page (and accrue link equity to it). Falls back to the `?tire=` deep link when a slug isn't available. Existing `?tire=` links keep working unchanged.
- **Tire page hero image shows the full tire.** The image was `object-fit: cover` (cropped to fill the square); it's now `contain` on a white surface with 20px inner padding, so the whole product shot is visible.

## [1.54.2] - 2026-07-03

### Fixed
- **Page width/gutters now governed natively by the theme (Blocksy) instead of plugin overrides.** Investigation of the live DOM showed the in-theme pages render as direct children of Blocksy's *constrained* `entry-content`, which already sizes content natively — `width: var(--theme-container-width)` (responsive edge gutters included), centered, capped at the theme's block max-width. The 1.53.3 `width: 100%` override stomped that rule (which is what made mobile go flush), and 1.54.1's 16px padding patched the symptom while double-insetting content against the theme's own gutters. Both overrides are removed: tire, review, and compare pages now size exactly like every other piece of content on the site, at every viewport.

## [1.54.1] - 2026-07-03

### Fixed
- **Content sat flush against the screen edge on mobile.** The 1.53.3 full-width change relied on the theme for horizontal gutters, but the theme's bare page template only centers a max-width column — below that width (phones/tablets) it provides no side padding at all. The tire, review, and compare pages now carry their own 16px side gutter under 1024px (and in their small-screen overrides), matching how the main guide page reads on mobile.

## [1.54.0] - 2026-07-03

### Changed
- **Individual tire pages redesigned as a proper product-detail layout.** The previous page had a sparse hero (title + stars + a full-width efficiency banner + one button surrounded by dead space), a flat spec grid that left an awkward empty filler cell, and a bare two-line reviews section. The new layout, built on the ink & brass design system:
  - **Hero**: gold brand eyebrow, 32px title, rating row that links to the reviews section (or invites the first review), category/3PMS/OEM chips, a key-stats row (Real-World Efficiency in Roamer blue with tracked-miles context, Average Price, Mileage Warranty, Weight — empties skipped), and a dual CTA row (gold "View Tire" + secondary "Write a Review").
  - **Specifications**: two grouped cards — "Size & Fitment" and "Construction & Performance" — with icon headers and label/value rows (no more orphan grid cells; empty rows skipped).
  - **Owner Reviews**: header shows the rating count with a Write-a-Review button, and the no-reviews case gets a real empty state (icon, heading, invitation copy, gold CTA) instead of a text line.
  - Mobile: hero stacks, stats go 2-up, CTAs go full-width; `prefers-reduced-motion` disables transitions.

## [1.53.3] - 2026-07-03

### Changed
- **Tire, Review, and Compare pages now span the full theme content width, like the main guide.** Each page had its own centered max-width cap left over from the standalone era (tire 900px, review 640px, compare 1200px); those are removed so all pages fill the theme's content area. Their own horizontal padding was also dropped (desktop and mobile) since the theme's content gutters provide it — matching how the guide shortcode behaves.

## [1.53.2] - 2026-07-03

### Changed
- **Style-consistency pass across all pages, aligned to the ink & brass palette.** A cross-page audit against the main guide found and fixed:
  - Main guide `:root` tokens had drifted from the documented palette: `--rtg-text-muted`/`--rtg-text-heading`/`--rtg-text-light` were all `#ece9e4` (so "muted" text rendered at body brightness) and `--rtg-accent-hover` equaled the accent (no hover lightening). Now `#a19e97` / `#f6f4f0` / `#f6f4f0` / `#ffbe4a`, matching BRANDING.md and the other pages.
  - Compare + Review topbars used an off-palette background (`#1e2126` → `#16191e`), still rendered a standalone-era RivianTrackr logo (redundant now that the theme header sits directly above — removed), and were `position: sticky` (which fights the theme's own header in-theme — now static).
  - Compare + Review hardcoded an `'Inter'`-first font stack; they now inherit the theme font, matching the main guide's behavior.
  - The tire page partial regained the admin theme-color override support that was dropped in the 1.53.0 in-theme conversion (Compare/Review had kept theirs).
  - **Compare + Review layout unified with the tire page pattern.** Both pages dropped their standalone-era full-bleed `bg-deep` panel (they now sit directly on the theme page background, like the tire pages) and replaced the "Back to Tire Guide" topbar with the same Home › Tire Guide › … breadcrumb the tire pages use (Compare keeps its Share/Print buttons on the breadcrumb row). Page titles aligned to the tire page scale (28px, 23px mobile), and the review page's redundant "Powered by RivianTrackr" footer was removed (the theme footer is right below it now).

## [1.53.1] - 2026-07-03

### Fixed
- **Duplicate page title on in-theme pages.** Themes print their own page-title `<h1>` above `the_content`, and the tire/review/compare content partials each render their own heading — so the title appeared twice (e.g. "Toyo Open Country A/T III (305/45R22) — Rivian Tire Guide" from the theme, then the tire `<h1>` again). `RTG_Theme_Render` now blanks the virtual post's display title via the `the_title` filter (scoped to the virtual post's ID `0`, so real posts, nav menus, and widgets are untouched). The browser-tab `<title>`, AIOSEO tags, and OG title are unaffected.

## [1.53.0] - 2026-07-02

### Changed
- **Tire, "Write a Review", and "Compare" pages now render inside the active theme** (header/nav/footer) instead of as standalone documents, while keeping their existing URLs. A new theme-agnostic `RTG_Theme_Render` helper presents each request as a singular page (works for classic and block themes) and injects the server-rendered content into `the_content` (with `wpautop` disabled so inline markup/styles survive). Each page's CSS was re-scoped under a container (`.rtg-tp` / `.cmp-root` / `.rv-root`) so it no longer resets or overrides theme styles. The Review and Compare pages are marked `noindex` (utility views).

### Added
- **All in One SEO integration for tire pages.** Per-tire `<title>`, meta description, and canonical are delegated to AIOSEO via its filters (`aioseo_title` / `aioseo_description` / `aioseo_canonical_url`), with core `document_title` / canonical fallbacks when no SEO plugin is active. `Product` + `BreadcrumbList` JSON-LD is emitted on `wp_head`, and every tire URL is registered into the AIOSEO XML sitemap via `aioseo_sitemap_additional_pages` — closing the sitemap gap from the initial launch.
- **`[rivian_tire_review]` and `[rivian_tire_compare]` shortcodes** so the review and compare UIs can also be dropped onto any theme page, in addition to their dedicated URLs.

> **Staging note:** this release changes how these pages render and how the tire pages' SEO tags are produced. Verify on staging before deploying — check that each page shows the theme chrome; that AIOSEO outputs the correct per-tire title/description/canonical for tire pages (view source + AIOSEO's tools); that `/tires/{slug}/` appears in the sitemap; that unknown tire slugs still 404; that the review/compare apps still work in-theme (Font Awesome icons present, layout intact); and that the shortcodes render correctly on a normal page.

## [1.52.1] - 2026-07-02

### Fixed
- **Affiliate/review/image links with query strings were silently broken.** The client-side row sanitizer (`validateAndSanitizeCSVRow`) stripped `< > " ' &` from every string cell, which deleted the `&` separators inside URLs — e.g. `?tireMake=…&tireModel=…&partnum=…` collapsed into a single param, so retailers like TireRack couldn't resolve the product and redirected clicks to their homepage. The affiliate link (index 18), image (19), and review link (22) columns — plus the slug (28) — are now excluded from that strip, matching the existing exception for the JSON `vehicle_breakdown` column (27). These fields are still validated by their own strict allowlist helpers (`safeLinkURL` / `safeImageURL` / `safeReviewLinkURL`) before use, so nothing unsafe slips through. Stored data was never affected — the `&` were always correct in the database; only the rendered link was corrupted.

## [1.52.0] - 2026-07-02

### Added
- **Individual, crawlable tire pages (2.0 Pillar 2, Phase 2A).** Every tire now has a server-rendered, indexable URL at `/tires/{slug}/` — tire content (specs, price, Roamer efficiency, owner reviews) is in the initial HTML, not injected by JS. Adds a `slug` column (migration 17, auto-generated from brand+model+size and backfilled), a new `RTG_Tire_Page` route mirroring the standalone review-page pattern, per-tire `<title>`/meta/canonical/OG tags, and dedicated `Product` + `BreadcrumbList` JSON-LD (the `RTG_Schema` product builder was factored out for reuse instead of only emitting a catalog-wide `ItemList`). Tire-card titles now link to these pages; legacy `/tires/{tire_id}/` URLs 301 to the canonical slug. Remaining SEO work (sitemap entries, `?tire=` canonical consolidation, admin slug setting, on-page click tracking) is tracked in ROADMAP-2.0.md.

## [1.51.0] - 2026-07-02

### Changed
- **Discontinued the proprietary efficiency score in the frontend.** The calculated efficiency badge (A–F grade + 0–100 value) no longer appears on tire cards or the compare page, the "Efficiency Grade" sort option is removed, and its info tooltip is gone. The default sort across every mode (client, server-side pagination, and the public REST `/tires` endpoint) is now "Real-World Efficiency" (Roamer mi/kWh) so results are no longer ordered by the discontinued score; `efficiency_score` remains an accepted REST sort value for backward compatibility. This is a frontend-only change and fully reversible — the `efficiency_score` / `efficiency_grade` DB columns, the calculation engine, `recalculate_all_efficiency`, the admin tire-edit preview and "Recalculate Grades" action, CSV import/export, and the efficiency-calc endpoints are all untouched, and the stored values still flow through the tire payload. Roamer real-world efficiency (mi/kWh) is unaffected.

## [1.50.2] - 2026-06-17

### Removed
- **Reverted the per-tire rolling-resistance feature (added in 1.50.0, sort option in 1.50.1).** The estimate was derived from Roamer real-world efficiency, which conflates rolling resistance with aerodynamic drag, vehicle mass, HVAC/accessory load, terrain, temperature, and driver behavior — too many variables to represent the actual rolling resistance of the tread. Rather than present an energy-derived estimate as a measured tire property, the feature is removed: the "Rolling Resistance" card/compare rows, the "Rolling Resistance: Low → High" sort, the REST `roamer_crr` field, and the `RTG_Database` calculation are all gone. Real rolling resistance is a lab-measured value (ISO 28580 / EU tyre label); a sourcing assessment showed coverage is too partial for this catalog (US-market brands and large Rivian sizes are largely absent from EU label / lab-test data) to build on now. The `roamer_crr` DB column is dropped via migration 16. Roamer real-world efficiency (mi/kWh) is unaffected.

## [1.49.3] - 2026-06-12

### Fixed
- **"View Tire" (and other anchor buttons) turned unreadable on hover.** The compare and tire-review templates load the theme stylesheet via `wp_head()`, and the theme's global `a:hover { color: #ffbe4a }` outranks a button's non-hover text color — so hovering a gold button turned its text light-gold on a light-gold background. Hover rules for `.cmp-cta-primary`, `.cmp-cta-review`, `.cmp-btn-primary`, and `.rv-btn-primary` now pin their text color (and `text-decoration: none`) explicitly.

## [1.49.2] - 2026-06-12

### Fixed
- **Compare and tire-review pages now use the gold brand accent.** Both standalone templates overrode `--rtg-accent` to green (`#5ec095` / hover `#4ade80`), so best-value highlights, buttons, links, and focus states rendered green instead of amber. Accent is now `#fba919` / `#ffbe4a` like the rest of the site. Button text on gold was already dark (`#15130e`), so contrast is unaffected.
- Kept semantically-green elements green now that the accent is gold: the review success toast pins to `#34c759` (system success) and the compare "Yes" feature checkmarks pin to `#4ade80` (matching the green-check / amber-best convention from the spec tables). The 3PMS/EV/studded category tags are unchanged.
- Template text tokens aligned to spec: `--rtg-text-primary` `#e5e5e5 → #ece9e4` (warm), `--rtg-text-heading` `#ffffff → #f6f4f0`.

## [1.49.1] - 2026-06-12

### Fixed
- **Compare & review follow-ups for the ink & brass theme.** The compare and tire-review standalone templates had drifted token fallbacks (`--rtg-bg-deep: #111827`, `--rtg-border: #334155`) plus a navy section-header hover (`#1a2537`) that the 1.49.0 conversion missed — now `#121418` / `#3a3e45` / `#1f2228`. Also converted the focused-search background, the toggle off-track, and the guest-notice background fallback in the main stylesheet.
- **Compare grade colors now match the documented A–F scale**: grade A `#5ec095 → #34c759`, grade B `#a3e635 → #7dc734` (C/D/F already matched); the no-grade fallback gray is warm-neutral (`#a19e97`).
- Review toast "info" accent aligned to the system info color (`#3b82f6 → #60a5fa`).

## [1.49.0] - 2026-06-12

### Changed
- **Restyled the frontend to the new "ink & brass" theme**, matching the sitewide refresh on riviantrackr.com. The navy/slate dark palette is replaced with near-neutral charcoal surfaces (`#121418` deep bg, `#16191e` cards, `#3a3e45` borders/inputs) and warm-neutral text (`#ece9e4` body, `#a6a39c` secondary, `#79766f` muted) — cool surfaces, warm text, gold (`#fba919`) as the only saturated accent. Slate grays in the compare/review templates (`#1e293b`, `#1e3044`, `#8493a5`, `#94a3b8`, `#f1f5f9`, `#e2e8f0`) map to warm-neutral equivalents; star-empty becomes `#2c2f34`; text-on-accent becomes `#15130e`. Gold, purple CTA, success/error, star-user green, and the light admin theme are unchanged.
- **Updated the admin theme-color defaults** (settings page + reset values in `admin-scripts.js`) to the new palette. Note: installs with previously saved custom theme colors keep their saved values — use "Reset to defaults" on the settings page to adopt the new palette.
- **Typography alignment with the site type system:** page titles on the compare and tire-review templates (24–26px/700) gain −0.5px tracking; standalone template body line-height 1.5 → 1.6; added Firefox font-smoothing parity (`-moz-osx-font-smoothing`).
- Updated `BRANDING.md` and `CLAUDE.md` dark-theme tokens to document the new palette.

## [1.48.1] - 2026-06-01

### Changed
- **Load index tooltip now distinguishes R1 and R2 minimums.** The
  "Load Index" info tooltip previously stated a single blanket minimum
  load index of 116 for all Rivian vehicles. It now clarifies that R1
  vehicles (R1T, R1S) require a minimum load index of 116 while R2
  vehicles require a minimum of 112. Updated the string in
  `frontend/js/modules/tooltips.js` and rebuilt the frontend bundle.

## [1.48.0] - 2026-05-23

### Removed
- **Member sign-up entry point from the review flow.** WordPress
  registration is now disabled site-wide, so the "Sign up · or · Log in"
  banner that appeared above the submit button for guest reviewers had
  nowhere productive to send people. Removed the banner from both the
  standalone review page and the inline review modal on the catalog,
  deleted the associated CSS, and dropped the now-unused `register_url`
  from the localized review-data arrays in `class-rtg-frontend.php` and
  `class-rtg-tire-review.php`. Existing members are unaffected: the
  member review submission path (`submit_tire_rating`) is unchanged and
  `/wp-login.php` is still reachable for those who already have an
  account.

### Changed
- **Favorites heart button is hidden for non-logged-in visitors.** The
  heart overlay on each tire card previously redirected guests to
  `wp-login.php`, which now dead-ends since registration is off. The
  button is no longer rendered for guests in `cards.js`; existing
  members continue to see it as before. The "Filter to my favorites"
  toggle in the filter bar was already gated by `is_user_logged_in()`
  server-side and needed no change.

## [1.47.1] - 2026-05-22

### Changed
- **Declared compatibility with WordPress 7.0.** Added `Tested up to: 7.0`
  to the plugin header after auditing the 7.0 dev notes. No code changes
  were required: the plugin ships no Gutenberg blocks, uses none of the
  author link functions whose signatures changed in 7.0, and the AI
  feature removed in 1.46.0 left no residual references that would
  conflict with the new core AI Client API.

## [1.47.0] - 2026-04-20

### Security
- **Rate limiting hardened end-to-end.** Replaced the read-then-write
  transient pattern with an atomic `wp_cache_add` / `wp_cache_incr` path
  when a persistent object cache is available, falling back to transients
  otherwise. Rate-limit counters no longer drift under concurrent writes
  when Redis/Memcached is present.
- **Public AJAX endpoints now rate limited.** `rtg_get_tires`,
  `rtg_get_filter_options`, `rtg_track_click`, and `rtg_track_search`
  previously relied only on a nonce that is exposed in page source, so a
  scraper could harvest it and hammer the endpoint. Reads now cap at 120
  req/min per fingerprint; analytics caps at 240 req/min and drops silently
  when throttled so user typing isn't penalized. Reviews keep the existing
  tighter 3-per-5-minute limit in its own bucket so normal browsing can't
  starve review submissions or vice versa.
- **Guest name validation now trims first.** `submit_guest_tire_rating`
  rejected `""` but accepted `"   "` — whitespace-only submissions are now
  rejected with the same error.
- **Roamer assign/hide/restore batches now validate each tire ID** against
  the canonical regex and cap the batch at 50 entries. A malformed JSON
  payload can no longer slip a bad ID into `update_tire()`.

### Added
- **Admin email on Roamer sync failure.** When a scheduled cron sync can't
  reach the feed (wp_error, non-200, or invalid JSON), the admin gets an
  HTML email with the failure reason and a link to the Roamer Sync page.
  Throttled to one email per reason per 12h so an extended outage doesn't
  flood the inbox. Manual admin runs don't email (errors already show in
  the UI). Honors the existing `roamer_notify_enabled` setting.
- **Slow-response loading state for server-side pagination.** `#tireCards`
  gets a dimmed, input-blocking overlay after 500ms of fetch latency so
  fast responses don't flash a spinner but slow ones give feedback.
- **Rate-limit concurrency test.** New PHPUnit test issues max + 2 rapid
  guest submissions and asserts the overflow is blocked.

### Changed
- **Ratings no longer block card render.** `loadTireRatings` now runs in
  parallel with `renderCards`, and rating blocks are swapped in via
  `updateRatingDisplay` once the batch response arrives. Saves ~200ms of
  perceived latency on the first paint for each page.
- **Switch-slider toggles no longer use inline `onclick`.** The 3PMS/OEM
  filter pills now use `data-toggle-target` + a delegated listener wired
  up in `rivian-tires.js`, which plays nicer with strict CSP headers.
- **Mobile filter drawer manages keyboard focus on open.** The first
  focusable control inside `#mobileFilterContent` receives focus when the
  drawer opens, matching the WCAG 2.1 focus-management pattern used by
  the other modals/drawers.
- **Admin dashboard widget strings are now `_n()`-translatable.** "Missing
  links / missing images" copy goes through `_n()` + `__()` so translators
  can pluralize properly.
- **Sort whitelist centralized.** `RTG_Ajax::ALLOWED_SORTS` is the single
  source of truth for the sort keys accepted by `get_tires()`.

### Removed
- **Stale AI references in frontend code.** `search.js` no longer mentions
  the removed "AI" button in its module docstring; the leftover
  `console.time('Building search index')` production log is gone.

## [1.46.0] - 2026-04-15

### Removed
- **AI tire recommendations feature** — Usage was consistently under 1%, so
  the whole feature is gone end-to-end. Regular search (search button + Enter
  key) is unchanged.
  - Deleted `includes/class-rtg-ai.php` (the Anthropic Messages API wrapper,
    model list cache, rate limiter, response parser, tire context builder)
  - Deleted `frontend/js/modules/ai-recommend.js`
  - Removed Ask AI button, AI status area, and AI summary region from the
    tire-guide template
  - Removed the entire AI Recommendations card from the admin settings page
    (enable toggle, API key field, model dropdown, Refresh from Anthropic
    button, rate limit)
  - Removed admin-scripts.js `rtg_refresh_ai_models` click handler
  - Removed `.rtg-ai-*` CSS classes from `admin-styles.css` and
    `rivian-tires.css` (submit button, NEW badge, status spinner, error,
    summary banner, clear button, tire chips, highlight animation, model
    row, model select, refresh button)
  - Removed AJAX endpoints `rtg_ai_recommend` and `rtg_refresh_ai_models`
    and their handlers in `class-rtg-ajax.php`
  - Removed AI settings (enable / api key / model / rate limit) from the
    admin save handler in `class-rtg-admin.php`
  - Removed `aiEnabled` / `aiNonce` from the frontend localized data in
    `class-rtg-frontend.php`
  - Removed `RTG_AI::flush_cache()` call from `RTG_Database::flush_cache()`
  - Removed the `'ai'` value from the analytics `search_type` allowlist in
    `track_search()`
  - Removed `initAiRecommend` import + call from the frontend entrypoint
  - Removed `isAiEnabled` / `isAiActive` / `clearAiRecommendations` usage
    from `search.js` — `executeLocalSearch()` now just calls
    `filterAndRender()` directly
  - `uninstall.php` now also deletes the legacy `rtg_ai_models_cache`
    option and flushes any stray `_transient_rtg_ai_*` rows for sites
    upgrading from the AI era

### Migration notes
- Saved settings (`rtg_settings['ai_enabled']`, `ai_api_key`, `ai_model`,
  `ai_rate_limit`) are preserved on the existing install but no longer
  read by any code. They'll be removed automatically on plugin uninstall.
- If you had `RTG_ANTHROPIC_API_KEY` defined in `wp-config.php` from 1.45.x,
  you can remove it — nothing reads it anymore.
- The stored `rtg_ai_models_cache` option is no longer touched by the
  plugin and will be cleaned up on uninstall.

### Changed
- **Plugin version** — Bumped to 1.46.0 (minor bump to mark feature removal).

## [1.45.8] - 2026-04-15

### Changed
- **Filter-area typography normalized** — The extended-filter row's inline
  slider labels ("Max Price", "Min Warranty") were rendering at 13px/600
  uppercase muted (leftover from the old label-above-control layout) while
  the toggle labels next to them were 13px/500 non-uppercase primary. On the
  same row, the mismatch was visible. Both now render at 14px/600 sentence
  case primary, matching the vehicle buttons and the rest of the filter
  area's type scale. Slider value spans (`≤ $600`) stay lighter (14px/500
  muted) to read as dynamic values.
  (`frontend/css/rivian-tires.css`)
- **Favorites is now a heart icon in the filter header** — Replaced the
  Favorites toggle pill (previously in the sort bar) with a small hollow-
  heart icon button next to the Clear All button in the filter header. The
  heart fills and turns accent-colored when Favorites filtering is active,
  and the red count badge now rides on the heart button. The hidden
  `#filterFavorites` checkbox is unchanged, so every piece of filter JS
  keeps working — only the UI surface moved.
  (`frontend/templates/tire-guide.php`, `frontend/css/rivian-tires.css`)

### Removed
- `.favorites-filter-wrapper` and `.favorites-count-badge` CSS — the old
  pill-style Favorites toggle is gone.

### Changed
- **Plugin version** — Bumped to 1.45.8.

## [1.45.6] - 2026-04-15

### Changed
- **Flattened the filter drawer** — After the previous round of filter
  removals the "More Filters" disclosure was hiding only five controls.
  Five controls don't justify a progressive-disclosure pattern, so the
  disclosure is gone. Max Price slider, Min Warranty slider, 3PMS toggle,
  and OEM toggle now live in a flat `.rtg-extended-filters` row directly
  below the vehicle/size/brand/category row. One less click to reach any
  filter, no ghost grid columns, no "2 filters active" badge logic.
  (`frontend/templates/tire-guide.php`, `frontend/css/rivian-tires.css`)
- **Favorites moved to the sort bar** — Favorites is a personal collection,
  not a tire attribute, so it didn't belong with 3PMS and OEM in the feature
  row. It now lives next to the sort dropdown (logged-in users only), where
  it reads naturally as a "my stuff" control rather than a tire filter. The
  red favorites-count badge rides along.
  (`frontend/templates/tire-guide.php`)

### Removed
- `#advancedFiltersToggle`, `#advancedFilters`, `#advancedFilterBadge`,
  `#advancedFiltersBody` template elements and all their CSS
  (`.rtg-advanced-filters`, `.rtg-advanced-toggle`, `.rtg-advanced-body`,
  `.rtg-advanced-badge`, `.rtg-filter-section`, `.rtg-filter-section-label`)
- `getAdvancedFilterCount()` and `updateAdvancedFilterBadge()` from
  `filters.js`, along with their caller in `finishFilterAndRender()` and
  the auto-open-on-active-filter logic
- The advanced-filters click handler in `rivian-tires.js`

### Changed
- **Plugin version** — Bumped to 1.45.6.

## [1.45.5] - 2026-04-15

### Removed
- **Max Weight filter slider** — Removed from the advanced filters. The
  Efficiency Grade already summarizes weight's effect on range (weight is 26%
  of the efficiency score), so asking users to also set a max weight in
  pounds was asking them to do manual work the grade already did. Users who
  want range can still sort by Efficiency Grade or Real-World Efficiency.
- **EV Rated filter** — Removed from the advanced filters. Every tire in the
  guide works on a Rivian; this filter's meaning was ambiguous in a
  Rivian-specific context.
- **Studded filter** — Removed from the advanced filters. Niche use case —
  users who need studded tires can find them via the Winter category.
- **Reviewed filter** — Removed from the advanced filters. The name was
  confusing (external review link vs. user reviews), and the sort dropdown's
  "Most Reviewed" option covers the user-review case.

All four filters are fully removed end-to-end — UI template, client-side
filter logic, URL state, server-side AJAX payload, and the PHP
`get_filtered_tires()` WHERE clause builder. Underlying tire data (tags,
review links, weight) is untouched in the database, admin form, CSV
import/export, and the compare page. No effect on efficiency scores.

### Changed
- **Plugin version** — Bumped to 1.45.5.

## [1.45.4] - 2026-04-15

### Changed
- **Max PSI hidden from tire cards** — Removed Max PSI from the default card
  spec list. It's not useful information for most Rivian owners when browsing
  tires. Still rendered on the compare page (`compare.js:274`), still editable
  in the admin tire-edit form, still in CSV import/export. Max PSI is not
  part of the efficiency formula, so this change has no effect on any
  efficiency score. (`frontend/js/modules/cards.js`)
- **Plugin version** — Bumped to 1.45.4.

## [1.45.3] - 2026-04-15

### Changed
- **Simpler tire card specs** — The default card view now hides three specs
  that are cryptic for most buyers and already summarized by the efficiency
  grade: **Tread Depth**, **Max Load**, and **Load Range**. **UTQG** is shown
  conditionally — only when the tire actually has a UTQG value. When UTQG is
  empty or literally "None", the row is skipped so the card stops advertising
  missing data. The card drops from 13 spec rows to 9 (10 when UTQG is
  present). The hidden specs still live in the database, admin tire-edit
  form, CSV import/export, and the compare page — power users who want the
  full spec sheet haven't lost anything. Tread depth, load range, and UTQG
  still feed the efficiency score (42% of the total weight) on the backend
  exactly as before. (`frontend/js/modules/cards.js`)
- **Plugin version** — Bumped to 1.45.3.

## [1.45.1] - 2026-04-15

### Fixed
- **AI model Refresh button didn't fire** — The click handler lived outside
  `$(document).ready()` and relied on the element existing at script parse
  time. Switched to delegated binding via `$(document).on('click', ...)` so
  the handler works regardless of load order. Also wired it to the localized
  `rtgAdmin.ajaxurl` / `rtgAdmin.nonce` with sensible fallbacks.
- **AI model row layout** — The inline `vertical-align:middle;line-height:inherit`
  on the dashicon didn't center inside WP's `.button` class. Moved all the
  row styling into proper classes (`.rtg-ai-model-row`, `.rtg-ai-model-select`,
  `.rtg-ai-model-refresh-btn`, `.rtg-ai-model-status`) in `admin-styles.css`.
  Button is now 36px tall to match the select, the dashicon is sized and
  centered with `inline-flex`, and the icon spins while a refresh is in
  flight. Error states use an `.is-error` class instead of inline colours.

### Changed
- **Plugin version** — Bumped to 1.45.1 (also busts any stale browser cache
  of `admin-scripts.min.js`).

## [1.45.0] - 2026-04-15

Plugin review sweep — security hardening, performance, and accessibility fixes
across the PHP backend and JS frontend. One small admin feature (AI model list
refresh) is also included. No schema changes.

### Added
- **AI model list refresh** — The settings page now has a "Refresh from
  Anthropic" button next to the AI model dropdown. Clicking it calls
  Anthropic's `GET /v1/models` endpoint, caches the result in the
  `rtg_ai_models_cache` option, and rebuilds the dropdown in place without a
  page reload. The save-handler allowlist also reads from this cached list, so
  new Claude models become selectable immediately after refreshing — no code
  changes required. Previously-saved models that have been deprecated are
  preserved in the dropdown with a `(saved — not in current list)` suffix.
  (`includes/class-rtg-ai.php`, `includes/class-rtg-ajax.php`,
  `includes/class-rtg-admin.php`, `admin/views/settings.php`,
  `admin/js/admin-scripts.js`)

### Security
- **AI API key can live in `wp-config.php`** — Define `RTG_ANTHROPIC_API_KEY` to
  keep the Anthropic credential out of `wp_options`. The plugin settings field
  still works as a fallback when the constant is not set. (`includes/class-rtg-ai.php`)
- **Guest rate limiting no longer trusts raw `REMOTE_ADDR`** — Replaced the naive
  IP check with a per-visitor fingerprint (`md5(IP + truncated User-Agent)`) so a
  single spoofed IP from multiple clients can't bypass the limit. Logged-in users
  are fingerprinted by user ID. (`includes/class-rtg-ajax.php`)
- **Admin tire-delete hardens `$_GET['tire_id']`** — Input is now unslashed,
  sanitized, and validated before the nonce check, rejecting malformed IDs with
  a `wp_die()` instead of silently building an unpredictable nonce token.
  (`includes/class-rtg-admin.php`)
- **Security event logging** — Nonce failures and rate-limit hits now emit a
  compact `[RTG] {json}` line to `error_log` when `WP_DEBUG` is on, giving an
  audit trail without changing user-facing messages. (`includes/class-rtg-ajax.php`)

### Performance
- **Dashboard stats are now cached** — `RTG_Database::get_dashboard_stats()` runs
  roughly ten aggregation queries; results are memoised in a 5-minute transient
  and invalidated automatically by `flush_cache()` on any tire/rating write.
  (`includes/class-rtg-database.php`)
- **N+1 eliminated in AI context build** — `build_tire_context()` used to call
  `get_all_tires()` then a second `get_tire_ratings()` query. Added
  `get_tires_with_ratings()`, a single `LEFT JOIN` query returning tires with
  aggregated rating columns, and switched the AI path to use it. (`includes/class-rtg-database.php`, `includes/class-rtg-ai.php`)
- **Link checker `set_time_limit` is bounded and guarded** — The 300s / 120s
  hard-coded ceilings were replaced with `BATCH_SIZE × (REQUEST_TIMEOUT + 2)`
  and `PROGRESS_BATCH_SIZE × (REQUEST_TIMEOUT + 2)`, wrapped in a
  `function_exists('set_time_limit')` check for hosts that disable it.
  (`includes/class-rtg-ajax.php`)
- **Card cache LRU tightened to 20 entries** — Previous limit was 100 with a
  20-entry batch eviction; that effectively held 100 cloned DOM subtrees plus
  their image references in memory. Dropped to 20 with single-entry LRU eviction
  using `Map`'s insertion-order iteration. (`frontend/js/modules/cards.js`)
- **`IntersectionObserver` is now disposable** — Added `disconnectImageObserver()`
  and a `pagehide` listener so the shared observer is released on teardown.
  (`frontend/js/modules/cards.js`)

### Accessibility
- **Pagination controls are announced properly** — `#paginationControls` now
  carries `role="navigation"` + `aria-label`, each button has a descriptive
  `aria-label`, and the page info span is a `role="status"` / `aria-live="polite"`
  region so screen readers announce page changes. (`frontend/js/modules/filters.js`)
- **Image modal traps focus and returns focus on close** — Opening the modal
  remembers the launching element, focuses the dialog, traps Tab/Shift+Tab
  inside, and returns focus to the original element when closed. (`frontend/js/modules/image-modal.js`)

### Refactors
- **Single-source-of-truth `RTG_Database::validate_tire_id()`** — The
  `preg_match('/^[a-zA-Z0-9\-_]+$/', ...) && strlen <= 50` rule was duplicated
  across 15+ AJAX handlers and the database helper. Extracted to one static
  method and threaded through all callers. (`includes/class-rtg-database.php`,
  `includes/class-rtg-ajax.php`, `includes/class-rtg-admin.php`)
- **`get_filtered_tires()` split into builders** — Extracted
  `build_filter_where_clause()` and `build_filter_sort_clause()` so the main
  method is now a short count + fetch pair. No behaviour change, but the query
  body is dramatically easier to read and modify. (`includes/class-rtg-database.php`)
- **Memory-safe search listener binding** — `initializeSmartSearch()` used
  `cloneNode(true)` to strip old handlers, which orphaned references held in
  module-level caches. Now tracks the handler and calls `removeEventListener()`.
  (`frontend/js/modules/search.js`)
- **Pagination event delegation** — Replaced per-render `.onclick` assignments
  with a single delegated click handler on `#paginationControls`, using
  `data-pagination="prev|next"` attributes. (`frontend/js/modules/filters.js`)
- **Inline `style.cssText` replaced with CSS classes** — `.info-tooltip-trigger`
  and `.tire-card-tag-list` now live in `rivian-tires.css`, eliminating the
  hardcoded style blocks and mouseenter/mouseleave handlers in `cards.js`.
  Hover and focus states also get proper focus-visible treatment.
  (`frontend/css/rivian-tires.css`, `frontend/js/modules/cards.js`)

### Changed
- **Plugin version** — Bumped to 1.45.0.

## [1.44.2] - 2026-04-08

### Fixed
- **Vehicle breakdown not showing in tooltip** — The row sanitizer (`validateAndSanitizeCSVRow`) was stripping double quotes from all string cells, turning the JSON `[["Gen 1 R1T Dual",1]]` into unparseable `[[Gen 1 R1T Dual,1]]`. The vehicle_breakdown field (index 27) is now excluded from quote stripping.
- **Sync silently failing without vehicle_breakdown column** — The sync now ensures the `roamer_vehicle_breakdown` column exists before writing, so UPDATEs don't fail silently on sites where dbDelta missed it.
- **Wrong insert_tire format types** — Fixed misaligned `$formats` array in `insert_tire()` after the `roamer_session_count` removal shifted column positions.

### Changed
- **Plugin version** — Bumped to 1.44.2.

## [1.44.1] - 2026-04-08

### Fixed
- **Vehicle breakdown feed format** — Fixed parsing of the Roamer feed's `vehicle_breakdown` field which uses an array-of-pairs format (`[["Gen 1 R1T Dual", 1]]`) rather than an object. Tooltip, admin tire edit, and multi-assign merge logic all updated.
- **Vehicle breakdown column not created** — The `roamer_vehicle_breakdown` TEXT column could fail to be created by dbDelta on MySQL < 8.0.13 (TEXT columns don't support DEFAULT values). Added migration 14 as a safety net to explicitly create the column via ALTER TABLE.

### Changed
- **Plugin version** — Bumped to 1.44.1.

## [1.44.0] - 2026-04-08

### Changed
- **Roamer schema: sessions → distance** — Replaced `roamer_session_count` with `total_distance_km` from the updated Rivian Roamer feed. All surfaces now display total miles tracked instead of driving session counts. Multi-assign efficiency weighting changed from session count to total distance.
- **Vehicle breakdown by drivetrain** — New `vehicle_breakdown` field from the Roamer feed shows how many vehicles contributed by drivetrain (e.g. "Gen 1 R1T Dual", "Gen 2 R1T Tri"). Displayed in tile tooltips, compare page, admin tire edit, and stored as JSON in the database.
- **Distance displayed in miles** — All user-facing surfaces convert the source km values to miles for consistency with mi/kWh efficiency units. REST API field renamed from `roamer_total_km` to `roamer_total_miles`.
- **Database schema** — Migration 13 drops `roamer_session_count` column and adds `roamer_vehicle_breakdown` TEXT column to `wp_rtg_tires`.
- **Plugin version** — Bumped to 1.44.0.

## [1.43.0] - 2026-04-04

### Changed
- **Redesigned wheel guide section** — The "Not sure which tire fits your Rivian?" drawer now features vehicle tabs (R1T, R1S, R2, etc.) and a card grid layout, replacing the old flat list. Users can quickly filter by their vehicle and browse wheels in a clean, visual card format.

### Improved
- **Mobile wheel guide** — Card grid collapses to a single column on small screens with compact tab styling.

## [1.42.1] - 2026-04-04

### Improved
- **Roamer efficiency info tooltip** — The info icon on the Real-World Efficiency badge now shows the number of driving sessions and vehicles that contributed to the efficiency value for each tire.

### Changed
- **Plugin version** — Bumped to 1.42.1.

## [1.42.0] - 2026-04-03

### Added
- **OEM tire tag support** — Tires tagged with "OEM" display a green badge with a certificate icon on their card and can be filtered via a dedicated toggle.
- **OEM filter and server-side query** — New "OEM" toggle in the Features filter section with full server-side filtering support.

### Improved
- **Shortened feature filter labels** — Renamed "3PMS Rated" → "3PMS", "Studded Available" → "Studded", "Officially Reviewed" → "Reviewed", "My Favorites" → "Favorites" for a cleaner filter UI.
- **Wheel guide hidden on deep links** — The "Not sure which tire fits your Rivian?" section is now hidden when viewing a single tire via direct link.

### Fixed
- **Tire deep links in server-side mode** — Direct tire links (`?tire=tire001`) now work in server-side pagination mode by passing the tire ID to the PHP query.
- **Tire deep link render race condition** — Deep links in client-side mode no longer race with the async filter render.

### Removed
- **Reviewed badge** — Removed the rainbow "Reviewed" badge from tire cards (no longer used).

### Changed
- **Plugin version** — Bumped to 1.42.0.

## [1.41.2] - 2026-04-03

### Fixed
- **Tire deep links broken in server-side mode** — Direct tire links (`?tire=tire001`) were only handled in client-side mode. Added `tire_id` filter support to the server-side AJAX path and PHP query so deep links work regardless of pagination mode.

### Changed
- **Plugin version** — Bumped to 1.41.2.

## [1.41.1] - 2026-04-03

### Fixed
- **Tire deep links not rendering** — Direct tire links (`?tire=tire001`) were setting the filtered data but not re-rendering the card grid, so the page still showed all tires instead of the single linked tire.

### Changed
- **Plugin version** — Bumped to 1.41.1.

## [1.41.0] - 2026-04-03

### Added
- **OEM tire tag support** — Tires tagged with "OEM" now display a green badge with a certificate icon in the top-right corner of their card, making factory-original tires instantly recognizable.
- **OEM filter toggle** — New "OEM Tire" toggle in the filter sidebar lets users filter the catalog to show only Original Equipment tires.
- **OEM server-side filtering** — The OEM filter is applied server-side for consistent results across paginated and AJAX-loaded views.
- **OEM tooltip** — Added info tooltip explaining that OEM tires are Original Equipment from the factory on Rivian vehicles.

### Changed
- **Plugin version** — Bumped to 1.41.0.

## [1.40.0] - 2026-04-03

### Fixed
- **Dropdown counts no longer show (0) when a filter is selected** — Size, Brand, and Category dropdowns now count against rows filtered by everything *except* their own selection, so users see how many tires each option would yield if they switched to it.

### Improved
- **Collapsible advanced filters** — Sliders (price, warranty, weight) and toggle switches (3PMS, EV Rated, Studded, Reviewed, Favorites) are now tucked behind a compact inline "More Filters" toggle, keeping primary filters (vehicle, size, brand, category) always visible and reducing visual clutter.
- **Advanced filter badge** — The "More Filters" toggle shows an active filter count badge and auto-expands when any advanced filter is in use, so users never lose track of applied filters.
- **Filter section labels** — Added "Specifications" and "Features" sub-section labels within the advanced filters panel for clearer visual hierarchy and grouping.
- **Clear All button** — Relocated from the toggle row into the filter header bar for constant visibility, and restyled from red to muted text with accent gold hover to match the UI palette.
- **Sort dropdown moved into filter card** — The sort dropdown now lives inside the filter card beneath the primary filters, matching the "Filter, Sort, and Compare" header and keeping all controls in one place.
- **Two-column toggle grid** — Toggle switches now use a CSS grid layout with `auto-fill` columns for consistent two-column alignment on desktop and clean single-column stacking on mobile.
- **Compact slider group** — Reduced padding, gap, and font size on the price/warranty/weight sliders for a tighter footprint within the advanced filters panel.
- **Wheel guide moved to standalone callout** — The "Not sure which tire fits your Rivian?" wheel guide is now a standalone collapsible section below the filter card with its own styled trigger, separating help content from filter controls.

### Changed
- **Plugin version** — Bumped to 1.40.0.

## [1.36.0] - 2026-04-02

### Fixed
- **Roamer efficiency NaN guard** — Added `Number.isFinite()` check before displaying Roamer real-world efficiency values to prevent `Infinity` or `NaN` from rendering broken badges on tire cards.
- **Rating sort error handling** — Added `.catch()` handler to the `loadTireRatings` promise chain in `filterAndRender` so sorting by rating or most-reviewed still completes on network failure instead of silently breaking.
- **Card cache eviction** — Cache now evicts 20 entries at a time when the 100-entry limit is reached, preventing single-evict thrashing that allowed unbounded memory growth on long sessions.
- **Favorites error feedback** — Failed favorite toggle (network error or server rejection) now shows a visible toast notification instead of silently reverting the optimistic UI update.
- **REST API IP validation** — Rate limiter in the REST API now uses `filter_var(FILTER_VALIDATE_IP)` for proper IPv4/IPv6 validation instead of a regex that could pass malformed addresses.
- **REST efficiency endpoint validation** — The `POST /efficiency` endpoint now returns a 400 error when the required `size` parameter is missing, instead of silently calculating with empty data.
- **AJAX tire_ids cap** — The `get_tire_ratings` AJAX handler now caps the `tire_ids` array to 200 entries to prevent query explosion from malicious or malformed requests.

### Improved
- **Image modal accessibility** — Added `role="dialog"`, `aria-modal="true"`, and `aria-label` attributes to the full-screen image preview modal for screen reader support.
- **Honeypot accessibility** — Added `aria-hidden="true"` to the hidden honeypot field in the guest review modal so screen readers no longer announce it.

### Changed
- **Plugin version** — Bumped to 1.36.0.

## [1.35.0] - 2026-03-31

### Added
- **Hide unmatched Roamer tires** — New "Hide" button on the Roamer Sync page lets you permanently dismiss unmatched tires that aren't compatible with Rivian (e.g. insufficient load rating). Hidden tires are excluded from future syncs and won't reappear.
- **Restore hidden tires** — Hidden Roamer tires can be viewed and restored via a collapsible "View Hidden Tires" button on the Roamer Sync page.
- **Coverage stat** — Sync Status card now shows a linked/total coverage percentage (e.g. "32/45 — 71%") at a glance.

### Changed
- **Default sort is now Real-World Efficiency** — The tire guide sort dropdown defaults to "Real-World Efficiency" instead of "Rating: High → Low", so visitors see efficiency-ranked tires first.
- **Collapsible Linked & Unlinked tables** — Linked Tires and Unlinked Guide Tires sections on the Roamer Sync page are now collapsed by default to reduce clutter, with click-to-expand headers.
- **Relative timestamps** — "Last Sync" and per-tire "Last Synced" now display as relative time (e.g. "2 hours ago") with full datetime on hover.
- **Unmatched sorted by session count** — Unmatched Roamer tires are now sorted by session count descending so the most impactful tires appear first.
- **Plugin version** — Bumped to 1.35.0.

## [1.33.0] - 2026-03-30

### Added
- **Real-world efficiency in AI search** — The AI Tire Advisor now includes Rivian Roamer real-world efficiency data (mi/kWh and session count) in its tire context. When users ask about range or efficiency, the AI factors in actual driving data from Rivian owners alongside the calculated efficiency grade.

### Fixed
- **Roamer efficiency unit conversion** — Source data from Rivian Roamer (`efficiency_km_per_kwh`) is in km/kWh. Values are now correctly converted to mi/kWh (× 0.621371) during sync. Admin labels on the Roamer Sync page and tire edit form updated from "km/kWh" to "mi/kWh" for consistency.
- **Compare view Real-World Efficiency styling** — Added blue pill background behind mi/kWh values and fixed the row label background not filling the full row height on multi-line rows.

### Changed
- **Plugin version** — Bumped to 1.33.0.

## [1.30.0] - 2026-03-30

### Added
- **Rivian Roamer real-world efficiency data** — Integrates live tire efficiency data (mi/kWh) collected from Rivian owners via [Rivian Roamer](https://rivianroamer.com). Data syncs automatically twice daily via WP-Cron and is displayed alongside the calculated efficiency score on tire cards, comparison pages, and the REST API feed.
- **Roamer Sync admin page** — New admin page (Tire Guide > Roamer Sync) for managing the integration: sync status dashboard, settings (enable/disable, feed URL), linked tires table, ambiguous match resolution with dropdown assignment, unmatched Roamer tires with multi-select assign, and paginated unlinked guide tires list.
- **Manual Roamer mapping** — Tires with the same name and size but different load ratings are flagged as ambiguous and skipped for manual review. Admins can assign Roamer data via the sync page or directly on the tire edit form. Multiple Roamer entries can be assigned to one tire with weighted-average efficiency.
- **Real-World Efficiency sort** — New "Real-World Efficiency" option in the sort dropdown, ordering tires by mi/kWh (tires without data sorted to bottom).
- **Real-World Efficiency on compare page** — New row in the Performance section showing mi/kWh with session count and best-value highlighting.
- **Roamer fields in REST API** — The `/wp-json/rtg/v1/feed` endpoint now includes `roamer_efficiency`, `roamer_session_count`, `roamer_vehicle_count`, and `roamer_synced_at`.
- **Tire edit form Roamer section** — New "Rivian Roamer — Real-World Data" card on the tire edit page showing linked Roamer ID, mi/kWh, session count, vehicle count, and km tracked.
- **Dashboard Roamer cards** — New "Rivian Roamer — Real-World Efficiency" overview card with coverage, avg/best/worst mi/kWh, total sessions, total vehicles, and last sync status. New "Most Efficient (Real-World)" ranked list of top 5 tires by Roamer mi/kWh.
- **WP Dashboard widget** — Roamer sync coverage stat (X/Y tires linked) with link to Roamer Sync page.

### Improved
- **Real-world efficiency display** — mi/kWh shown as a bordered pill badge next to the calculated efficiency badge on tire cards, with its own info tooltip linking to Rivian Roamer. Tags (EV Rated, etc.) moved to a dedicated row at the bottom of the spec list.

### Fixed
- **Ambiguous/unmatched assignments persist** — Assigned tires are now removed from the stored sync stats immediately, so they no longer reappear in the ambiguous or unmatched tables after page reload or sync.
- **Multi-assign sync recognition** — Comma-separated `roamer_tire_id` values from multi-assign are now correctly recognized during sync so tires stay linked.

### Changed
- **Database schema** — Migration 12 adds 6 columns to `wp_rtg_tires`: `roamer_tire_id`, `roamer_efficiency`, `roamer_session_count`, `roamer_total_km`, `roamer_vehicle_count`, `roamer_synced_at`.
- **Plugin version** — Bumped to 1.30.0.

## [1.28.2] - 2026-03-16

### Fixed
- **Tire size dropdown no longer disables options** — Previously, selecting a tire size would disable all other size options with zero matches, forcing users to clear filters before switching. All dropdown options now stay enabled so users can freely change their selection.

### Changed
- **Plugin version** — Bumped to 1.28.2.

## [1.28.1] - 2026-03-16

### Changed
- **Minified assets verified** — Rebuilt all minified JS and CSS bundles (esbuild) to ensure production assets are up-to-date with source files.
- **Plugin version** — Bumped to 1.28.1.

## [1.28.0] - 2026-03-15

### Added
- **JSON data feed endpoint** — New public REST API endpoint (`GET /wp-json/rtg/v1/feed`) returns the full tire catalog as a shareable JSON feed. Includes all tire specs, efficiency scores, and rating aggregates. The feed auto-updates whenever tires are added or modified. CORS-enabled for easy external consumption.
- **JSON Feed URL in admin dashboard** — A new "JSON Data Feed" card on the admin dashboard displays the feed URL with a one-click copy button and a preview link, making it easy to share your tire data with others.

### Changed
- **Plugin version** — Bumped to 1.28.0.

## [1.27.0] - 2026-03-06

### Changed
- **Dark theme color refresh** — Updated the default dark theme palette: unified accent hover with primary accent, matched card background to primary background, adjusted deep background, consolidated text colors for better readability, and updated border/divider color.
- **Button text contrast** — Buttons with the accent background now use dark text (#0f172a) instead of white for improved readability.
- **Plugin version** — Bumped to 1.27.0.

## [1.26.1] - 2026-03-06

### Changed
- **Efficiency formula recalibration** — Recalibrated width and weight score baselines to produce fair scores across both R1 and R2 tire ranges. Added speed rating Y to the scoring map. Existing tire scores will shift minimally (~1-2 points). Run "Recalculate All" from the admin to update.
- **R2 default tire sizes** — Added R2 tire sizes to the default sizes dropdown.
- **Plugin version** — Bumped to 1.26.1.

## [1.26.0] - 2026-03-06

### Added
- **Vehicle filter toggle** — A segmented toggle (All / R1 / R2) now appears above the filter dropdowns, letting users instantly filter tires by Rivian vehicle. R1T and R1S are grouped as "R1" since they share the same tire sizes. Selecting a vehicle cascades to narrow the Size dropdown to only compatible sizes.
- **Vehicle-to-size mapping from wheels** — Vehicle compatibility is automatically derived from the stock wheels database. Adding a wheel with a new vehicle (e.g., R2) in the Wheels admin makes it appear in the frontend toggle with no additional configuration.
- **Vehicle shortcode attribute** — New `vehicle` attribute for the `[rivian_tire_guide]` shortcode: `[rivian_tire_guide vehicle="R2"]` pre-filters to that vehicle on page load.
- **Vehicle URL parameter** — `?vehicle=R2` filter state is preserved in the URL for sharing and browser back/forward navigation.
- **Vehicle filter chip** — Active vehicle filter appears as a dismissible chip alongside other active filter pills.
- **Vehicle in smart no-results** — When no tires match, the smart suggestions include an option to remove the vehicle filter.

### Changed
- **Vehicle toggle layout** — Moved the vehicle toggle inline with the Size/Brand/Category dropdowns instead of floating alone above them. Matches dropdown height for a cleaner, more integrated look.
- **Plugin version** — Bumped to 1.26.0.

## [1.25.1] - 2026-03-06

### Improved
- **Frontend tire size filter managed from admin** — The size filter dropdown on the frontend is now sourced from the admin-managed sizes list in Settings → Dropdown Options, merged with sizes found in tire data. New tire sizes added in the admin panel immediately appear in the frontend filter without needing to first create a tire with that size.

### Changed
- **Plugin version** — Bumped to 1.25.1.

## [1.25.0] - 2026-03-04

### Improved
- **Slider labels show filtering direction** — Price and weight sliders now display "Max Price: ≤ $X" and "Max Weight: ≤ X lbs" to clarify they set an upper bound. Weight label now includes "lbs" unit.
- **Warranty filter flipped to minimum threshold** — The warranty slider now filters for tires with *at least* the selected mileage (≥), matching user intent. Previously it filtered for tires *up to* a value which was counterintuitive.
- **Mobile filter button shows active count** — On mobile, the collapsed filter toggle now displays a badge (e.g., "Filters (3)") showing how many filters are active, and properly toggles between "Show/Hide Filters".
- **Live result count in filter panel** — A new inline count ("42 tires match your filters") appears inside the filter section when filters are active, giving immediate feedback without scrolling to results.
- **Dropdown options show tire counts** — Size, Brand, and Category dropdowns now display the number of matching tires per option (e.g., "Continental (12)"). Options with zero matches are disabled to prevent dead-end selections.
- **Clear All button restyled** — The reset button is now visually distinct from filter toggles, using a red outline style with a rotate icon so users recognize it as a destructive action.
- **Tooltip button styles moved to CSS** — Inline styles and `onmouseenter`/`onmouseleave` handlers on info tooltip buttons have been replaced with a proper `.info-tooltip-trigger` CSS class, improving maintainability and touch device support.
- **Sort dropdown handler fixed** — Removed inline `onchange` attribute from the sort dropdown. It now uses a proper event listener that correctly routes through server-side rendering when in server-side mode.

### Changed
- **Plugin version** — Bumped to 1.25.0.

## [1.24.3] - 2026-03-02

### Fixed
- **Review email link broken** — The guest review notification email linked to a non-existent admin page (`rivian-tire-guide-reviews` instead of `rtg-reviews`), causing a "not allowed to access this page" error when clicking the link.

### Changed
- **Plugin version** — Bumped to 1.24.3.

## [1.24.2] - 2026-03-02

### Added
- **Link check progress bar** — The "Check Links Now" button now shows a live progress bar with status text ("Checking link 12 of 38...") and a running count of broken links found. Links are checked in batches of 5 via sequential AJAX calls, replacing the single long-running request. Page auto-reloads after 1.5 seconds with a summary message.

### Fixed
- **Network error alert on page leave** — Navigating away during a link check no longer shows a "Network error" alert. An `isUnloading` flag suppresses error callbacks from cancelled AJAX requests, and a `beforeunload` confirmation warns the user that a check is still running.

### Changed
- **Plugin version** — Bumped to 1.24.2.

## [1.24.0] - 2026-03-02

### Added
- **Affiliate link health checker** — New `RTG_Link_Checker` class that detects broken affiliate links by following redirects and flagging links that land on the supplier homepage instead of the product page. Also catches HTTP errors (4xx/5xx) and connection failures.
- **Weekly automated checks** — WP-Cron runs the link health check once per week. A custom `weekly` cron schedule is registered for this purpose.
- **"Check Links Now" button** — Manual trigger on the Affiliate Links admin page to run the health check on demand without waiting for the weekly schedule.
- **Broken link badges** — Each broken tire row in the Affiliate Links table shows a red "Broken" badge with a tooltip explaining the failure reason (e.g. "Redirects to homepage").
- **"Broken" filter tab** — New filter tab on the Affiliate Links page to show only tires with broken links, alongside the existing All/Affiliate/Regular/Missing/No Review tabs.
- **Broken Links stat card** — New stat card in the Affiliate Links stats grid showing the count of broken links detected.
- **Broken link email notification** — HTML email sent to the site admin when broken links are found, listing each affected tire with its status and failure details, plus a direct link to the admin dashboard.
- **Dashboard health indicator** — Content Health section on the main dashboard now includes a broken affiliate links indicator with a link to the Affiliate Links page for remediation.

### Fixed
- **"Check Links Now" network error** — The AJAX handler could exceed PHP's `max_execution_time` when checking many links. Added `set_time_limit(300)` and eliminated a redundant second HTTP request per link in `get_effective_url()` by reading the final URL from WordPress's transport response object.
- **Affiliate link checker missing broken CJ links** — `check_single_link()` used `wp_remote_head`, but affiliate networks like CJ (jdoqocy.com) only redirect GET requests, not HEAD. Switched to `wp_remote_get` with a 4 KB response size limit so the full redirect chain is followed.

## [1.23.0] - 2026-03-01

### Added
- **Standalone tire review page** — New shareable page at `/tire-review/` where anyone can select a tire and submit a review without navigating the full catalog. Features a searchable tire dropdown, inline review form with star rating, and support for both logged-in and guest reviewers. Deep-link to a specific tire via `?tire=TIRE_ID` for social sharing with tire-specific OG/Twitter meta tags.
- **Tire Review Page Slug setting** — Configurable URL slug for the review page in Settings > Display Settings (default: `tire-review`).

## [1.22.0] - 2026-03-01

### Changed
- **Share image: show tire size on top-rated callout** — The top-rated tire now displays the size in parentheses (e.g. "Michelin Defender LTX M/S (275/65R18)") so tires with the same name but different sizes can be distinguished.
- **Share image: show Avg Efficiency out of 100** — The Avg Efficiency stat card now renders as "72 / 100" instead of just "72" for clearer context.

## [1.21.1] - 2026-03-01

### Fixed
- **Share image: category pills overlapping top-rated callout** — The category pills and top-rated tire banner occupied the same vertical space when 5 brands were present. The callout Y-position is now computed dynamically from the bottom of both the stat cards and the categories section, and brand bar spacing was tightened to give categories more room.

## [1.21.0] - 2026-03-01

### Added
- **Stats share image generator** — New admin page (Tire Guide > Share Image) that generates a branded 1200x630 social media image with top stats from the tire guide. The image uses the frontend dark theme colors (dark navy background, orange accent) and includes total tires, average price, average efficiency score, community reviews, top brands bar chart, category pills, and top-rated tire callout. Customizable title, subtitle, and footer text fields with live canvas preview. Download as PNG, copy to clipboard, or regenerate on demand.

### Changed
- **Plugin version** — Bumped to 1.21.0.

## [1.20.3] - 2026-02-23

### Fixed
- **Officially Reviewed filter not filtering tires** — The client-side filter checked `row[23]` (`created_at`) instead of `row[22]` (`review_link`). Since every tire has a `created_at` timestamp, the filter never excluded anything. Also added the missing server-side plumbing so the filter works in server-side pagination mode: the `reviewed` parameter is now sent in the AJAX request, accepted by the PHP handler, and applied as a `review_link != ''` WHERE clause in the database query.
- **Plugin version** — Bumped to 1.20.3.

## [1.20.1] - 2026-02-22

### Fixed
- **Nonce passed to review endpoints** — `get_tire_reviews` and `rtg_get_user_reviews` AJAX calls now include the nonce for logged-in users, fixing "Security check failed" errors when authenticated users attempted to load reviews or view their review history.
- **Plugin version** — Bumped to 1.20.1.

## [1.20.0] - 2026-02-22

### Added
- **Delete own rating** — Logged-in users can now delete their own tire rating via a new `delete_tire_rating` AJAX endpoint. Backed by `RTG_Database::delete_user_rating()` which only deletes ratings matching the current user. Returns updated aggregate rating data after deletion.
- **Admin dashboard widget** — WordPress dashboard widget ("Tire Guide — Quick Stats") showing total tires, average rating, total ratings, average price, pending review count with link to moderation queue, missing links/images counts, and top-rated tire at a glance.
- **AJAX integration tests** — New `tests/test-ajax.php` with 14 integration tests extending `WP_Ajax_UnitTestCase`. Covers `get_tire_ratings`, `submit_tire_rating` (success, missing nonce, invalid rating, nonexistent tire, review text with pending status), `delete_tire_rating` (success, no rating, cross-user prevention), `get_tire_reviews`, and favorites lifecycle (add/get/remove cycle, nonexistent tire).
- **PHP coding standards enforcement** — `.phpcs.xml` configuration for WordPress Coding Standards (WPCS 3.x). Scans `includes/`, `admin/`, main plugin file, and `uninstall.php`. New `phpcs` CI job in GitHub Actions with `cs2pr` for inline PR annotations.

### Changed
- **PHP autoloader** — Replaced 12 manual `require_once` calls with `spl_autoload_register()`. Maps `RTG_` prefixed class names to `includes/class-rtg-*.php` files automatically. New classes are loaded without editing the main plugin file.
- **PHPDoc blocks** — Added `@param`, `@return`, and description blocks to all public methods in `RTG_Database` and `RTG_Admin`. Cleaned up orphaned dangling PHPDoc block.
- **PLUGIN-REVIEW.md** — All 41 of 41 review items now resolved (100%).
- **Plugin version** — Bumped to 1.20.0.

## [1.19.9] - 2026-02-22

### Changed
- **Auth banner margin** — Added consistent bottom, left, and right margins to the login/register banner so it aligns with the modal's field padding (24px on desktop, 16px on mobile).
- **Plugin version** — Bumped to 1.19.9.

## [1.19.8] - 2026-02-22

### Changed
- **Mobile auth banner redesign** — Redesigned the guest login/register banner in the review modal for mobile. The banner now displays as a centered card with the user icon in a circular accent-tinted badge, descriptive text ("Create an account to edit reviews and favorite tires"), and pill-shaped action buttons — "Sign up" as a filled primary button and "Log in" as an outlined secondary button. Desktop layout remains a compact inline banner.
- **Plugin version** — Bumped to 1.19.8.

## [1.19.7] - 2026-02-22

### Changed
- **Mobile review modal — full-screen takeover** — Replaced the bottom-sheet pattern with a full-screen native-app-style page on screens ≤640px. The modal now covers the entire viewport with a sticky top nav bar (close button on left, centered title), scrollable body, and a full-width sticky "Submit Review" button at the bottom. Stars are 40px with 14px gap for fat-finger tapping. Inputs use 16px font to prevent iOS auto-zoom. Animation uses a spring-like `cubic-bezier(0.32, 0.72, 0, 1)` curve for smooth slide-up. Safe-area insets respect iPhone notch/Dynamic Island and home indicator.
- **Plugin version** — Bumped to 1.19.7.

## [1.19.6] - 2026-02-22

### Fixed
- **Efficiency score info icon too small** — The info icon next to the efficiency score on tire cards was 12px in a 16×16 button, while all other info icons use 14px in a 20×20 button. Matched sizing, padding, and added missing `aria-label` and `type` attributes.
- **Officially Reviewed filter icon wrong color** — The "Officially Reviewed" filter toggle was excluded from the JS tooltip replacement that runs on page load, so it kept the PHP-hardcoded `#94a3b8` color while the other three filter icons used `var(--rtg-text-muted)` (`#8493a5`). Added it to both `updateFilterTooltipsDirectly()` and `updateFilterTooltips()`.
- **AI clear not resetting tire view** — Clicking "Clear" on the AI recommendation summary did not restore the default tire grid because `filterAndRender()` short-circuited on a matching `lastFilterState` cache key. Now clears `state.lastFilterState` before calling `filterAndRender()` so the full filter pipeline re-runs.

### Changed
- **Mobile review modal — bottom sheet** — The review modal on screens ≤640px now slides up from the bottom as a sheet with a drag-handle indicator, rounded top corners, sticky footer with action buttons, larger star touch targets (36px), 16px font inputs to prevent iOS auto-zoom, and `env(safe-area-inset-bottom)` padding for notched devices.
- **Plugin version** — Bumped to 1.19.6.

## [1.19.5] - 2026-02-22

### Security
- **Admin image preview XSS fix** — Sanitized the user-supplied image URL in the admin preview handler using the `URL` constructor to break the CodeQL taint chain (`js/xss-through-dom`). The parsed URL's protocol is checked against `http(s):` and the image extension is validated on `pathname` only, then the sanitized `parsed.href` is assigned to the native `HTMLImageElement.src` property. This replaces the previous regex-on-raw-input approach that CodeQL flagged as DOM text reinterpreted as HTML.

### Changed
- **Plugin version** — Bumped to 1.19.5.

## [1.19.3] - 2026-02-22

### Changed
- **Plugin review audit** — Audited all 7 high-priority items from `PLUGIN-REVIEW.md` and confirmed each was resolved in prior versions: rate limiting (v1.1.0), compare page XSS (v1.1.0), CSV import/export (v1.2.0), server-side pagination (v1.3.0), URL filter persistence (v1.10.0), accessibility (v1.2.0 + v1.14.0), and PHPUnit/JS test suites (v1.3.0 + v1.14.0). Updated the review document with resolution details, version references, and a note on medium/low items also addressed.
- **Plugin version** — Bumped to 1.19.3.

## [1.19.2] - 2026-02-22

### Fixed
- **Tire images loading too late** — Removed conflicting `loading="lazy"` and `fetchpriority="low"` attributes from tire card images. These were double-gating the IntersectionObserver-based lazy loading, causing images to appear blank or pop in on-screen. The IntersectionObserver is now the sole loading controller, with `decoding="async"` for non-blocking decode. Root margin increased from 200px to 600px so images load well before scrolling into view.

### Changed
- **Plugin version** — Bumped to 1.19.2.

## [1.19.1] - 2026-02-21

### Security
- **AI rate limiter IP spoofing fix** — `get_client_ip()` now prioritizes `REMOTE_ADDR` over proxy headers (`X-Forwarded-For`, `X-Real-IP`). Proxy headers are only trusted when `REMOTE_ADDR` is a private/reserved IP, indicating the server is behind a reverse proxy. Previously, attackers could bypass AI rate limiting entirely by forging proxy headers.
- **CSV import MIME validation** — Added `finfo`-based MIME type validation alongside the existing file extension check for defense-in-depth on CSV uploads.
- **Nonce verification on public review endpoints** — `get_tire_reviews` and `get_user_reviews` AJAX endpoints now verify nonces for logged-in users, consistent with the existing `get_tire_ratings` pattern.

### Changed
- **Plugin version** — Bumped to 1.19.1.

## [1.19.0] - 2026-02-21

### Added
- **Guest tire reviews** — Non-logged-in users can now submit tire reviews with their name and email. Guest reviews require a title or body text (not just star ratings) and are always held for admin approval before going live.
- **Guest review modal** — Full review modal for guests with name, email, star rating, title, and body fields. Includes a honeypot field for spam prevention.
- **Interactive stars for guests** — Star ratings are now clickable for logged-out users and open the guest review modal with the selected rating pre-filled.
- **Login/register banner** — Guest review modal shows a "Sign up or Log in to edit reviews and favorite tires" banner encouraging account creation, with links to the login and registration pages.
- **Guest review localStorage pre-fill** — Name and email are saved after a successful guest review and auto-filled the next time the modal opens, reducing friction for multi-tire reviewers.
- **"Review Pending" badge** — After a guest submits, the card swaps "Write a Review" for a styled "Review Pending" indicator (session-only, resets on page reload).
- **Admin email notification for guest reviews** — Site admin receives a styled HTML email with the guest's name, email, star rating, and review snippet whenever a new guest review is submitted, with a "Review in Dashboard" button.
- **Reviewer approval email** — When any review (guest or logged-in) is approved by an admin, the reviewer receives a styled HTML email notification with their review snippet and a link back to the tire guide.
- **IP rate limiting for guests** — Guest submissions are rate-limited to 3 per 5 minutes per IP address.
- **Duplicate guest review detection** — Prevents the same email from reviewing the same tire twice.
- **Database migration 11** — Added `guest_name` and `guest_email` columns to the ratings table, updated the unique key to `(user_id, tire_id, guest_email)` to support multiple guests per tire.
- **RTG_Mailer class** — New mailer class for sending HTML email notifications via `wp_mail()`, respecting any SMTP plugin configuration.
- **Schema.org structured data** — Guest author names are automatically handled in review structured data.

### Changed
- **Guest rate limit tightened** — Reduced from 10 submissions per minute to 3 per 5 minutes for better spam protection.
- **Guest reviewer links** — Reviews from guests (user_id 0) no longer link to a reviewer profile page in the reviews drawer.
- **Admin reviews list** — Guest reviews now display the guest's name and email with a "Guest" badge in the WordPress admin reviews panel.
- **Removed login-prompt CSS** — Cleaned up unused `.login-prompt` styles that were replaced by the guest review flow.

## [1.18.6] - 2026-02-21

### Added
- **RivianTrackr search redirect** — When a search returns no matching tires, a "Search RivianTrackr" link now appears directing users to riviantrackr.com for non-tire topics. Applies to both the local search no-results view and the AI recommendation path.

### Changed
- **AI error display** — When a query is present, the red AI error text is hidden and replaced with only the clean RivianTrackr search redirect link. The error message still appears for edge cases with no query.
- **RivianTrackr link hover** — Removed underline on hover for the RivianTrackr search redirect links.

### Fixed
- **Analytics tire list spacing** — Tire name and "unique visitors" text in the analytics dashboard now stack on separate lines instead of running together.
- **Plugin version** — Bumped to 1.18.6.

## [1.18.3] - 2026-02-21

### Changed
- **AI summary tire names** — Removed underline/highlight styling from tire names in the AI recommendation summary text. Tire chips below the summary already provide clickable navigation to each recommended tire.
- **Button text color** — Changed Ask AI and Search button text color to dark (`#0f172a`) to match the affiliate "View Tire" buttons on tire cards, improving visual consistency across the plugin.
- **Pagination button hover color** — Next/Previous pagination buttons now switch to dark text (`#0f172a`) on hover when the accent background appears, matching the Ask AI and affiliate button style.
- **Mobile search layout** — Fixed Search and Ask AI buttons not wrapping below the search input on mobile. Moved the mobile media query after the base button styles so the overrides take effect correctly.
- **Plugin version** — Bumped to 1.18.3.

## [1.18.0] - 2026-02-20

### Added
- **AI Tire Advisor** — New natural-language search powered by Anthropic's Claude API. Visitors can type queries like "best winter tire for my Rivian with 20 inch wheels" and receive AI-ranked recommendations drawn from the tire catalog data (specs, ratings, reviews, efficiency grades). The AI search bar lives inside the Filter, Sort, and Compare panel alongside the existing search and filters.
- **Admin AI settings** — New "AI Tire Recommendations" settings card with enable/disable toggle, Anthropic API key input, model selector (Claude Haiku 4.5 or Claude Sonnet 4), and configurable per-visitor rate limit.
- **AI rate limiting** — Per-IP rate limiting via WordPress transients to control API costs (default: 10 queries/minute/visitor).
- **AI response caching** — Identical queries are cached for 1 hour to reduce API calls and speed up repeated questions.

### Changed
- **Plugin version** — Bumped to 1.18.0.

## [1.17.2] - 2026-02-20

### Changed
- **Plugin version** — Bumped to 1.17.2 to bust browser and CDN caches after the Newest Added sort fix.

## [1.17.1] - 2026-02-20

### Fixed
- **"Newest Added" sort broken** — The client-side "Newest Added" sort option was reading from the wrong array index (24 instead of 23), causing it to compare empty values and effectively not sort at all. Tires now correctly sort by `created_at` descending.

## [1.17.0] - 2026-02-20

### Changed
- **Analytics timezone** — Daily charts (Clicks Over Time, Search Volume) now group data by the WordPress site timezone instead of UTC, so dates on the analytics dashboard match the site owner's local time.
- **Bar graph alignment** — Fixed "Most Used Filters" horizontal bar chart so bar tracks align consistently regardless of label length. Labels now use a fixed width with text truncation.
- **Plugin version** — Bumped to 1.17.0.

### Removed
- **Bundle links** — Removed the bundle link feature from the entire plugin UI. The bundle link field has been removed from tire editing, the affiliate links dashboard, the comparison page, tire cards, analytics charts, and CSV import/export. The database column is retained for backwards compatibility but is no longer surfaced anywhere.

## [1.15.0] - 2026-02-19

### Added
- **esbuild build pipeline** — New `package.json` with `npm run build` and `npm run build:watch` commands. esbuild minifies all JS and CSS assets, producing `.min.js` and `.min.css` files. Console/debugger statements are automatically stripped from production builds. Replaces the ad-hoc `build.sh` script.
- **GitHub Actions CI** — New `.github/workflows/ci.yml` runs JS tests, build verification, and PHP syntax linting (PHP 7.4, 8.0, 8.2) on every push and pull request.
- **REST API rate limiting** — All REST API endpoints now enforce per-IP rate limits via WordPress transients: 60 requests/minute for read endpoints, 10 requests/minute for the write (efficiency) endpoint. Returns HTTP 429 when exceeded.
- **Inline SVG icon system** — Replaced the Font Awesome 6.5 CDN dependency (~60 KB CSS + web fonts) with lightweight inline SVGs. New `RTG_Icons` PHP class and `rtgIcon()` JS helper render icons from a shared map. All ~35 Font Awesome icon references across JS and PHP templates have been replaced. CSP headers updated to remove cloudflare.com allowance.
- **Mobile range slider improvements** — Added editable number inputs alongside range sliders on mobile (visible below 600px breakpoint). Number inputs and sliders sync bidirectionally. Slider thumbs have larger 28px touch targets on mobile for easier interaction.

### Changed
- **Asset loading** — Frontend, compare, and admin pages now serve minified assets (`.min.js`/`.min.css`) when available and `SCRIPT_DEBUG` is off. Falls back to unminified sources for development.
- **Plugin version** — Bumped to 1.15.0.

## [1.14.0] - 2026-02-19

### Added
- **REST API** — New public REST API under `rtg/v1` namespace with four endpoints: `GET /tires` (filtered, paginated listing), `GET /tires/{tire_id}` (single tire with ratings), `GET /tires/{tire_id}/reviews` (paginated reviews), and `POST /efficiency` (calculate efficiency score from specs). All inputs validated and sanitized per WordPress REST API conventions.
- **Shortcode attributes** — The `[rivian_tire_guide]` shortcode now accepts optional pre-filter attributes: `size`, `brand`, `category`, `sort`, and `3pms`. Example: `[rivian_tire_guide brand="Michelin" category="All-Season" sort="price-asc"]`.
- **Skeleton loading states** — Shimmer placeholder cards display immediately while tire data loads, eliminating the flash of empty content. Respects `prefers-reduced-motion`.
- **Accessibility (a11y) improvements** — Skip-to-content link, `aria-label` attributes on all filter controls, search input, sort dropdown, and tooltip info buttons. `role="status"` and `aria-live="polite"` on no-results container. Screen-reader-only labels for dropdowns. Focus-visible outline styles for all interactive elements (stars, buttons, filters, links). `.screen-reader-text` utility class.
- **JavaScript unit tests** — New `tests/test-validation.js` with 83 tests covering `escapeHTML`, `sanitizeInput`, `validateNumeric`, `safeImageURL`, `safeLinkURL`, and `fuzzyMatch`. Runnable via `node tests/test-validation.js`.
- **Efficiency calculator AJAX endpoint** — Admin tire edit form now calls the canonical PHP `RTG_Database::calculate_efficiency()` via AJAX with debouncing, eliminating the duplicate JS formula.

### Changed
- **Consolidated URL validation** — Extracted shared `escapeHTML`, `safeImageURL`, `safeLinkURL`, and `safeReviewLinkURL` into `rtg-shared.js`. The compare page now delegates to this shared module instead of maintaining duplicate implementations with divergent domain lists.
- **Admin efficiency preview** — Replaced the 95-line duplicate JS efficiency formula in `admin-scripts.js` with a debounced AJAX call to the PHP source of truth. The formula now only exists in `RTG_Database::calculate_efficiency()`.
- **Plugin version** — Bumped to 1.14.0.

## [1.13.1] - 2026-02-19

### Changed
- **Simplified tire card rating display** — Removed redundant review count from the rating line. The average score now shows cleanly next to the stars (e.g. "5.0") while the review count appears only in the actionable "X reviews" button below.
- **Plugin version** — Bumped to 1.13.1.

## [1.13.0] - 2026-02-19

### Added
- **Affiliate click tracking** — Tracks when users click purchase, bundle, and review links using `navigator.sendBeacon()` for zero-latency, privacy-respecting analytics. New `wp_rtg_click_events` database table with server-side 5-second deduplication.
- **Search analytics** — Tracks user search queries, active filters, sort options, and result counts. New `wp_rtg_search_events` database table with 2-second client-side debounce and 3-second server-side deduplication.
- **Analytics admin page** — New admin page (Tire Guide > Analytics) with period selector (7/30/90 days), summary cards (total clicks, unique visitors, total searches), Chart.js line charts for clicks-over-time and search volume, ranked tables for most clicked tires, top search queries, zero-result searches (unmet demand), and most used filters.
- **Analytics data retention** — Configurable retention period (7–365 days, default 90) in Settings. Daily WP-Cron job automatically cleans up old events.

### Changed
- **Database schema version** — Bumped to v9 with migrations 8–9 for click events and search events tables.
- **Plugin version** — Bumped to 1.13.0.

## [1.12.1] - 2026-02-19

### Fixed
- **Dashboard bar charts not rendering** — Horizontal bar fill elements were invisible because `<span>` elements default to `display: inline`, which ignores width/height. Added `display: block` to `.rtg-bar-fill`.

### Changed
- **Plugin version** — Bumped to 1.12.1.

## [1.12.0] - 2026-02-19

### Added
- **Admin dashboard** — New default landing page when opening Tire Guide in the admin panel. Shows overview cards (total tires, average price, average efficiency score, total ratings), breakdowns by category/brand/size/efficiency grade with horizontal bar charts, key insights (price and weight ranges, affiliate link coverage), top rated and most reviewed tires, content health indicators (pending reviews, missing images, missing links with action buttons), and recently added tires.

### Changed
- **Plugin version** — Bumped to 1.12.0.

## [1.11.1] - 2026-02-19

### Added
- **Configurable affiliate domains** — New "Affiliate Link Domains" section on the Settings page. Admins can add or remove affiliate network domains (one per line) to control how links are classified on the Affiliate Links dashboard. Protocols and www prefixes are stripped automatically.

### Changed
- **Plugin version** — Bumped to 1.11.1.

## [1.11.0] - 2026-02-19

### Added
- **Affiliate Links dashboard** — New admin page (Tire Guide > Affiliate Links) providing a centralized view of all tire purchase, bundle, and review links. Summary stats show counts for affiliate, regular, and missing links at a glance.
- **Link classification** — Automatically detects whether a purchase link is an affiliate link (via known affiliate network domains like CJ, ShareASale, AvantLink, Impact, etc.) or a regular direct retailer link, displayed as color-coded badges.
- **Filter tabs** — Quick-filter buttons to show only tires with affiliate links, regular links, missing links, missing bundle links, or missing review links — making it easy to find which tires still need affiliate links added.
- **Inline link editing** — Edit all three link fields (purchase, bundle, review) directly in the table row with AJAX save — no page reload required.
- **Search** — Search the affiliate links table by brand, model, or tire ID.

### Changed
- **Plugin version** — Bumped to 1.11.0.

## [1.10.0] - 2026-02-18

### Added
- **Favorites / Wishlist system** — Logged-in users can now save tires to a personal favorites list by clicking the heart icon on each tire card. New `wp_rtg_favorites` database table stores user preferences. Includes a "My Favorites" filter toggle to show only favorited tires, with a badge count on the toggle. Optimistic UI updates for instant feedback.
- **Smart No Results state** — The empty state when no tires match filters now shows an illustrated view with specific, actionable suggestions (e.g., "Remove size filter", "Show all brands", "Clear all filters") based on which filters are active.
- **Enhanced image lazy loading** — Added `IntersectionObserver`-based lazy loading with `data-src` pattern and shimmer placeholder animation. Images fade in smoothly on load. Falls back to native `loading="lazy"` when IntersectionObserver is unavailable.
- **Browser back/forward for filters** — Filter changes now push to browser history via `pushState`, enabling back/forward navigation through filter states. Added `popstate` listener to restore filters from URL.
- **Favorites filter in URL** — The `?favorites=1` URL parameter persists the favorites filter for shareable links.

### Changed
- **Card enter/exit animations** — Cards now use a slide-up (`translateY`) + scale animation with cubic-bezier easing and staggered delays (40ms per card on desktop) for a smoother cascade effect on filter changes.
- **Database schema version** — Bumped to v7 with migration for the new favorites table.
- **Plugin version** — Bumped to 1.10.0.

## [1.9.4.2] - 2026-02-18

### Changed
- **Larger star ratings** — Bumped SVG star sizes across all contexts for better visibility: default 20→22px, interactive 24→26px, review modal 36→40px, mini stars 16→18px, mobile modal 28→32px.

## [1.9.4] - 2026-02-18

### Changed
- **Mobile-first card body spacing** — Rewrote tire card body spacing to use a single `gap` as the source of truth instead of mixing gap with individual child margins. Base styles target mobile, with a `min-width: 601px` breakpoint scaling up padding for desktop.
- **Brand/model tightened** — Brand name and model title now sit closer together as a visual unit, with more breathing room around the star rating area below.
- **Review actions spacing** — Increased separation between the star row and the "Write a Review" / "View Reviews" action links so the stars feel like they float in their own space.

### Fixed
- **User-rated stars overriding average display** — The `.user-rated` CSS class was forcing `star-fill` opacity to 1 on all stars up to the user's personal rating, regardless of the actual average. A 3/5 average could show 5 full green stars if the user had rated 5. The user-rated styling now only colorizes stars that are already filled based on the average.

## [1.9.0] - 2026-02-18

### Added
- **SVG star ratings with half-star support** — Replaced the old Unicode star characters with layered SVG stars (background outline, full fill, and half fill via `clip-path`). Ratings round to the nearest 0.5 for accurate half-star display.
- **Star color settings** — New admin settings for Star Filled, Star User-Rated, and Star Empty colors, output as CSS custom properties (`--rtg-star-filled`, `--rtg-star-user`, `--rtg-star-empty`).

### Changed
- **Rebrand to orange/gold accent** — Primary accent shifted from green to orange/gold (`#fba919`) with an optimized dark navy palette. Updated all CSS custom property defaults, admin color picker defaults, and the comparison page theme.

## [1.8.4] - 2026-02-18

### Fixed
- **Shared page links reverting to page 1** — Fixed pagination links losing the current page when sharing. Renamed the page URL parameter from `page` to `pg` to avoid a conflict with a reserved WordPress query variable.

## [1.8.3] - 2026-02-18

### Added
- **Highlighted user reviews** — Reviews with text now display a prominent badge and CTA styling. Rating-only entries (no review body) are also included in the reviews drawer with a blank body.

### Changed
- **Write-review button styling** — Aligned the write-review button with matching pill styling for consistency.

## [1.8.2] - 2026-02-17

### Fixed
- **Server-side pagination: Clear All filters** — The "Clear All" button and individual filter chip dismiss actions now correctly fetch fresh data from the server when server-side pagination is enabled. Previously they called the client-side render path, which operates on an empty dataset in server-side mode, resulting in no tires being displayed.

## [1.8.1] - 2026-02-17

### Changed
- **Grade scale simplified** — Removed the "E" grade from the efficiency scale. Grades now use A / B / C / D / F only, across the PHP calculation engine, admin preview calculator, frontend tire cards, and comparison page.

## [1.8.0] - 2026-02-17

### Added
- **Tire duplication** — "Duplicate" action on each tire row in the admin list. Creates a copy with a new auto-generated tire ID and opens the edit form immediately.
- **Recalculate Grades button** — One-click bulk recalculation of efficiency scores for all tires from the admin tire list header. Useful after algorithm changes or CSV imports.
- **Admin list filters** — Brand, Size, and Category dropdown filters alongside the existing search bar on the All Tires page.
- **Load Index column** — Sortable Load Index column added to the admin tire list table.
- **Tag suggestions** — Previously-used tags appear as clickable chips on the tire edit form for quick reuse. Clicking toggles the tag in or out of the comma-separated tags field.
- **Size-to-diameter mapping** — New "Size → Tire Diameter" setting in the Dropdown Options section. Maps each tire size to its overall diameter (e.g. `275/65R20 = 34.1"`). Selecting a size on the tire edit form auto-fills the diameter field.
- **Image URL prefix** — The CDN Image Prefix from settings is now shown as a static label before the image filename input on the tire edit form. Only the filename portion needs to be entered; the full URL is assembled on save.
- **UTQG "None" fallback** — Tires with no UTQG value now display "None" on frontend cards and the comparison page instead of a blank or dash.

### Changed
- **Sort order field hidden** — The sort order input on the tire edit form is now a hidden field (preserving its value) to reduce form clutter.
- **Search matches tags** — Admin tire search now also matches against the tags field.

## [1.7.8] - 2026-02-17

### Added
- "User Reviews Page Slug" setting in admin settings to configure the shortcode page slug.
- "Back to Tire Guide" link on the user reviews page.
- Tire names on the user reviews page now link to the tire guide filtered to that tire.

## [1.7.7] - 2026-02-17

### Added
- `[rivian_user_reviews]` shortcode — displays all reviews by a user (via `?reviewer=ID` URL param).
- Reviewer names in review cards now link to the user's reviews page.
- New `user_reviews_slug` setting (defaults to `user-reviews`).

## [1.7.6] - 2026-02-17

### Added
- "Officially Reviewed" toggle filter to show only tires with an official RivianTrackr review.

## [1.7.5] - 2026-02-17

### Changed
- Renamed review buttons to "Read/Watch Official Review" to distinguish from community reviews.

### Fixed
- Fixed escaped slashes in review text caused by WordPress magic quotes.

## [1.7.4] - 2026-02-17

### Changed
- **Ratings admin "Review" column** — Now shows a "View Reviews" link that navigates to the Reviews page filtered by that tire, instead of displaying inline review text.
- **Re-moderation on edit** — Editing a review (title or body) resets it to pending status for non-admin users, ensuring all changes are re-approved. Title-only reviews are now also subject to moderation.

## [1.7.3] - 2026-02-17

### Added
- **Toast notifications** — Users now see confirmation feedback after submitting a review: "Your rating has been saved!", "Your review has been updated.", or "Thanks! Your review has been submitted and is pending approval." depending on context.
- **Admin pending-reviews notice** — A dismissible info banner appears on the WordPress dashboard and Tire Guide admin pages when reviews are awaiting moderation, with a direct link to the pending queue.
- **Improved reviews drawer empty state** — When a tire has no reviews, the drawer shows a friendly heading, icon, and "Write a Review" CTA button (for logged-in users) instead of a plain text message.

## [1.7.2] - 2026-02-17

### Changed
- Updated logged-out prompt to "Log in or sign up to review tires" with separate login and registration links.

## [1.7.1] - 2026-02-17

### Added
- **Review moderation** — New admin "Reviews" page (Tire Guide > Reviews) with status tabs (All, Pending, Approved, Rejected). Pending review count displays as a badge in the admin menu. Admins can approve, reject, or delete reviews. Only approved reviews are visible on the frontend and in Schema.org structured data.
- **Admin auto-approve** — Reviews submitted by users with `manage_options` capability are automatically approved; all other reviews default to "pending" status.
- **Database migration 6** — Adds `review_status` column (VARCHAR 20, default `'approved'`) to the ratings table. Existing reviews are grandfathered as approved.

### Fixed
- **Review date showing original rating date** — The reviews drawer and Schema.org markup now use the `updated_at` timestamp instead of `created_at`, so editing a review shows the correct date (e.g. "Today") instead of when the original star rating was submitted.
- **Review date timezone** — Review relative dates ("Today", "Yesterday") now use the WordPress timezone setting instead of UTC or the browser's local time.

## [1.7.0] - 2026-02-17

### Added
- **User text reviews** — Users can now write optional text reviews alongside star ratings. Clicking a star or the "Write a Review" button opens a review modal with star selector, optional title (200 char limit), and review body (5,000 char limit). Existing reviews can be edited from the same modal.
- **Reviews drawer** — Each tire card shows a review count link (e.g. "3 reviews") that opens a slide-in drawer displaying all written reviews with author name, star rating, relative date, title, and body text. Paginated at 10 reviews per page.
- **Review AJAX endpoints** — New `get_tire_reviews` public endpoint for fetching paginated reviews. Extended `submit_tire_rating` to accept `review_title` and `review_text` fields with length validation and sanitization.
- **Schema.org Review markup** — Individual `Review` objects (up to 5 per tire) are now included in the JSON-LD structured data alongside `AggregateRating` for rich snippet eligibility.
- **Admin review column** — The Ratings & Reviews admin table now displays review title and truncated review text with hover tooltip for each entry.
- **Database migration 5** — Adds `review_title` (VARCHAR 200) and `review_text` (TEXT) columns to the ratings table for existing installations.

## [1.6.1] - 2026-02-17

### Changed
- **Sort options refined** — Removed low-value sort options (Brand A→Z, Brand Z→A, Weight Heavy→Light). Added "Newest Added" (sorts by date added, descending) and "Most Reviewed" (sorts by number of ratings, with average rating as tiebreaker). Default remains Rating: High → Low.

## [1.6.0] - 2026-02-17

### Added
- **Review link on tire cards** — Each tire can now link to an article or video review via a new `review_link` field. The button adapts based on the URL: YouTube/TikTok links show "Watch Review" with a play icon, while article links (RivianTrackr, Instagram) show "Read Review" with a newspaper icon. Styled with a purple CTA button.
- **Review link in admin** — New "Review Link" input in the Pricing & Links section of the tire edit form, with description text guiding accepted platforms.
- **Review link in CSV import/export** — The `review_link` column is included in CSV exports and recognized during imports.
- **Review link on compare page** — The "Where to Buy" section now includes the review link alongside existing View Tire and Bundle buttons.
- **Review link URL validation** — Frontend validates review links against an allowlist of domains: riviantrackr.com, YouTube, TikTok, and Instagram.
- **Database migration 4** — Adds the `review_link` column to the tires table for existing installations.

## [1.5.2] - 2026-02-17

### Changed
- **Monospace font for tire spec values** — Tire specification values (size, price, weight, tread depth, load index, etc.) now render in a monospace font on both the main tire card view and the comparison page for improved readability of numeric data.

## [1.5.0] - 2026-02-16

### Added
- **Open Graph & Twitter Card meta tags** — Sharing a `?tire=` link on social platforms now shows a rich preview with the tire name, description, price, and image. Default meta tags are output on the catalog page when no tire is specified.
- **Native share sheet** — The share button now uses `navigator.share()` on supported devices (mobile), opening the native share sheet with the tire name and URL. Falls back to clipboard copy on desktop. Icon updated from link to share-nodes.

## [1.4.8] - 2026-02-16

### Changed
- **Tire deep-link shows single tire** — Opening a `?tire=` link now isolates that tire as the only visible card, hiding filters, sort bar, and pagination. A "View all tires" back link appears above the card to return to the full catalog.

## [1.4.7] - 2026-02-16

### Fixed
- **Tire deep-link not activating** — Fixed shareable tire links not scrolling or highlighting on page load. The async render pipeline (throttled RAF + rating Promises) meant cards weren't in the DOM when the deep-link handler ran. Now polls for the card element reliably.

## [1.4.6] - 2026-02-16

### Added
- **Shareable tire links** — Each tire card now has a link button (visible on hover) that copies a direct URL to that tire. Opening the link scrolls to the tire and highlights it with a brief accent glow, even navigating to the correct page. If the tire is hidden by active filters, filters are automatically cleared.

## [1.4.5] - 2026-02-16

### Removed
- **Back-to-top button** — Removed the fixed-position scroll button as it interfered with page interactions.

## [1.4.4] - 2026-02-16

### Added
- **Active filter chips** — Selected filters now display as dismissible chips below the filter bar for quick visibility and one-click removal.

## [1.4.3] - 2026-02-16

### Changed
- **Compare checkbox repositioned** — Moved the compare checkbox to the top-right corner of the tire card image for easier access.

## [1.4.2] - 2026-02-16

### Fixed
- **Compare bar button text wrapping** — Fixed compare bar buttons wrapping their text on desktop viewports.

## [1.4.1] - 2026-02-16

### Fixed
- **Mobile filter button width** — Fixed the mobile filter toggle button stretching too wide.
- **Compare text wrapping** — Fixed text overflow in compare bar on smaller screens.

## [1.4.0] - 2026-02-16

### Changed
- **Main tire guide redesign** — Revamped the entire frontend to match the compare page design language. Filter section now uses a card container with a section header bar (icon + title), bordered cards with hover accent highlight, and consistent typography/spacing across all elements.
- **Tire cards refactored** — Cards use semantic CSS classes (`tire-card-*`) instead of inline JavaScript styles. New structure separates image, body content, and action areas. Brand name is now accent-colored uppercase (matching compare page's `.cmp-tire-brand` pattern). Spec rows use muted label + primary value styling with subtle dividers.
- **Efficiency badge** — Updated to match the compare page's two-part badge pattern (`tire-card-eff-grade` + `tire-card-eff-score`) with consistent sizing.
- **Tags** — Restyled to match compare page `.cmp-tag` sizing (11px, 3px 8px padding, 4px radius).
- **Buttons unified** — All CTA buttons now use class-based styling: primary (accent green), bundle (blue), disabled (bordered muted), and compare (ghost bordered). All share `.15s` transitions.
- **Filter controls refined** — Select dropdowns and toggle switches now have border + hover accent highlight. Slider wrappers use bordered containers. Clear All button uses dashed border ghost style.
- **Compare bar** — Refined with `backdrop-filter: blur`, card background, border, and class-based button variants (go/clear).
- **Mobile toggle button** — Restyled from solid accent fill to bordered card style with icon.

### Added
- **Print styles for tire cards** — Cards hide actions and use light-friendly colors when printed.
- **Reduced motion support** — Tire card transitions respect `prefers-reduced-motion`.
- **Hover state on tire cards** — Subtle border color shift to accent on hover.

## [1.3.0] - 2026-02-15

### Added
- **Server-side pagination** — New optional mode (Settings > Server-side Pagination) that fetches tires via AJAX instead of embedding the full dataset in the page. Includes `rtg_get_tires` and `rtg_get_filter_options` AJAX endpoints with full server-side filtering, sorting, and pagination. Recommended for catalogs with 200+ tires.
- **Database migration versioning** — Schema changes are now tracked via a numbered migration system (`rtg_db_version` option). Migrations run automatically on plugin update via `plugins_loaded`. New migrations can be added to `RTG_Activator` with a simple method pattern.
- **Production asset minification** — New `build.sh` script generates `.min.css` and `.min.js` files using terser/csso (falls back to basic minification). Frontend and admin classes automatically serve minified assets when available and `SCRIPT_DEBUG` is off.
- **PHPUnit test suite** — Full test scaffolding with `phpunit.xml`, WordPress test bootstrap, and test cases covering database CRUD, cascade deletes, cache invalidation, efficiency calculation, filtered pagination, ratings upsert, migration versioning, and admin menu registration.
- **Tags index** — Added database index on `tags(100)` column for faster server-side tag filtering (applied via migration 2).

## [1.2.0] - 2026-02-15

### Added
- **CSV import and export** — New admin page (Tire Guide > Import / Export) for bulk importing tires from CSV and exporting the full catalog as a CSV backup. Supports duplicate handling (skip or update), auto-generated tire IDs, and auto-calculated efficiency scores.
- **Transient caching for tire queries** — `get_all_tires()` results are now cached in a WordPress transient (1 hour TTL) and automatically invalidated on insert, update, or delete operations.
- **Accessibility improvements** — Star ratings now have ARIA `role`, `aria-label`, `aria-checked` attributes and full keyboard navigation (arrow keys, Enter/Space). Filter toggle and wheel drawer buttons have `aria-expanded`/`aria-controls`. Compare checkboxes include descriptive `aria-label`. Tire count is an `aria-live` region. Image modal supports Escape key to close and has `role="dialog"`.

### Fixed
- **Orphaned ratings on tire delete** — Deleting a tire (single or bulk) now also removes its associated ratings from the database, preventing orphaned records.

## [1.1.4] - 2026-02-15

### Fixed
- **Diameter dropdown not persisting on save** — WordPress magic quotes were escaping the `"` character in diameter values (e.g. `20"` became `20\"`), causing a mismatch on reload. Added `wp_unslash()` to all POST data in the tire save handler.
- **Dropdown values not matching stored data** — If a tire's stored value for any dropdown field (brand, size, diameter, category, load range, speed rating) wasn't in the managed options list, the field would silently reset to empty on save. The current stored value is now always included as a dropdown option.

## [1.1.3] - 2026-02-15

### Fixed
- **Efficiency grade A color** — Reverted grade A badge back to fixed `#5ec095` green, independent of the theme accent color.

## [1.1.2] - 2026-02-15

### Changed
- **Accent colors fully themeable** — All hardcoded `#5ec095` / `rgba(94, 192, 149, …)` references replaced with `var(--rtg-accent)` / `rtgColor('accent')` so Primary Accent and Accent Hover are fully controllable from admin settings.

### Fixed
- **Diameter missing inch symbol** — Diameter values stored without a trailing `"` (e.g. `33`, `32.8`) now display as `33"` and `32.8"` on tire cards and the comparison page.

## [1.1.1] - 2026-02-15

### Fixed
- **Star ratings showing 0** — Frontend was not passing the nonce with `get_tire_ratings` AJAX requests, causing the new CSRF check to reject rating fetches for logged-in users.
- **Broken images on comparison page** — `safeImageURL()` was hardcoding a CDN optimization prefix on every validated URL. Now returns the validated URL directly.

## [1.1.0] - 2026-02-15

### Added
- **Schema.org structured data** — Automatic JSON-LD output (Product + AggregateRating + ItemList) on pages using the `[rivian_tire_guide]` shortcode for SEO rich snippets.
- **Rate limiting on rating submissions** — Transient-based limiter (10 submissions per 60-second window per user) to prevent abuse.
- **Tire existence validation** — Rating submissions now verify the tire exists in the database before saving.
- **Nonce verification on `get_tire_ratings`** — Logged-in users' read requests are now CSRF-protected.
- **Content-Security-Policy headers** — The standalone comparison page now sends `CSP`, `X-Content-Type-Options`, `X-Frame-Options`, and `Referrer-Policy` headers.
- **URL validation in comparison page** — Image, affiliate, and bundle link URLs are now validated through `safeImageURL()` and `safeLinkURL()` domain allowlists with `escapeHTML()` applied to all attributes.
- **CSS injection prevention** — Theme color values are re-validated with `sanitize_hex_color()` at render time in both the frontend shortcode and comparison template.
- **README.md** — Comprehensive project documentation.
- **CHANGELOG.md** — Version history.
- **SECURITY.md** — Security policy and responsible disclosure instructions.

### Fixed
- **XSS in comparison page** — Link and image URLs were inserted into HTML attributes without escaping. All dynamic values now pass through `escapeHTML()` and domain-validated URL functions.
- **Uninstall cleanup** — Added `delete_option('rtg_dropdown_options')` to the uninstall handler to remove all plugin data on deletion.
- **Bundle link hover state** — Fixed `onmouseout` color not changing (was identical to `onmouseover`).

### Security
- 8 security improvements addressing CSRF, XSS, rate limiting, CSP, CSS injection, and data validation. See the individual items above for details.

## [1.0.5] - Initial tracked release

### Features
- Interactive tire catalog with filtering, sorting, and pagination
- Smart search with fuzzy matching and type-ahead suggestions
- Side-by-side tire comparison page
- User star ratings (1-5) with AJAX submission
- Efficiency scoring algorithm (A-F grade, 0-100 score)
- Full admin CRUD for tire management
- Ratings management dashboard
- Customizable theme colors (11 CSS custom properties)
- Configurable dropdown options for tire fields
- Shortcode: `[rivian_tire_guide]`
- Custom rewrite rules for comparison page
- Proper activation/deactivation/uninstall hooks
