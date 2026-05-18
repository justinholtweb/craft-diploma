# Changelog

## 5.0.2 - 2024-06-18

### Added
- Initial release
- Courses, lessons, and quizzes as Craft elements
- Multiple question types: multiple choice, true/false, short answer, matching
- Enrollment and progress tracking
- Certificate generation with public verification
- Pro edition: drip content, PDF certificates, Commerce/Headcount integration

### Fixed
- "Add Question" button now appears on the quiz edit screen — the control-panel JS bundle was registering CSS only, so `quiz-builder.js` never loaded.
- Question modal is now scrollable when content exceeds the viewport; the Save/Cancel footer stays pinned while the body scrolls.

### Changed
- Redesigned the question modal to use Craft's native form styling (proper field labels, select dropdown, fullwidth textareas, bordered answer rows) and Garnish's modal shell.

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
