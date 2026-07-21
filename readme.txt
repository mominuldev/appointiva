=== Appointiva ===
Contributors: appointiva
Tags: booking, appointment, scheduling, calendar, reservations
Requires at least: 6.5
Tested up to: 6.6
Requires PHP: 8.1
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Appointment and service booking for salons, consultants, tutors, clinics, and other service businesses. Multilingual, RTL-ready, and accessible out of the box.

== Description ==

Appointiva lets visitors book a service and time slot directly on your site, with confirmation and reminder emails handled automatically.

**Free plugin features**

* Single staff, single service booking calendar
* Configurable weekly availability and blocked-time rules
* Email confirmation and reminder notifications, sent in the customer's own language
* One-way sync of confirmed bookings to Google Calendar
* Stripe and PayPal payment collection
* A Gutenberg block and a `[appointiva_booking]` shortcode to embed the booking form anywhere
* A dependency-free, accessible (WCAG AA) booking widget — full keyboard navigation and ARIA labeling, no JavaScript framework required
* Calendar and email templates automatically adapt to the visitor's light/dark OS preference
* SMTP delivery with one-click presets for Gmail, Outlook, Yahoo, iCloud, and Zoho
* GDPR-aware: explicit consent checkbox on the booking form, and full integration with WordPress's built-in personal data export/erase tools
* Uninstalling the plugin keeps all of your booking data by default; permanent deletion is an explicit opt-in setting

Need multiple staff, multiple locations, WhatsApp reminders, or a customer self-service dashboard? Those are covered by the separate **Appointiva Pro** add-on (sold separately, requires this plugin).

= Built for extensibility =

Every step of the booking flow — availability calculation, notification dispatch, payment processing, admin UI — fires WordPress actions and filters so other plugins (including Appointiva Pro) can extend it without modifying this plugin's files.

== Installation ==

1. Upload the `appointiva` folder to `/wp-content/plugins/`, or install it through the Plugins screen directly.
2. Activate the plugin through the "Plugins" screen in WordPress.
3. Go to **Appointiva** in the admin menu to add a service and set your weekly availability.
4. Add the "Appointiva Booking Widget" block, or the `[appointiva_booking]` shortcode, to any page.

== Frequently Asked Questions ==

= Does this plugin require a paid subscription? =

No. Every feature listed above is fully usable in the free plugin, with no time limits or locked functionality. Appointiva Pro is an entirely separate, optional plugin for larger, multi-staff businesses.

= Will my booking data be deleted if I uninstall the plugin? =

No, not unless you explicitly turn on "Delete all data on uninstall" in Appointiva → Settings. By default, deleting the plugin keeps all bookings, customers, and settings intact.

= Does the plugin work with WPML or Polylang? =

Yes. Appointiva ships with a translation-ready `.pot` file and RTL-aware styling from the ground up.

= Is the booking widget accessible? =

Yes. The widget is built with semantic HTML, ARIA roles/labels, and full keyboard navigation, and targets WCAG 2.1 AA.

== Screenshots ==

1. The booking widget, embedded via the Gutenberg block.
2. The Appointiva admin dashboard.
3. Weekly availability settings.

== External services ==

This plugin does not contact any external service by default. The following integrations only run when you actively configure and enable them from Appointiva's settings screen — no data is sent until you do.

* **Google Calendar** — if you connect a Google account under Appointiva → Notifications, each newly confirmed booking (date, time, and any notes you or the customer entered) is sent to the Google Calendar API (`googleapis.com`) to create a matching calendar event. This requires you to complete Google's own OAuth consent screen. See Google's Privacy Policy: https://policies.google.com/privacy

* **Stripe** — if you enter Stripe API keys under Appointiva → Payments and a customer pays by card, the booking amount, currency, and a booking reference are sent to Stripe's API (`api.stripe.com`) to create and confirm the payment. See Stripe's Privacy Policy: https://stripe.com/privacy

* **PayPal** — if you enter PayPal API credentials under Appointiva → Payments and a customer pays via PayPal, the booking amount, currency, and a booking reference are sent to PayPal's API (`paypal.com` / `sandbox.paypal.com` in test mode) to create and capture the order. See PayPal's Privacy Policy: https://www.paypal.com/privacy

* **Your own SMTP provider** — if you enable custom SMTP delivery under Appointiva → Notifications, outgoing booking emails are sent through the mail server you configure (e.g. Gmail, Outlook, Yahoo, iCloud, Zoho, or another provider) instead of the server's default mail function. Your SMTP credentials are encrypted (AES-256) before being stored.

== Changelog ==

= 1.0.0 =
* Initial release: booking calendar, availability rules, email notifications, Google Calendar one-way sync, Stripe/PayPal payments, Gutenberg block and shortcode, GDPR tools.

== Upgrade Notice ==

= 1.0.0 =
Initial release.
