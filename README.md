# Soro to Google Business Profile Auto Post

Automatically cross-posts articles published on your WordPress site (e.g. by Soro) to your Google Business Profile as **"What's New"** posts, so your business stays active on Google Maps and Search.

## Features

- **Automatic cross-posting** — when an article is published (including automatically by Soro), a matching "What's New" post is created on your Google Business Profile with the title, a short summary, and a "Learn more" button linking to the article.
- **Language filter** — choose which language(s) may be posted. Articles in other languages are skipped. Uses Polylang / WPML assignments when available, otherwise automatic language detection.
- **Featured image reuse** — the article's featured image is used on the Google post. Images are automatically resized/converted to meet Google's requirements (JPEG/PNG, min 400×300, max 5 MB). Optional fallback image for articles without one.
- **Category filter** — optionally limit cross-posting to selected categories (e.g. only Soro's category).
- **Multi-location support** — post to one or all of your Business Profile locations.
- **Posting log** — see exactly what was posted, skipped (and why), or failed.
- **Manual trigger** — a "Post to Google Business" row action on the posts list for immediate re-posting.

## Requirements

- WordPress 5.0+ with PHP 7.0+
- The [GD image extension](https://www.php.net/manual/en/book.image.php) (standard in most installs) for image conversion
- A Google Cloud Console project with the **Business Profile API** enabled (free)

## Installation

1. Copy the `soro-gbp-auto-post` folder into `wp-content/plugins/`.
2. Activate **Soro to Google Business Profile Auto Post** in the Plugins screen.
3. Open **Settings → Soro → Google Business**.

## Google Cloud Console setup (one time)

> **Important:** the Business Profile API (formerly "My Business API", the one that creates posts) is a **restricted API**. It is hidden from the API Library and has a quota of 0 until Google approves your project through their access request form. This is Google's gate — every third-party posting tool had to pass it.

1. Go to [Google Cloud Console](https://console.cloud.google.com/) and create a project (or use an existing one).
2. Go to **APIs & Services → OAuth consent screen** and configure it:
   - User type: **External**, then add your own Google account email as a **Test user** (required while the app is in testing mode).
3. Go to **APIs & Services → Credentials → Create Credentials → OAuth client ID**:
   - Application type: **Web application**
   - Under **Authorized redirect URIs**, add the redirect URI shown on the plugin settings page (it looks like `https://yoursite.com/wp-admin/admin.php?page=soro-gbp-auto-post&sgbp_oauth=callback` — it must match exactly).
4. Copy the **Client ID** and **Client Secret** into the plugin settings page and save.
5. **Request Business Profile API access**: log in with the Google account that owns your Business Profile and complete the access request form linked from [Google's prerequisites page](https://developers.google.com/my-business/content/prereqs). Use your project's ID and describe the use case, e.g. *"Automatically publish our website's blog articles as What's New posts on our own Business Profile."* Requirements: your Business Profile must be verified.
6. Wait for the approval email (typically a few days to a couple of weeks). Approval is granted per Google Cloud project.
7. Once approved, enable the API — either via the direct link `https://console.cloud.google.com/apis/library/mybusiness.googleapis.com` or by following the enable steps on [Google's basic setup page](https://developers.google.com/my-business/content/basic-setup). If it still does not appear, check the API's quota page in your project — approval sometimes shows up there first.

Until the project is approved, the plugin can save credentials but "Refresh locations" will fail with a 403 error. This is expected and resolves once Google grants access.

## Connecting your account

1. After saving credentials, click **Connect Google account**.
2. Log in with the Google account that manages your Business Profile and grant access.
3. Back on the settings page, click **Refresh locations**, tick the locations you want to post to, and save.
4. Choose your language filter (e.g. only English) and optionally restrict to Soro's category.
5. You are done — every newly published article in the selected language will automatically appear on your Google Business Profile.

## Notes

- Google Business Profile posts expire automatically (typically after ~6 months or 100 posts per location).
- If your site is behind maintenance mode or basic auth, Google cannot fetch post images; keep the uploads folder publicly accessible.
- The plugin only posts; it never edits or deletes your Google content.

## Changelog

### 1.0.0
- Initial release.
