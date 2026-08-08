# Changelog

All notable changes to Lumen will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.1.9] - 2026-08-08

### Changed

- Reworked Setup → Lumen around the video-management workflow: compact status
  filters and actions now lead directly into the library.
- Replaced the wide sort-button row with a labelled native select and preserved
  tag filters when submitting a title search.
- Moved usage and cost estimates into a dedicated, collapsed section while
  keeping plan details available on demand.
- Aligned workspace navigation with the rounded UIkit pill pattern used by the
  wider ProcessWire module family and improved responsive overflow behavior.
- Reduced repeated local-storage notices inside video cards.
- Fixed the Total status control so it clears filtering instead of requesting
  the nonexistent `total` processing state.

## [1.1.8] - 2026-08-08

### Fixed

- Rendered the Cloudflare Account ID as a normal validated text field instead
  of a password-change form.
- Replaced the API token control with a single masked input. Existing tokens
  are never rendered into the DOM and remain unchanged when the field is left
  blank.
- Moved the connection checklist into a compact, collapsed help section so the
  credential controls remain visible without scrolling.

## [1.0.0] - 2026-08-02

First release of Lumen. The module has not been published before.

### Added

- Cloudflare Stream video fieldtype for ProcessWire.
- Direct and resumable TUS uploads, status refresh, metadata persistence,
  thumbnails, posters, subtitles, trim metadata, and watch previews.
- Public and signed private playback helpers, orientation and short-form
  detection, and bounded pending-video refreshes.
- Local-storage development mode and public attachment APIs for integrations.
- Setup → Lumen dashboard with video library, upload, filters, usage estimates,
  settings, event log, and protected bulk deletion.
- CloudCache compatibility that prevents expiring signed playback URLs from
  entering shared page caches.
- `[[lumen:...]]` textformatter shortcodes.
- Responsibility-based source layout under `src/Core`, `src/Fieldtype`,
  `src/Inputfield`, `src/Admin`, and `src/Support` while keeping the actual
  ProcessWire module classes in their root `*.module.php` files.
- README illustration, sponsorship metadata, Olivia agent guidance, and MIT
  license.

### Security

- CSRF validation and server-side validation protect dashboard mutations.
- API tokens are stored as password configuration and are not rendered back
  into the settings DOM or written to event logs.
- Stream deletion preserves local metadata when remote deletion cannot be
  confirmed, allowing a safe retry.
