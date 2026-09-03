# Changelog

All notable changes to mod_pagecheck are recorded here. The plugin follows
[semantic versioning](https://semver.org/) for its release names.

## [0.3.3] - 2026-09-08

### Fixed
- The timeline connector was drawn only across the gap between two steps, so it read as a stub
  beside each marker instead of a line joining them. The steps now stack the marker above its
  label and the connector runs between marker centres, turning to run down the side on a narrow
  screen.
- `box-sizing` is stated on the step marker rather than inherited from the theme's reset, which
  had left the connector two pixels off centre.
- The grade bar had no colour of its own and fell back to the muted grey.
- The page count sat centred in its own column on narrow screens while the rest was ranged left.

## [0.3.2] - 2026-09-07

### Fixed
- A submission that had been graded still showed "Graded" as the step the student was standing on,
  and the report stacked two pills saying overlapping things. The stored status stops at
  "submitted" because grading lives in its own table, so every screen now asks
  `submission_manager::furthest_state()` how far a submission has actually got.
- Six pages printed the activity name a second time under the theme's own activity header.

### Changed
- "All checks passed" moved from a paragraph in the middle of the panel to a chip beside the status.
- The page ruler dropped the 0 and maximum labels that repeated the minimum and maximum marks, and
  a mark close to either end lines its label up with that end.
- Facts sit in two columns from 768px; the submissions report scrolls inside its own box.

## [0.3.1] - 2026-09-06

### Fixed
- `[[grade]]` appeared where the label should read Grade, in the student panel, the grading form,
  the report header and the CSV export.
- A student who had submitted and been marked was told in red that they had used all their
  attempts. The attempt allowance is now only checked when work is actually being sent.
- The paper size read "-" for a file counted before the plugin could measure one; such rows are
  recounted once, and a format that genuinely has no paper size is stored as empty.

### Added
- A Draft, Submitted, Graded timeline, minimum and maximum marks at their real positions on the
  page ruler, and animations that respect `prefers-reduced-motion`.

## [0.3.0] - 2026-09-05

### Added
- A grading screen: one student at a time, showing the submission as the student sees it, with a
  grade, a comment, the earlier attempts, and previous/next navigation through the class.
- Feedback is stored with the grade and travels into the gradebook, backups and the privacy export.
- Scales are supported: the stored value is the scale item, shown by its name everywhere.

## [0.2.0] - 2026-09-04

### Added
- Required paper size (A4, A3, A5, Letter, Legal), read from each page's `/MediaBox`.
- The page range can apply to the whole submission or to each file on its own.
- A minimum number of files, a required file name pattern, and refusing the same file twice.
- The plugin's own icon, and screens rebuilt around the page count.

## [0.1.3] - 2026-09-03

### Fixed
- The submissions report listed only enrolled participants, so work submitted by anyone else was
  invisible; it now lists everyone who actually has a submission.
- Invalid SQL broke the submissions and overrides pages.
- An undefined constant broke the submission form.

## [0.1.0] - 2026-09-02

### Added
- First release: an activity that collects files and counts their pages, with restrictions on page
  count, file types, size, dates, attempts, group submission, per group and per user overrides,
  and document checks for encryption, a missing text layer and blank pages.
