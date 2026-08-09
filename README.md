# Lumen

Lumen adds Cloudflare Stream video hosting to ProcessWire: upload a source
video once, let Cloudflare encode it, and deliver adaptive playback, embeds,
thumbnails, metadata, and watch pages from ProcessWire.

![Lumen](assets/readme-doodle.png)

It is made for sites where video belongs to the content model: media libraries,
editorial platforms, courses, portfolios, product showcases, and membership
projects.

**Version:** 1.2.1  
**Author:** Maxim Semenov  
**Website:** [smnv.org](https://smnv.org)  
**Email:** [maxim@smnv.org](mailto:maxim@smnv.org)

If this project helps your work, consider supporting future development: [GitHub Sponsors](https://github.com/sponsors/mxmsmnv) or [smnv.org/sponsor](https://smnv.org/sponsor/).

## What Lumen Does

- Adds a ProcessWire file fieldtype for Cloudflare Stream videos.
- Supports direct uploads and resumable TUS uploads.
- Stores Stream status, duration, dimensions, category, tags, poster,
  subtitles, trim points, linked page, and view count.
- Provides HLS URLs, responsive iframe embeds, thumbnails, posters, and watch
  previews.
- Supports public playback and private playback with signed Stream tokens.
- Detects portrait, landscape, square, and unknown orientation.
- Identifies ready portrait videos that fit a configurable short-form duration.
- Refreshes pending Stream metadata in bounded `LazyCron` jobs.
- Includes local-storage mode for development without a Cloudflare account.
- Provides safe upload and metadata APIs for other modules.
- Integrates with CloudCache while protecting expiring signed playback URLs.
- Handles recoverable remote deletion failures and keeps local metadata retryable.
- Embeds videos in formatted text with `[[lumen:...]]` shortcodes.

## Admin Area

Lumen adds **Setup → Lumen**, where editors use separate, directly linkable
Overview, Videos, Upload, Settings, Usage, and Event Log pages to:

- check the Cloudflare connection and processing health;
- browse, search, filter, sort, and paginate the video library;
- upload a video to a compatible page and Lumen field;
- refresh processing status and inspect video metadata;
- copy embed codes, Stream URLs, thumbnails, and previews;
- estimate stored and delivered Stream minutes;
- configure credentials, upload limits, signed playback, and local mode;
- inspect and clear the diagnostic event log;
- bulk-delete selected videos with remote deletion safeguards.

The admin workspace uses native ProcessWire Inputfields and UIkit components,
theme-aware `--pw-*` tokens, responsive pill navigation, compact status
filters, a library-first layout, and scoped styles. Secondary usage estimates
remain available in a disclosure without pushing the video library below the
initial workspace view.

## Public API

Feature-detect the optional module before calling it:

```php
<?php namespace ProcessWire;

if($modules->isInstalled('Lumen')) {
    /** @var Lumen $lumen */
    $lumen = $modules->get('Lumen');
    $orientation = $lumen->streamOrientation($video);
    $isShort = $lumen->isShortFormVideo($video, 120);
}
```

For trusted integrations that create a video Page, use:

```php
$video = $lumen->attachUploadedVideo(
    $page,
    'video',
    $_FILES['video'],
    1024 * 1024 * 1024
);

$video = $lumen->attachLocalVideo($page, 'video', $sourcePath);
```

Pagefile helpers:

```php
$video->streamUrl();
$video->streamEmbed(800, 450);
$video->streamEmbedResponsive();
$video->streamThumbnail(12);
$video->streamPoster();
$video->streamPreview();
$video->streamReady();
$video->streamDurationFormatted();
$video->streamAspect();
$video->linkedPage();
$video->tags();
$video->subtitles();
```

The text formatter supports `[[lumen:video_uid]]`,
`[[lumen:page_id.field_name]]`, and `[[lumen:page_id.field_name:thumb]]`.

## Installation

1. Copy the `Lumen` directory into `/site/modules/`.
2. Refresh modules in ProcessWire and install **Lumen**.
3. Enable Cloudflare Stream for the account.
4. Create an API token with `Stream:Edit` permission.
5. Open **Modules → Configure → Lumen** and save the public Account ID and API
   token. The token is hidden after saving; leave its field blank to keep the
   configured token.
6. Open **Setup → Lumen** and refresh the connection status.
7. Create a **Cloudflare Stream Files** field and add it to the required
   templates.

Requirements: ProcessWire 3.0.255+, PHP 7.4+, cURL, and a Cloudflare account
with Stream enabled. Local-storage mode is available for development.

## Optional Integrations

Lumen is autonomous. It does not require Channels or another publishing
workflow and does not own frontend routes, subscriptions, moderation, or
content templates. Other modules may feature-detect Lumen and use its public
upload and metadata APIs without becoming a Lumen requirement.

## Documentation

See [DOCUMENTATION.md](DOCUMENTATION.md) for field setup, Cloudflare
configuration, template APIs, signed playback, uploads, short-form detection,
CloudCache behavior, troubleshooting, and security notes.

See [CHANGELOG.md](CHANGELOG.md) for release notes.

## Author

Maxim Semenov  
[smnv.org](https://smnv.org)  
[maxim@smnv.org](mailto:maxim@smnv.org)

## License

MIT. See [LICENSE](LICENSE).
