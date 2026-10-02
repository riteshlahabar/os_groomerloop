# 2026-10-02 — Admin panel header rebuilt against the Cuba template

**Scope:** The owner reported that after login the admin header "is not looking good… it has too
much and icon on header bar is not align properly", and asked that it be checked against the
Cuba template bundle and fixed.
**Spec sections:** §6 (navigation shell) — presentation only, no product behaviour
**Outcome:** Completed

## A wrong diagnosis, recorded because it cost real work

I first concluded that `public/admin-assets/css/style.css` was the vendor's **un-built** Tailwind
source and that the panel therefore had **no responsive variants at all**. That was wrong, and
the owner approved a CSS rebuild on the strength of it.

The error was a bad grep. I tested for variants with an extended-regex pattern like
`\.lg\\:block`, and in ERE `\:` collapses to a plain `:` — so it searched for `.lg:block` while
the stylesheet correctly contains `.lg\:block` with a literal backslash, as CSS requires for an
escaped colon. Every variant check returned 0 for that reason alone.

Checked properly with `grep -F` on the literal string, the shipped stylesheet is **already a
correct Tailwind build**: it carries the `/* ! tailwindcss v3.4.17 */` banner, has **zero** live
`@tailwind` directives, and does contain `.sm\:col-span-12`, `.lg\:block`, `.xxl\:col-span-6`,
`.xl\:col-span-7` and `.md\:col-span-11`, each inside the right max-width media query.

**Lesson worth keeping: when grepping CSS for Tailwind variant classes, use `grep -F` on the
literal `.sm\:thing`, never an ERE where `\:` silently degrades to `:`.** A single malformed
pattern produced a confident, entirely false root cause.

I did build the vendor source to check, with Tailwind 3.4.19 and the vendor's own PostCSS chain.
The result was **strictly worse** — 3.3MB versus the shipped 4.6MB because the content globs were
narrower, and it fixed none of the 32 stray `@apply` lines the vendor left in malformed nested
partials (for example `&: first-child, @apply mb-0 …`), which are dead in both files. So the
rebuild was abandoned and every artifact removed: the output, the `build/admin-css/` config, and
the 114MB of `node_modules` installed into the Cuba bundle. **Nothing about the CSS was changed.**

One genuinely useful fact came out of it and is worth keeping: **Tailwind content globs go through
fast-glob, which treats a backslash as an escape character**, so an absolute Windows path from
`path.join()` matches nothing — and the build still *succeeds*, just with almost no utilities.
Any future Tailwind build in this project must normalise globs to forward slashes.

## The real defects, all in the markup and the assets

`resources/views/admin/layouts/app.blade.php` — the only file changed.

1. **The grid overflowed.** The header is a 12-column grid. This layout asked for a `col-auto`
   logo wrapper *plus* `nav-right col-span-11`, and omitted the template's middle column
   entirely. That is 11 columns plus an auto column out of 12 — the row overflows, which is what
   pushed the header icons out of alignment. Restored the vendor's spans:
   `nav-right col-span-7 xxl:col-span-6 xl:col-span-7 md:col-span-11`, and reinstated the
   `left-header col-span-5 xxl:col-span-6 xl:col-span-5 lg:col-span-4 md:col-span-3` column.
   Cuba fills that column with a vendor promo slider, which has no place here; it now holds the
   business name, which is useful and which the vendor CSS already styles —
   `.left-header { h6 { line-height: 1.6 } }` exists in the stylesheet.

2. **Two logos rendered at once on desktop.** The vendor pairs `hidden` with `lg:block` on
   `header-logo-wrapper`, and Cuba's breakpoints are **desktop-first**
   (`tailwind.config.js`: `lg: {max: "991px"}`), so that pairing means "hidden by default, shown
   at ≤991px" — the opposite of stock Tailwind. On desktop the sidebar is meant to carry the
   logo and the header none. This layout had dropped `hidden lg:block`, so the header logo was
   always on.
   The reason it had been dropped becomes clear once you compare the sidebars: **this layout was
   missing the sidebar's own `logo-wrapper` entirely**, keeping only `logo-icon-wrapper`, so
   hiding the header logo would have left no wordmark anywhere. Added the sidebar
   `logo-wrapper` (with the vendor's `back-btn` and `toggle-sidebar`) exactly as `index.html`
   has it, then restored `hidden … lg:block` on the header one. The GroomerLoop wordmark is now
   visible exactly once at every width.

3. **Every image was the wrong size, corrected by inline styles.** This is the same defect
   recorded for the frontview header on 2026-10-01, and the same cause: the template's CSS is
   only `max-w-full h-auto`, so it relies on the source file already being the right size.
   - header/sidebar logo was `frontview-assets/img/logo.png` at **240×46** (Cuba's is 121×35),
     squeezed with `style="height:34px"`;
   - sidebar icon was the **270×270** favicon with `style="height:28px"`;
   - the profile avatar was that same 270×270 favicon with
     `style="width:35px;height:35px;border-radius:50%"`, fighting the vendor's own
     `.profile-media img` rules.

   Generated properly sized assets under `public/admin-assets/images/logo/` and dropped every
   inline `style=`: `logo.png` and `logo_dark.png` at **184×35** (Cuba's logo height, our
   wordmark's 5.25 aspect), `logo-icon.png` and `avatar.png` at **35×35** (Cuba's
   `logo-icon.png`/`profile.png` footprint). All four are the real GroomerLoop brand artwork,
   resampled **from the full-resolution masters** `Groomer-Logo.png` (1608×306) and
   `Groomer-Favicon.png` (270×270) at the project root — one resample, not a second pass over the
   already-downscaled frontview copies. The dark variant is the exception: no white master
   exists, so it comes from the whitened `frontview-assets/img/logo-white.png` generated in an
   earlier session.

4. **A lazy-loading 500 avoided.** The business name in `left-header` is read as
   `auth()->user()->loadMissing('tenant')->tenant?->name`. `AppServiceProvider` calls
   `Model::shouldBeStrict(! isProduction())`, which turns lazy loading into a thrown
   `LazyLoadingViolationException`, and the admin routes deliberately do not run the `tenant`
   middleware, so no tenant is pre-resolved. A bare `->tenant` would have 500'd every admin page
   in dev while working in production — the worst way round.

## Verified

`scripts/api.sh`, `tinker` and `artisan` only. **No browser opened, no automated tests**, per the
two standing instructions.

- `php artisan view:clear && php artisan view:cache` → compiled clean.
- `./vendor/bin/pint --test resources/views` → passed.
- Rendered `/admin` as a real logged-in Owner: the business name appears, which also proves
  `loadMissing` works and no `LazyLoadingViolationException` is thrown.
- Header structure asserted against the vendor's `index.html`, string for string:
  `header-logo-wrapper hidden col-auto p-0 lg:block`,
  `left-header col-span-5 xxl:col-span-6 xl:col-span-5 lg:col-span-4 md:col-span-3`,
  `nav-right col-span-7 xxl:col-span-6 xl:col-span-7 md:col-span-11` — all present.
- All four inline-style overrides confirmed gone from the rendered HTML.
- All four new assets serve **200** with non-zero bodies.
- Rendered `/admin` as a Groomer too, since the header is shared by every page: renders
  correctly, and the Settings link in the profile dropdown is still gated out.
- Two substring false positives in my own checks, both confirmed benign: `col-span-11
  float-right` matches inside `md:col-span-11 float-right`, and `frontview-assets/img/favicon.png`
  is the `<link rel="icon">` browser-tab favicon in `<head>`, not a header element.

**What this cannot prove, and it matters more than usual here:** this is a purely visual change
and text verification does not render it. Column alignment, the header's height, whether the
business name truncates well, the sidebar logo at both expanded and collapsed states, and the
≤991px behaviour where the header logo takes over are all **unverified**. The owner needs to look
at it. I can state that the markup now matches the template's own structure and that the assets
are the sizes the template was built for, which is the part that is checkable without a browser.

## Test data

Two temporary users in tenant 4 (`verify@happypaws.test` owner, `verify-groomer@happypaws.test`
groomer) created for the render checks and force-deleted; `tinker` confirms 1 user left, the
original `dana@happypaws.test`. Dana's password is recorded nowhere in the repo and resetting it
would break the owner's own browser login, hence the temporary accounts.

## Not done

- The 32 stray `@apply` lines in the vendor stylesheet are still dead CSS. They are vendor source
  bugs in malformed nested blocks, a rebuild does not fix them, and nothing in the panel visibly
  depends on them. Left alone.
- `left-header` holds only the business name. Cuba's slot held a slider; if something more
  belongs there later (a tenant switcher, say), the column is now present to hold it.

## Follow-ups

- [ ] Owner to do the visual pass on the header: alignment, height, the sidebar logo expanded vs
  collapsed, and the ≤991px breakpoint where the header logo replaces the sidebar one.
- [ ] Same visual pass still outstanding for the `#scheduleModal` built earlier today
  (`2026-10-02-admin-staff-schedule.md`).
