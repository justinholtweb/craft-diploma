# Changelog

## 5.0.3 - 2026-05-18

### Added
- Full multisite support. Each course, lesson, and quiz lives only on the site it was created on, just like Craft entries with site-scoped sections. Element queries and index pages are now correctly scoped to the active site.
- Standard Craft breadcrumb-style site switcher on every Diploma CP page (dashboard, course/lesson/quiz edit screens, enrollments, certificates, settings). Course and quiz element indexes show the same switcher when multisite is enabled. Switching sites preserves the current path and query params.
- Migration `m260518_140000_pin_elements_to_primary_site` cleans up any cross-site `elements_sites` rows from earlier installs so existing elements remain pinned to their original site.

### Fixed
- "Add Question" button now appears on the quiz edit screen — the control-panel JS bundle was registering CSS only, so `quiz-builder.js` never loaded.
- Question modal is now scrollable when content exceeds the viewport; the Save/Cancel footer stays pinned while the body scrolls.
- Saving a question now works on multisite installs. The save endpoint URL was built by concatenating `Craft.actionUrl + '/diploma/questions/save'`, which produced a broken URL when `Craft.actionUrl` already contained a `?site=…` query string. Switched to `Craft.getActionUrl()` so the path is appended before the query string.
- Frontend `progress.js` and `quiz.js` no longer hardcode `/diploma/api/…` paths, which broke on sites configured under a non-root base URL. The asset bundle now publishes a `window.DiplomaUrls` object built from `UrlHelper::actionUrl()` so the scripts always hit the correct per-site action endpoint.

### Changed
- Redesigned the question modal to use Craft's native form styling (proper field labels, select dropdown, fullwidth textareas, bordered answer rows) and Garnish's modal shell.
- `Course`, `Lesson`, and `Quiz` now override `getSupportedSites()` to return all site IDs, declare `isLocalized()` as `true`, and treat the title as non-translatable so the same canonical title is shared across sites.
- Bumped `schemaVersion` to `1.0.1`. Run `php craft migrate/all` after upgrading.

## 5.0.1 - 2026-04-10

### Fixed
- Dashboard cards and other CP styles weren't loading because the plugin layout never registered the CP asset bundle.

### Changed
- Marked nullable parameters explicitly (`?string`) on element `defineSources()` and `defineActions()` for forward compatibility with PHP 8.4's implicit-nullable deprecation.
- Switched lesson and course queries from the string form of `orderBy()` to the array form (`['sortOrder' => SORT_ASC]`), and quoted column references in `select()` so cross-driver behavior is consistent.

## 5.0.0 - 2026-03-24

### Added
- Initial release.
- Courses, lessons, and quizzes as Craft elements.
- Multiple question types: multiple choice, true/false, short answer, matching.
- Enrollment and progress tracking.
- Certificate generation with public verification.
- Pro edition: drip content, PDF certificates, Commerce/Headcount integration.
