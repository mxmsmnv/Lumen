# Lumen Documentation

## Requirements

- ProcessWire 3.0.255+
- PHP 7.4+
- cURL extension (required for direct multipart and TUS uploads)
- Cloudflare account with Stream enabled
- Cloudflare API Token with `Stream:Edit` permission

## Configuration

**Modules → Configure → Lumen**

| Setting | Required | Description |
|---------|:--------:|-------------|
| Cloudflare Account ID | ✅ | Found in your Cloudflare Dashboard URL |
| Cloudflare API Token | ✅ | Create at Dashboard → API Tokens, use the Stream template |
| Require Signed URLs | — | Enforce signed tokens for video playback |
| Use Local Storage | — | Bypass Stream and keep files on server (development) |
| Customer Code Override | — | Public Stream customer subdomain code if auto-detection has no uploaded video to inspect |
| Max Duration (seconds) | — | Default 3600 (1 h), maximum 21600 (6 h) |

To verify credentials, go to **Setup → Lumen** and click **Refresh status**.
Or call `$lumen->validateCredentials($accountId, $apiToken)` in code.

The admin workspace follows `mxmsmnv/pw-design-system` and is organized into
Overview, Videos, Upload, Settings, and Event Log sections. It uses native
ProcessWire/UIkit controls and inherits light, dark, and accent colors from the
active AdminThemeUikit theme.

## Field Setup

1. **Setup → Fields → Add New**
2. Type: **Cloudflare Stream Files**
3. Configure max files, descriptions etc.
4. Add the field to your template.

A Lumen field accepts: mp4, mkv, mov, avi, flv, ts, ps, mxf, lxf, gxf, 3gp, webm, mpg. Maximum file size is 30 GB (Cloudflare Stream limit).

Recommended: H.264 video + AAC audio, ≤ 60 fps, MP4 container.

## Optional integrations

Lumen has no dependency on Channels or on any other publishing workflow. Its
module metadata installs only the Lumen package, and its runtime never resolves
or calls a Channels service. A consumer may use Lumen's public ingestion and
metadata APIs when available, but must feature-detect Lumen and own its content
templates, permissions, moderation, subscriptions, and routes itself.

This boundary means Lumen continues to work when an integration is absent,
disabled, or uninstalled.

## Template API

### File Methods

```php
$video = $page->video->first();

// Stream URLs
$video->streamUrl();              // HLS manifest (.m3u8)
$video->streamEmbed(800, 450);    // Cloudflare iframe player (with subtitles & poster)
$video->streamEmbedResponsive();  // Responsive 16:9 iframe wrapper
$video->streamThumbnail();        // Thumbnail at default position
$video->streamThumbnail(12);      // Thumbnail at 12 seconds
$video->streamPoster();           // Custom poster or auto-thumbnail fallback
$video->streamPreview();          // Cloudflare watch page

// Status & metadata
$video->streamReady();            // bool — video is transcoded
$video->streamDurationFormatted();// "1:23" or "1:02:45"
$video->streamAspect();           // "16:9", "4:3", "21:9", "9:16"

// Organization
$video->linkedPage();             // Page object or null — linked page
$video->tags();                   // array — tag strings
$video->subtitles();              // array of [src, srclang, label]
```

### File Properties

```php
$video->stream_uid;               // Cloudflare video UID (string)
$video->stream_status;            // queued | inprogress | ready | error
$video->stream_ready;             // 0 or 1
$video->stream_duration;          // seconds (int)
$video->stream_width;             // pixels (int)
$video->stream_height;            // pixels (int)
$video->stream_category;          // category string (varchar 255)
$video->stream_tags;              // comma-separated tags (varchar 500)
$video->stream_page_id;           // linked ProcessWire page ID (int)
$video->stream_poster;            // custom poster URL (varchar 500)
$video->stream_subtitles;         // JSON array of subtitle tracks (text)
$video->stream_trim_start;        // trim start timestamp in seconds (decimal)
$video->stream_trim_end;          // trim end timestamp in seconds (decimal)
$video->stream_views;             // view count (int unsigned)
```

### Orientation and short-form eligibility

Cloudflare Stream reports the decoded input width, height, and duration. Lumen
can use those values without trusting a manually assigned content type:

```php
$lumen = $modules->get('Lumen');

$orientation = $lumen->streamOrientation($video);
// portrait | landscape | square | unknown

$isShort = $lumen->isShortFormVideo($video, 120);
// true only when ready, portrait, longer than 0 seconds, and no longer than 120 seconds
```

Both helpers fail closed when Cloudflare has not supplied usable metadata.
Pass `false` as the third argument to `isShortFormVideo()` only when a
classification is needed before Stream processing reaches the ready state.
Lumen refreshes up to ten pending Stream records on
`LazyCron::everyMinute`, so duration and orientation become available without
opening the admin dashboard.

### Creating video Pages from another module

Use the central attachment API instead of instantiating `InputfieldLumen` or
calling its protected upload methods:

```php
$lumen = $modules->get('Lumen');
$video = $lumen->attachUploadedVideo(
    $page,
    'lumen_video',
    $_FILES['video'],
    1024 * 1024 * 1024
);
```

Trusted CLI/import code can use
`$lumen->attachLocalVideo($page, 'lumen_video', $sourcePath)`. Both methods
save the Pagefile first, then use the normal Lumen/Cloudflare upload and
metadata persistence workflow.

### Subtitles

Set `stream_subtitles` as a JSON array:

```php
$video->stream_subtitles = json_encode([
    ['src' => '/files/subtitles-en.vtt', 'srclang' => 'en', 'label' => 'English'],
    ['src' => '/files/subtitles-ru.vtt', 'srclang' => 'ru', 'label' => 'Русский'],
]);
```

These values are stored as Lumen metadata for custom players and future
caption synchronization. Cloudflare's iframe player manages captions through
the Stream caption API; HTML `<track>` elements cannot be placed inside an
iframe.

## Template Examples

### Gallery with Konkat Design System

Uses AdminThemeUikit UIkit classes and `pw-wrap` for native Konkat panels.
Works inside ProcessWire admin views. For site frontends, include UIkit CSS + JS
and set `--pw-*` tokens, or use any framework — Lumen only returns URLs and iframes.

```php
<?php namespace ProcessWire; ?>

<div class="pw-wrap LumenGallery">

  <h2><?= $page->title ?></h2>

  <?php if($page->video->count()): ?>

    <div class="uk-child-width-1-2@m uk-child-width-1-3@l" uk-grid>

      <?php foreach($page->video as $video): ?>

        <div>
          <div class="uk-card uk-card-default">

            <?php if($video->streamReady()): ?>

              <div class="uk-card-media-top">
                <?= $video->streamEmbed(640, 360) ?>
              </div>

              <div class="uk-card-body">
                <h3 class="uk-card-title">
                  <?= $sanitizer->entities($video->description ?: $video->basename) ?>
                </h3>

                <div class="uk-flex uk-flex-middle uk-flex-wrap uk-text-small"
                     uk-grid="class:uk-grid-small">

                  <span class="uk-label uk-label-success"><?= __('Ready') ?></span>

                  <?php if($video->stream_duration): ?>
                    <span class="uk-text-muted">
                      <span uk-icon="icon: clock; ratio: 0.7"
                            class="uk-margin-small-right"></span>
                      <?= gmdate('H:i:s', $video->stream_duration) ?>
                    </span>
                  <?php endif; ?>

                  <?php if($video->stream_width && $video->stream_height): ?>
                    <span class="uk-text-muted">
                      <?= $video->stream_width ?>×<?= $video->stream_height ?>
                    </span>
                  <?php endif; ?>

                </div>
              </div>

            <?php else: ?>

              <div class="uk-card-media-top uk-position-relative uk-background-muted"
                   style="aspect-ratio:16/9">
                <?php if($video->stream_uid): ?>
                  <img src="<?= $video->streamThumbnail() ?>"
                       alt="<?= __('Processing…') ?>"
                       class="uk-position-cover"
                       style="object-fit:cover;opacity:0.5">
                <?php endif; ?>
                <div class="uk-position-center uk-text-center">
                  <div uk-spinner="ratio:1.5"
                       style="color:var(--pw-main-color)"></div>
                  <p class="uk-text-muted uk-text-small uk-margin-small-top">
                    <?= __('Processing…') ?>
                  </p>
                </div>
              </div>

              <div class="uk-card-body">
                <h3 class="uk-card-title">
                  <?= $sanitizer->entities($video->description ?: $video->basename) ?>
                </h3>
                <span class="uk-label uk-label-warning">
                  <?= $video->stream_status === 'error' ? __('Error') : __('Processing') ?>
                </span>
              </div>

            <?php endif; ?>

          </div>
        </div>

      <?php endforeach; ?>

    </div>

  <?php else: ?>

    <div class="uk-placeholder uk-text-center">
      <span uk-icon="icon: play-circle; ratio: 2"></span>
      <p class="uk-text-muted uk-margin-small-top"><?= __('No videos yet.') ?></p>
    </div>

  <?php endif; ?>

</div>
```

### Simple List

```php
<ul>
  <?php foreach($page->video as $video): ?>
    <li>
      <?php if($video->streamReady()): ?>
        <a href="<?= $video->streamPreview() ?>">
          <?= $video->basename ?>
        </a>
      <?php else: ?>
        <?= $video->basename ?> — <?= __('Processing…') ?>
      <?php endif; ?>
    </li>
  <?php endforeach; ?>
</ul>
```

### Video.js Player

```php
<?php $video = $page->video->first(); ?>
<?php if($video && $video->streamReady()): ?>
  <link href="https://vjs.zencdn.net/8.0.4/video-js.css" rel="stylesheet">
  <script src="https://vjs.zencdn.net/8.0.4/video.min.js"></script>
  <video class="video-js vjs-default-skin"
         controls preload="auto"
         width="640" height="360"
         data-setup='{}'>
    <source src="<?= $video->streamUrl() ?>"
            type="application/x-mpegURL">
  </video>
<?php endif; ?>
```

### Responsive Embed

```php
<?php $video = $page->video->first(); ?>
<?php if($video && $video->streamReady()): ?>
  <div class="uk-position-relative" style="padding-bottom:56.25%">
    <?= $video->streamEmbed() ?>
  </div>
<?php endif; ?>
```

## Status Checking

Videos are transcoded asynchronously by Cloudflare. Use one of these
methods to keep status up to date.

### Automatic (LazyCron)

Add to `/site/ready.php`:

```php
$wire->addHook('LazyCron::everyHour', function($event) {
    $inputfield = $event->wire('modules')->get('InputfieldLumen');
    $pages = $event->wire('pages');
    $fields = $event->wire('fields');

    $lumenFields = [];
    foreach($fields as $field) {
        if($field->type instanceof FieldtypeLumen) {
            $lumenFields[] = $field->name;
        }
    }
    if(!$lumenFields) return;

    foreach($lumenFields as $fieldName) {
        $videoPages = $pages->find("{$fieldName}.count>0, include=all");
        foreach($videoPages as $p) {
            foreach($p->get($fieldName) as $video) {
                if(!$video->streamReady() && !empty($video->stream_uid)) {
                    $inputfield->checkStreamStatus($video);
                }
            }
        }
    }
});
```

### Manual

```php
$inputfield = $modules->get('InputfieldLumen');
$video = $page->video->first();
$ready = $inputfield->checkStreamStatus($video);
```

### Dashboard

**Setup → Lumen → Refresh status** — rate-limited to 25 videos per click
with a 100 ms delay between API calls.

## Database Schema

Lumen adds these columns to each field table:

```sql
stream_uid        VARCHAR(100)   DEFAULT NULL
stream_status     VARCHAR(20)    NOT NULL DEFAULT 'queued'
stream_ready      TINYINT(1)     NOT NULL DEFAULT 0
stream_duration   INT            DEFAULT NULL
stream_width      INT            DEFAULT NULL
stream_height     INT            DEFAULT NULL
stream_category   VARCHAR(255)   DEFAULT NULL
stream_tags       VARCHAR(500)   DEFAULT NULL
stream_page_id    INT UNSIGNED   NOT NULL DEFAULT 0
stream_poster     VARCHAR(500)   DEFAULT NULL
stream_subtitles  TEXT           DEFAULT NULL
stream_trim_start DECIMAL(10,3)  DEFAULT NULL
stream_trim_end   DECIMAL(10,3)  DEFAULT NULL
stream_views      INT UNSIGNED   NOT NULL DEFAULT 0
```

Indexes: `PRIMARY KEY (pages_id, sort)`, `KEY stream_uid`, `KEY stream_ready`, `KEY stream_page_id`, `KEY stream_category`.

## API Reference

### Lumen (central module)

| Method | Description |
|--------|-------------|
| `streamConfig()` | Returns all config as an array |
| `streamApiRequest($method, $path, $body, $headers)` | Authenticated HTTP via `WireHttp` |
| `getCustomerCode()` | Playback customer code: override → cache → Stream API |
| `validateCredentials($id, $token)` | Tests credentials, returns `{valid, message}` |

### FieldtypeLumen (public API)

| Method | Returns |
|--------|---------|
| `getStreamUrl($pagefile)` | HLS manifest URL |
| `getStreamEmbed($pagefile, $w, $h)` | iframe HTML (with subtitles, poster, trim) |
| `getStreamEmbedResponsive($pagefile, $w, $h)` | Responsive 16:9 iframe wrapper |
| `getStreamThumbnail($pagefile, $time)` | Thumbnail URL |
| `getStreamPoster($pagefile, $time)` | Custom poster or thumbnail fallback |
| `getStreamPreview($pagefile)` | Cloudflare watch page URL |
| `isStreamReady($pagefile)` | bool — video is playable |
| `getStreamDurationFormatted($pagefile)` | "1:23" or "1:02:45" |
| `getStreamAspect($pagefile)` | "16:9", "4:3", etc. |
| `getLinkedPage($pagefile)` | Page object or null — linked page |
| `getTags($pagefile)` | array — tag strings |
| `getSubtitlesArray($pagefile)` | array of [src, srclang, label] |

### InputfieldLumen

| Method | Description |
|--------|-------------|
| `checkStreamStatus($pagefile)` | Polls API, updates DB, returns `bool` |
| `saveStreamMetadata($pagefile)` | Unified DB write via prepared statement |

### ProcessLumen (admin dashboard)

| Constant | Value | Description |
|----------|:-----:|-------------|
| `REFRESH_BATCH_SIZE` | 25 | Max videos per refresh click |
| `API_RATE_LIMIT_US` | 100000 | 100 ms delay between API calls |
| `PER_PAGE` | 24 | Videos per page in gallery |

Dashboard features:
- **Filters**: by status, category, tags, search
- **Sorting**: status, name, date, duration, views, category
- **Pagination**: 24 videos per page
- **Bulk actions**: select and delete multiple videos
- **Copy buttons**: embed shortcode + stream URL one-click copy
- **Clickable stats**: stat cards filter by status

## CloudCache compatibility

Lumen detects `CloudCache` without requiring it as a dependency.

- Public Cloudflare Stream playback uses stable UIDs and remains eligible for
  anonymous full-page caching.
- Local-storage playback uses stable file URLs and remains eligible.
- Remote playback with **Require Signed URLs** enabled automatically vetoes
  CloudCache for the rendered document. Signed playback identifiers expire, so
  they must not be persisted in L1, Apache L2, or Cloudflare edge HTML.
- Lumen admin, upload, status, delete, and other mutation requests are already
  outside public page caching; ProcessWire page saves trigger CloudCache's
  normal page, parent, and home invalidation.
- A Cloudflare status refresh can update field metadata without a Page save.
  When its public status, readiness, duration, or dimensions change, Lumen calls
  CloudCache's integration API to invalidate the page, parent listing, and home.

## Server Configuration

### PHP (`php.ini`)

```ini
upload_max_filesize = 2G
post_max_size       = 2G
max_execution_time  = 600
max_input_time      = 600
memory_limit        = 512M
```

### Nginx

```nginx
client_max_body_size  2048M;
proxy_read_timeout    600s;
fastcgi_read_timeout  600s;
```

### Apache (`.htaccess`)

```apache
php_value upload_max_filesize 2G
php_value post_max_size       2G
php_value max_execution_time  600
php_value max_input_time      600
php_value memory_limit        512M
```

## Troubleshooting

### Video stuck on "Processing"

1. Check Cloudflare Stream dashboard for the video UID.
2. Run `$inputfield->checkStreamStatus($video)` manually.
3. Enable debug mode: `$config->debug = true;` in `/site/config.php`.
4. Check logs: `/site/assets/logs/stream-debug.txt` and
   `/site/assets/logs/stream-error.txt`.

### Upload fails

1. Check PHP `upload_max_filesize` and `post_max_size` (≥ 2G).
2. Check disk space in `/site/assets/files/`.
3. Review `/site/assets/logs/stream-error.txt`.

### API Errors

| Code | Cause | Fix |
|:----:|-------|-----|
| 401 | Invalid or expired token | Recreate API token with `Stream:Edit` |
| 403 | Stream not enabled | Verify Cloudflare billing / subscription |
| 404 | Wrong Account ID or deleted video | Check Account ID from Dashboard URL |

### Customer code is wrong

Set **Customer Code Override** in **Modules → Configure → Lumen**.

## Design System

This module targets **AdminThemeUikit with the Konkat default skin**
(ProcessWire 3.0.255+).

Admin output uses `pw-wrap` panel wrappers, `uk-table uk-table-divider
uk-table-justify uk-table-small` tables, and `uk-label` semantic status
badges (`uk-label-success`, `uk-label-warning`, `uk-label-danger`).

No hardcoded colors — styling relies on `--pw-*` CSS custom properties. Custom
CSS is scoped to `.LumenDashboard` and `.LumenFileItem` to avoid leaking into
the admin.

The Konkat design-system reference is available at
[github.com/mxmsmnv/pw-design-system](https://github.com/mxmsmnv/pw-design-system).
