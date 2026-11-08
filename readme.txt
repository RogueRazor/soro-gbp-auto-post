=== Soro to Google Business Profile Auto Post ===
Contributors: roguerazor
Tags: google business profile, google my business, local seo, autopost, soro
Requires at least: 5.0
Tested up to: 6.7
Requires PHP: 7.0
Stable tag: 1.0.1
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Automatically cross-posts published articles (e.g. Soro articles) to your Google Business Profile as "What's New" posts.

== Description ==

When a new article is published on your site, this plugin creates a matching "What's New" post on your Google Business Profile — title, summary, featured image, and a "Learn more" button linking to the article. This keeps your profile active, which helps customers find you on Google Maps and Search.

Features:

* Automatic cross-posting on publish (works with Soro's automatic publishing)
* Language filter — only chosen languages are posted, others are skipped
* Polylang / WPML aware, with automatic language detection fallback
* Reuses the article's featured image (auto-resized to Google's requirements)
* Optional fallback image for articles without a featured image
* Optional category filter
* Multiple business locations supported
* Posting log with status and error messages
* Manual "Post to Google Business" action on the posts list

== Installation ==

1. Upload the soro-gbp-auto-post folder to /wp-content/plugins/
2. Activate the plugin through the Plugins screen
3. Open Settings → Soro → Google Business and follow the setup steps in README.md

== Frequently Asked Questions ==

= Does this write articles or reply to reviews? =

No. It only cross-posts your published articles to your Google Business Profile.

= Do Google posts expire? =

Yes, Google removes Business Profile posts automatically over time. Simply keep publishing articles and the plugin keeps your profile fresh.

== Changelog ==

= 1.0.1 =
* Fixed the OAuth connect flow: the redirect URI is now properly URL-encoded, so Google returns to the plugin's callback (previously the connection silently failed).
* Switched account and location lookups to the current Business Profile APIs (My Business Account Management API and My Business Business Information API) after Google shut down the old v4 accounts/locations endpoints.
* Fixed the local post media field (mediaFormat PHOTO instead of mediaType).
* Location names are now normalized to the full accounts/{id}/locations/{id} path, and the location list shows proper titles.
* Real Google error messages are now shown on the settings page and logged, and error notices display in red.

= 1.0.0 =
* Initial release.
