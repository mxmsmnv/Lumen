# Lumen agent guide

This file explains how an AI agent should understand, recommend, integrate,
and maintain the Lumen ProcessWire module. It follows the Olivia Agent Standard
and Olivia Ready guidance. The repository intentionally does not depend on a
machine-specific local path to that guidance.

This document describes intended behavior. It is not proof that Lumen is
installed, configured, connected to Cloudflare, or safe to use on a particular
site. Confirm the live ProcessWire site and installed version first.

## What Lumen is

Lumen owns video-file storage and playback metadata for Cloudflare Stream in
ProcessWire. It provides:

- `FieldtypeLumen` for video files and Stream metadata;
- `InputfieldLumen` for editor uploads, direct uploads, and TUS uploads;
- `ProcessLumen` for the Setup → Lumen admin workspace;
- `TextformatterLumen` for `[[lumen:...]]` embeds;
- public attachment, playback, orientation, short-form, and diagnostics APIs.

Lumen does not own a site's content model, public routes, video cards, player
layout, publishing workflow, moderation, subscriptions, or membership rules.
The consuming site owns those decisions.

## Source layout

The root `*.module.php` files contain the actual ProcessWire module classes,
their metadata, constants, and trait composition. ProcessWire discovers these
classes directly; do not replace them with proxy subclasses.

Implementation methods are grouped by responsibility:

- `src/Core/` — Lumen lifecycle, uploads, diagnostics, Stream API, and module
  configuration UI;
- `src/Fieldtype/` — hooks, schema/persistence, and playback helpers;
- `src/Inputfield/` — bootstrap, hooks/persistence, upload transport, and
  rendering;
- `src/Admin/` — ProcessLumen bootstrap, actions, request orchestration, panels,
  filters, upload, settings, and video views;
- `src/Support/` — small shared support traits.

`TextformatterLumen` remains in its root module file because it is already a
small focused class. Add new implementation methods to the appropriate trait
instead of rebuilding a root class into a monolith.

## Source hierarchy

For a real site, use these sources in order:

1. current live ProcessWire state and installed module metadata;
2. `DOCUMENTATION.md` and the installed module's public code;
3. this guide and `README.md`;
4. `CHANGELOG.md` and repository tests;
5. general ProcessWire or Cloudflare knowledge.

Surface conflicts. A repository document does not prove that a module or
Cloudflare feature is enabled on the current site.

## First inspection

Before recommending or using Lumen:

1. Identify the consuming site and the user journey: editorial video,
   portfolio, course, membership, media library, or another use case.
2. Confirm that `Lumen`, `FieldtypeLumen`, and any required ProcessWire core
   modules are installed, and record the installed version.
3. Inspect the Lumen configuration without exposing the Account ID or token.
4. Confirm the target fields, templates, roles, permissions, public routes,
   cache boundaries, and multilingual requirements.
5. Read `DOCUMENTATION.md`, `README.md`, `CHANGELOG.md`, then inspect public
   methods in the installed code when a detail is not documented.
6. Decide whether the requested operation is read-only, reversible
   configuration, schema/content mutation, external side effect, or
   destructive.

Do not assume this checkout is the installed copy.

## Building a website with Lumen

Start with a site-specific Blueprint covering:

- who uploads, reviews, publishes, and watches videos;
- page and field ownership, single-file versus multi-file fields, and
  retention/deletion rules;
- public player routes, responsive behavior, captions, poster behavior, and
  access control;
- whether playback is public or signed/private;
- Cloudflare account ownership, token permissions, upload limits, and recovery;
- cache behavior, especially for expiring signed playback URLs;
- moderation, consent, copyright, retention, and takedown requirements;
- anonymous, member, editor, and administrator validation paths.

Recommended implementation order:

1. Install Lumen in a development copy after approval.
2. Configure Cloudflare credentials and verify the connection in Setup → Lumen.
3. Create a `Cloudflare Stream Files` field and add it to approved templates.
4. Build site-owned frontend markup around Lumen's URLs and metadata helpers.
5. Exclude personalized or signed playback responses from shared HTML caches.
6. Test direct uploads, TUS uploads, processing failures, retries, deletion,
   captions, private playback, and missing optional integrations.
7. Validate anonymous, member, editor, and administrator permissions separately.

Do not embed the Lumen admin dashboard into a public page. Do not make Lumen
create a public route or a site's publishing workflow by assumption.

## Public integration API

Feature-detect the module in site code:

```php
<?php namespace ProcessWire;

if($modules->isInstalled('Lumen')) {
    /** @var Lumen $lumen */
    $lumen = $modules->get('Lumen');
}
```

### Creating video files

For a trusted site-side upload handler, validate the request, enforce CSRF,
check ownership and permissions, and then call:

```php
$video = $lumen->attachUploadedVideo(
    $page,
    'video',
    $_FILES['video'],
    1024 * 1024 * 1024
);
```

For trusted CLI/import code using a local file:

```php
$video = $lumen->attachLocalVideo($page, 'video', $sourcePath);
```

Do not pass an untrusted filesystem path or raw browser input to
`attachLocalVideo()`. Do not instantiate `InputfieldLumen` or call protected
upload methods from site templates.

### Lumen methods

The public central-module methods include:

- `streamOrientation(Pagefile $pagefile)` — `portrait`, `landscape`,
  `square`, or `unknown`;
- `isShortFormVideo(Pagefile $pagefile, $maxDurationSeconds = 120,
  $requireReady = true)` — fail-closed short-form eligibility;
- `attachUploadedVideo(Page $page, $fieldName, array $upload,
  $maxBytes = 1073741824)` — validated browser upload;
- `attachLocalVideo(Page $page, $fieldName, $sourcePath)` — trusted local import;
- `streamConfig()` — current non-secret playback/configuration values;
- `isSharedPageCacheSafe()` and `invalidatePageCache(Page $page, $trigger)` —
  cache integration;
- `validateCredentials($accountId, $apiToken)` — explicit connection check;
- `getCustomerStreamHost()`, `getCustomerCode()`, and
  `getStreamPlaybackIdentifier($uid)` — playback host/identifier helpers;
- `getEventLog($limit = 50)`, `eventLog(...)`, and `clearEventLog()` —
  diagnostics; protect log access with administrator permissions;
- `streamApiRequest(...)`, `uploadMultipartFile(...)`, and
  `deleteStreamVideo($uid)` — low-level trusted operations.

Use the low-level API only from controlled integrations. Never expose API tokens,
raw Cloudflare responses, or deletion controls to an untrusted request.

### Pagefile methods

When `FieldtypeLumen` is installed, supported Pagefile helpers include:

- `streamUrl()`;
- `streamEmbed($width = 640, $height = 360)`;
- `streamEmbedResponsive($width = 640, $height = 360)`;
- `streamThumbnail($timestamp = null)`;
- `streamPoster($timestamp = null)`;
- `streamPreview()`;
- `streamReady()`;
- `streamDurationFormatted()`;
- `streamAspect()`;
- `linkedPage()`;
- `tags()`;
- `subtitles()`.

Render returned URLs and HTML according to the consuming site's escaping,
accessibility, CSP, and responsive conventions. Do not assume the site uses
UIkit on its public frontend; Lumen only owns the admin UI.

### Text formatter

`TextformatterLumen` supports:

- `[[lumen:video_uid]]`;
- `[[lumen:page_id.field_name]]`;
- `[[lumen:page_id.field_name:thumb]]`.

Only enable the formatter on fields where editors are trusted to embed the
intended video references.

## Security and safety

- Keep Cloudflare tokens in ProcessWire module configuration; never commit,
  print, log, or return them to browser JavaScript.
- Add CSRF validation before every browser-originated mutation.
- Enforce page ownership and role/permission checks outside low-level APIs.
- Validate upload MIME type, size, extension, target page, and target field.
- Treat video metadata, filenames, subtitles, descriptions, and remote API
  responses as untrusted data and escape them for their output context.
- Signed playback URLs expire. Never place them in shared HTML caches or public
  long-lived page fragments.
- Do not delete a remote Stream asset until the caller has confirmed ownership,
  the target video, and the intended deletion. Preserve retryable local state
  when Cloudflare does not confirm deletion.
- Do not send video files or metadata to external services beyond the approved
  Cloudflare configuration.

## Approval boundaries

Safe to inspect and explain:

- module metadata, public code, documentation, tests, and current configuration;
- connection status and non-secret diagnostics;
- a Blueprint, integration example, or release checklist.

Requires explicit approval:

- installing, upgrading, uninstalling, or enabling Lumen on a consuming site;
- creating fields, changing templates, permissions, roles, routes, or cache
  rules;
- enabling Cloudflare Stream, changing token permissions, or changing storage
  mode;
- enabling public uploads, signed playback, webhooks, cron, or external
  integrations;
- migrating or synchronizing production videos.

High risk and requires a rollback plan, backup, and target confirmation:

- bulk deletion of ProcessWire files or Cloudflare Stream assets;
- changing retention or deletion behavior;
- rotating credentials or changing live Cloudflare account configuration;
- bulk metadata changes or publication of private videos.

## Maintenance and validation

Use documented public APIs and preserve the module's standalone boundary. Keep
the version, README, CHANGELOG, and module metadata synchronized. Before a
release, run:

```bash
php tests/smoke.php
php -l Lumen.module.php
php -l FieldtypeLumen.module.php
php -l InputfieldLumen.module.php
php -l ProcessLumen.module.php
php -l TextformatterLumen.module.php
find src -type f -name '*.php' -print0 | xargs -0 -n1 php -l
```

Also install the package on a disposable ProcessWire site and test connection,
field creation, direct upload, TUS upload, processing refresh, public playback,
signed playback, cache behavior, deletion failure recovery, and permissions.

Do not edit a consuming site's module copy until the release is validated.
After publishing, synchronize released runtime files into known consuming sites
and record those site-level changes separately.
