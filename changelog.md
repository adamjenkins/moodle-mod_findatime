# Changelog

All notable changes to this project are documented in this file.
The format is based on [Keep a Changelog](https://keepachangelog.com/).

## [0.1.3] - 2026-10-04

### Changed

- Release archives leave out development files (`.github`, `.camp`, `tests` and similar) through `.gitattributes` export-ignore rules, which the camp release workflow requires. No change to the plugin itself; this release carries 0.1.2 to the camp registry.

## [0.1.2] - 2026-10-04

### Added
- The GPL-3.0 licence text (`LICENSE`) at the repository root.

### Changed
- Maturity raised from `MATURITY_ALPHA` to `MATURITY_BETA`.
- CI: the non-blocking moodle.git `main` jobs become blocking `MOODLE_503_STABLE` jobs now that Moodle 5.3 is released.
- Composer: the `moodle/moodle` constraint is `^4.5 || ^5.0` (was `>=4.5 <5.4`), so new 5.x releases are not excluded.

## [0.1.1] - 2026-10-04

### Added
- Tagged releases are published to the camp plugin registry.

## [0.1.0] - 2026-09-28

### Added
- Availability grid with click-and-drag, touch and keyboard input, saved automatically through web services.
- Group overlap heatmap with names on hover and focus, and a ranked list of the best meeting times.
- Confirming, moving and cancelling a group's meeting; a group calendar event and Message API notifications follow.
- Automatic confirmation of the most popular time at a chosen moment; "Any group member may confirm" setting.
- Teacher overview of every group's response rate and meeting status; Moodle 5 course overview items.
- Activity completion rule "submitted availability", course reset, backup and restore, privacy provider, events.
