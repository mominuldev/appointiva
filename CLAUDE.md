# CLAUDE.md

Guidance for working on Appointiva (the Free plugin) in this directory.

## What's built (Phase 1 — Free plugin core)

Composer PSR-4 autoloading (`Appointiva\` → `src/`), no `@wordpress/scripts` — admin app builds
via a hand-rolled Vite config at the plugin root.

- **Bootstrap**: `appointiva.php` → `Appointiva\Plugin` (service container in `src/Container.php`).
  Services are registered in `Plugin::register_services()` and wired lazily. Get any service via
  `appointiva()->get('service.id')` (see the id list in `Plugin::register_services()`).
- **DB**: 9 custom tables (`wp_appointiva_*`), created/upgraded by `Database\Installer`. Schema
  version tracked in the `appointiva_db_version` option; bump `APPOINTIVA_DB_VERSION` in
  `appointiva.php` and extend `Installer::create_tables()` for migrations (dbDelta is idempotent).
- **Settings**: stored in the custom `appointiva_settings` table via `Database\Settings_Repository`
  (key/value + autoload flag), *not* `wp_options` — this is intentional, per the project spec. The
  one exception is `appointiva_delete_data_on_uninstall`, which lives in `wp_options` directly
  because `uninstall.php` runs standalone and must read it before anything else.
- **Booking domain**: `Booking\Status` is a PHP 8.1 backed enum with a filterable transition map
  (`appointiva_booking_status_transitions`) — this is what keeps the state machine open to a future
  inquiry/offer flow (`OFFER_SENT`, `DECLINED`, `EXPIRED` statuses already exist but are never
  assigned by Free plugin code paths). `Booking\Availability` computes slots; `Booking\Booking_Manager`
  orchestrates creation/status changes and fires the `appointiva_before_booking_created` /
  `appointiva_after_booking_created` / `appointiva_booking_status_changed` actions Pro hooks into.
- **Notifications**: `Notification_Manager` dispatches through channels registered via the
  `appointiva_registered_notification_channels` filter (Free registers only `email`; Pro adds
  `whatsapp` the same way). `Mailer` handles locale-safe sending (`switch_to_locale()` +
  forced textdomain reload — WP does not do this automatically for plugin text domains).
- **Payments**: `Payments\Gateway_Interface` + `Payment_Manager` (registered via
  `appointiva_payment_gateways` filter). Stripe and PayPal gateways call the vendor REST APIs
  directly over `wp_remote_*` — no bundled SDKs, kept deliberately lean. `create_payment()` takes
  optional `$amount`/`$type` params (default to the booking's full price / `'full', ` unchanged
  behavior when omitted) — added in Phase 3 so Pro's deposits add-on can charge less than full price
  through the same gateway code instead of a parallel implementation; nothing in Free calls this yet
  itself (the payments system is still otherwise unwired into the booking flow, see the Phase 1 note
  below).
- **Google Calendar**: `Integrations\Google_Calendar`, one-way push only, OAuth token exchange via
  `wp_remote_post`, no `google/apiclient` dependency. After a successful push, fires
  `appointiva_google_calendar_event_pushed( $booking, $event_id, $calendar_id )` — added in Phase 3,
  fire-and-forget, nothing in Free listens to it itself; Pro's two-way sync add-on uses it to track
  which Google event belongs to which booking (e.g. to delete it if the booking is later cancelled,
  which this class still never does on its own).
- **Booking confirmation email**: `templates/emails/booking-confirmation.php` fires
  `do_action( 'appointiva_email_confirmation_body', $booking, $customer )` after the standard details
  table — added ahead of time for Pro's deposit/payment summary and self-service dashboard link, and
  as of Phase 3 actually consumed: Pro's dashboard add-on hooks this to print a pre-authenticated
  "Manage this booking" link. Free itself renders nothing extra here on its own.
- **Frontend widget**: `assets/js/widget.js` is dependency-free vanilla JS (no build step) that
  hydrates any `[data-appointiva-widget]` container. Shared by the `[appointiva_booking]` shortcode
  and the `appointiva/booking-widget` Gutenberg block (`blocks/booking-widget/`, dynamic render via
  `render.php`, block.json `render` field — no PHP render_callback needed).
- **Admin app**: React + Tailwind, built with Vite (`npm install && npm run build` → outputs to
  `assets/dist/admin/`). Cross-bundle slot-fill registry lives on `window.Appointiva.admin`
  (`admin-app/src/slot-fill/registry.js`) — this is how Pro's separately-built JS bundle injects
  settings tabs / dashboard widgets / nav items without importing anything from this app.
  **Build format is IIFE (`vite.config.js` uses `build.lib` with `formats: ['iife']`), not Vite's
  default ES module output.** WordPress enqueues this bundle as a plain classic `<script src>` (no
  `type="module"`); ES-module output's top-level `const`/`let` land in wp-admin's *shared* global
  script scope when loaded that way, and WILL eventually collide with `window.wp` or some other
  plugin's global the moment any bundled dependency's minifier happens to shorten an internal
  variable to a colliding name (this actually happened — an internal `lucide-react` context
  variable minified down to `wp` and crashed the whole page with "Identifier 'wp' has already been
  declared"). IIFE avoids the entire class of bug by never touching the global scope. Two other
  non-obvious things this build config depends on: `cssMinify: false` (Vite's CSS minifier
  corrupts Tailwind's `.appointiva-admin` scoping prefix — see the `important` note above) and an
  explicit `define: { 'process.env.NODE_ENV': ... }` (library-mode builds skip Vite's automatic
  NODE_ENV replacement, leaving a literal `process.env` reference that throws `ReferenceError:
  process is not defined` in the browser). `npm run build` also renames Vite's default
  `style.css` output to `admin.css` afterward — `build.lib.cssFileName` isn't supported in the
  installed Vite version. If you ever touch `vite.config.js`, re-verify by loading the built
  `admin.js`/`admin.css` in a page that already has `window.wp` defined before the script runs —
  that's the condition that exposed the original bug and a normal same-page dev check won't catch it.
- **GDPR**: `Privacy\GDPR` registers WP core's personal-data exporter/eraser. Uninstall keeps all
  data by default (`uninstall.php`), destructive delete is opt-in via Settings.

## Prefix conventions (must stay consistent — see root CLAUDE.md Guideline 5 notes)

`APPOINTIVA_` constants, `appointiva_` hooks/options/table-names/settings-keys, `Appointiva\`
namespace, `appointiva/v1` REST namespace, `appointiva-*` script/style handles and CSS classes,
`window.Appointiva` JS global, `--appointiva-*` CSS custom properties.

## REST namespaces

- `appointiva/v1/services`, `/slots`, `/bookings` (POST) — **public**, `__return_true`, no nonce
  by design (anonymous booking widget). Rate-limiting/spam protection is a honeypot field only
  today; revisit if abuse shows up. `/services` accepts an optional `?lang=` param and passes results
  through `apply_filters('appointiva_public_services', $rows, $locale)` before returning them — added
  in Phase 3 so Pro's translation add-on can localize name/description; unused by Free itself, and the
  widget's own `assets/js/widget.js` now sends its already-known `cfg.locale` as `?lang=` on this
  request (harmless when nothing on the backend hooks the filter).
- `appointiva/v1/admin/*` — **authenticated**, `manage_options` + core cookie/nonce auth
  (`X-WP-Nonce` header, generated via `wp_create_nonce('wp_rest')` and passed to the admin app via
  `AppointivaAdminConfig`).

## Build/dev commands

```bash
composer install          # PHP autoloader + PHPCS/WPCS (dev)
npm install && npm run build   # admin app → assets/dist/admin/
npm run dev                # admin app watch mode
```

No build step is needed for the frontend widget (`assets/js/widget.js`, `assets/css/widget.css`)
or the block editor script (`blocks/booking-widget/index.js`) — both are hand-written vanilla
JS/browser-safe code, intentionally free of a build pipeline.

## Status against the project spec

Phase 1 (Free plugin core, WordPress.org-ready) is scaffolded and smoke-tested: DB installs
cleanly, REST booking flow works end-to-end (availability → booking → customer → email
notification), admin React app builds and mounts, Gutenberg block renders dynamically. **Not yet
done**: `.pot` file generation (script is wired in `package.json` via `wp i18n make-pot`, needs
WP-CLI i18n command run), actual WordPress.org submission assets (banner/icon), and real-world
testing of the Stripe/PayPal/Google Calendar flows against live sandbox credentials.

**Phase 2 is done**: Appointiva Pro plugin, the license server (separate plugin with
`/activate` `/validate` `/deactivate` endpoints), update-checker integration, and multi-staff
gating as the first Pro feature. **Phase 3 is done** (WhatsApp reminders, recurring appointments,
packages, deposits, two-way calendar sync, the customer self-service dashboard, in-plugin
translations, and the external integration API — every Phase 3 item in the original task list).
**Phase 4 in progress** (CSV import/export done; events module, white-label/agency mode, and mobile
app groundwork not yet started). See `../appointiva-pro/CLAUDE.md` for that work — this file only
covers
the Free plugin. The two open decisions (instant-confirm vs. inquiry/offer as a real, user-facing
feature; license server platform: custom vs. WooCommerce vs. Lemon Squeezy/Paddle) are still
unresolved and should be raised with the user before they'd block further work.
