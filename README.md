# Diploma — LMS Plugin for Craft CMS 5

Create and manage online courses, lessons, quizzes, and certificates directly in Craft CMS. Diploma brings the essential features of a learning management system into the Craft ecosystem -- structured courses with ordered lessons, auto-graded quizzes, progress tracking, and verifiable certificates.

## Editions

| Feature | Lite ($99/yr) | Pro ($149/yr) |
|---------|:---:|:---:|
| Courses & Lessons | &#10003; | &#10003; |
| Quizzes (MC, T/F, short answer, matching) | &#10003; | &#10003; |
| Progress tracking | &#10003; | &#10003; |
| Basic certificates (HTML) | &#10003; | &#10003; |
| Dashboard widgets | &#10003; | &#10003; |
| Drip content (lesson scheduling) | -- | &#10003; |
| PDF certificates (DOMPDF) | -- | &#10003; |
| Craft Commerce integration | -- | &#10003; |
| Headcount membership gating | -- | &#10003; |
| Advanced analytics/reporting | -- | &#10003; |
| Course bundles | -- | &#10003; |

## Requirements

- Craft CMS 5.3.0+
- PHP 8.2+
- [DOMPDF](https://github.com/dompdf/dompdf) (optional, Pro PDF certificates)

## Installation

```bash
composer require justinholtweb/craft-diploma
php craft plugin/install diploma
```

## Configuration

All settings are available in the control panel under **Diploma > Settings**, or override them via `config/diploma.php`:

```php
<?php

return [
    'enableCertificates' => true,
    'enableCourseUrls' => false,
    'courseUriFormat' => 'courses/{slug}',
    'courseTemplate' => '',
    'autoEnrollOnPurchase' => true,
    'autoIssueCertificate' => true,
    'requirePassingQuiz' => false,
    'defaultPassingScore' => 70,
];
```

## Usage

### Courses & Lessons

Courses and Lessons are custom element types with full field layout support. Create and manage them from **Diploma > Courses** in the CP. Lessons are nested under their parent course and support drag-and-drop reordering.

Each course has:
- **Status** -- Draft, Published, or Archived
- **Difficulty** -- Beginner, Intermediate, or Advanced
- **Estimated duration** -- in minutes
- **Enrollment limit** -- optional cap on enrollments
- **Passing score** -- percentage required for completion

Each lesson has:
- **Type** -- Text, Video, or Mixed
- **Sort order** -- drag to reorder within a course
- **Prerequisite** -- require a previous lesson be completed first
- **Free preview** -- allow unenrolled users to access the lesson
- **Drip delay** (Pro) -- days after enrollment to unlock

### Quizzes

Quizzes are a separate element type managed under **Diploma > Quizzes**. Each quiz can be linked to a course, a specific lesson, or both. Supported question types:

- **Multiple Choice** -- one correct answer from several options
- **True/False** -- binary choice
- **Short Answer** -- text response, graded via exact match
- **Matching** -- pair items together

Quiz settings include passing score, time limit, max attempts, question randomization, and questions-per-attempt limits.

### Enrollments

Manage enrollments from **Diploma > Enrollments**. Users can be enrolled manually via the CP, or automatically via:
- Self-enrollment (front-end form)
- Craft Commerce purchase (Pro)
- Headcount subscription (Pro)

Enrollment statuses: Active, Completed, Cancelled, Expired.

An enrollment can carry an expiry date (set by the CSV import below). Once it passes, the learner loses access straight away — lessons lock and quizzes refuse them — and `diploma/enrollments/expire` marks the enrollment Expired the next time it runs. Reactivating an enrollment clears its expiry date.

### Certificates

When a user completes a course (and passes any required quizzes), a certificate is automatically issued with a unique verification code. Certificates can be:
- Viewed at a public verification URL
- Downloaded as PDF (Pro, requires DOMPDF)
- Customized via settings (title, body text, or a custom Twig template)

## Template Reference

### Template Variable: `craft.diploma`

```twig
{# Course queries (returns element queries) #}
{% set courses = craft.diploma.courses().status('published').all() %}
{% set course = craft.diploma.courses().slug('intro-to-php').one() %}

{# Lesson queries #}
{% set lessons = craft.diploma.lessons().courseId(course.id).all() %}

{# Enrollment & progress #}
{% set enrollment = craft.diploma.getEnrollment(course, currentUser) %}
{% set progress = craft.diploma.getCourseProgress(course, currentUser) %}
{{ progress.percentage }}%
{{ progress.completedLessons }} / {{ progress.totalLessons }}

{# Quiz results #}
{% set attempts = craft.diploma.getQuizAttempts(quiz, currentUser) %}
{% set bestScore = craft.diploma.getBestScore(quiz, currentUser) %}

{# Certificate #}
{% set certificate = craft.diploma.getCertificate(course, currentUser) %}
{% if certificate %}
    <a href="{{ certificate.verifyUrl }}">View Certificate</a>
{% endif %}

{# Access checks #}
{% if craft.diploma.isEnrolled(course, currentUser) %}
{% if craft.diploma.isLessonUnlocked(lesson, currentUser) %}
{% if craft.diploma.hasCompleted(course, currentUser) %}
```

### Twig Filters

```twig
{# Duration in minutes to human-readable: 90 -> "1h 30m" #}
{{ course.estimatedDuration|diplomaDuration }}

{# Float or percentage formatting: 0.75 -> "75%" #}
{{ progress.percentage|diplomaProgress }}

{# Seconds to human-readable: 3661 -> "1h 1m 1s" #}
{{ progress.totalTimeSpent|diplomaTimeSpent }}
```

### Course URLs

By default, courses have **no front-end URLs** — you query them with `craft.diploma.courses()` and render them in whatever templates you build.

To give published courses their own URLs, enable **Course URLs** under **Diploma > Settings** (or set `enableCourseUrls` in `config/diploma.php`):

- **Course URI Format** — the URI pattern, e.g. `courses/{slug}`
- **Course Template** — the template Craft renders for a course; it receives a `course` variable

Once enabled, each published course resolves to its own URL and `course.url` is available:

```twig
{% set courses = craft.diploma.courses().status('published').all() %}
<ul>
    {% for course in courses %}
        <li><a href="{{ course.url }}">{{ course.title }}</a></li>
    {% endfor %}
</ul>
```

> Courses saved before enabling this setting won't have a URI until they're re-saved. Open and save each one from the control panel — Craft generates the slug (from the title, if blank) and URI on save.

### Front-end Forms

**Self-enrollment:**

```twig
<form method="post">
    {{ csrfInput() }}
    {{ actionInput('diploma/progress/enroll') }}
    {{ redirectInput(course.url ?? '/courses/' ~ course.slug) }}
    <input type="hidden" name="courseId" value="{{ course.id }}">
    <button type="submit">Enroll Now</button>
</form>
```

**Mark lesson complete (AJAX):**

```twig
<form method="post" class="diploma-complete-lesson">
    {{ csrfInput() }}
    {{ actionInput('diploma/progress/complete-lesson') }}
    <input type="hidden" name="lessonId" value="{{ lesson.id }}">
    <button type="submit">Mark Complete</button>
</form>
```

**Submit a quiz:**

```twig
<form method="post">
    {{ csrfInput() }}
    {{ actionInput('diploma/quiz-taking/submit') }}
    {{ redirectInput((course.url ?? '/courses/' ~ course.slug) ~ '/quiz-results') }}
    <input type="hidden" name="quizId" value="{{ quiz.id }}">

    {% for question in quiz.getQuestions() %}
        <fieldset>
            <legend>{{ question.questionText }}</legend>
            {% for answer in question.answers %}
                <label>
                    <input type="radio"
                           name="responses[{{ question.id }}][answerId]"
                           value="{{ answer.id }}">
                    {{ answer.answerText }}
                </label>
            {% endfor %}
        </fieldset>
    {% endfor %}

    <button type="submit">Submit Quiz</button>
</form>
```

## Front-end API

All endpoints require a logged-in user.

| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/diploma/api/enroll` | Enroll current user in a course |
| POST | `/diploma/api/complete-lesson` | Mark a lesson complete |
| POST | `/diploma/api/submit-quiz` | Submit quiz answers for grading |
| GET | `/diploma/api/progress/<courseId>` | Get progress for a course |
| GET | `/diploma/certificate/verify/<code>` | Public certificate verification |
| GET | `/diploma/certificate/download/<code>` | Download PDF certificate (Pro) |

## Console Commands

Everything here also works from the CP one learner at a time; the commands are for doing it in bulk, and from cron. Each takes `--dry-run` to report what it would do without writing anything, and `--course=<id or slug>` to limit it to one course. They exit non-zero if any row failed.

### Import enrollments

Moving learners over from another LMS:

```bash
php craft diploma/enrollments/import learners.csv --course=intro-to-craft --dry-run
php craft diploma/enrollments/import learners.csv --course=intro-to-craft --create-users
```

The first row of the CSV names the columns:

| Column | Required | Meaning |
|---|---|---|
| `email` | Yes | The learner's email address |
| `status` | No | `active` (default), `completed`, `cancelled` or `expired` |
| `date` | No | When they enrolled |
| `completed` | No | When they finished (for `completed` rows) |
| `expires` | No | When their access ends |

Dates are read in the system time zone, in any format PHP understands (`2025-01-15`, `2025-01-15 09:30`). Learners already enrolled (active or completed) are skipped, so a file can be imported twice; a cancelled or expired enrollment is reactivated. The course's enrollment limit still applies, and each row is logged in the activity log with the source `import`. A row for an email no user has is reported — unless you pass `--create-users`, which creates a pending user for it; they set a password with *Forgot password*, or you can send activation emails from **Users**.

Imported `completed` rows don't get a certificate on their own: run `diploma/certificates/reissue --missing` afterwards.

### Expire enrollments

```bash
php craft diploma/enrollments/expire
```

Marks every active enrollment whose expiry date has passed as Expired, logs it, and fires `Enrollments::EVENT_AFTER_EXPIRE`. Completed enrollments are left alone. Run it from cron, daily or hourly.

### Reissue certificates

```bash
php craft diploma/certificates/reissue --course=intro-to-craft
php craft diploma/certificates/reissue --missing
```

A certificate keeps a snapshot of the course title and the learner's name and email from the day it was issued. After renaming a course, a learner changing their name, or a template change that prints those values, `reissue` rebuilds the snapshots from the current data. Verification codes and issue dates are kept, so shared links and printed copies stay valid. `--missing` also issues a certificate to every completed enrollment that has none (imported completions, or courses finished while certificates were off).

### Drip unlocks (Pro)

```bash
php craft diploma/drip/unlock
```

Drip lessons open on their own — access is checked against the enrollment date on every request. This command is what lets something *happen* when one does: for each learner whose drip lesson has unlocked since the last run, it writes a "Lesson unlocked" activity-log entry and fires `DripContent::EVENT_AFTER_UNLOCK_LESSON` (a `LessonUnlockEvent` with `enrollmentId`, `userId`, `courseId`, `lessonId` and `unlockedAt`) — hook that to email the learner. Each unlock is announced once per enrollment. Unlocks older than `--lookback` days (7 by default) are ignored, so the first run doesn't announce months of old lessons. Run it from cron, hourly or daily. A lesson that also has a prerequisite stays locked until that's done; check `isLessonUnlocked()` before promising access.

## Commerce Integration (Pro)

Link courses to Craft Commerce products. When an order completes, buyers are automatically enrolled in the associated courses.

1. Navigate to **Diploma > Settings > Commerce**
2. Add a `diplomaCourses` relation field to your Commerce product field layout
3. When a customer purchases a product with linked courses, enrollment happens automatically

## Headcount Integration (Pro)

Gate course access behind Headcount membership plans.

1. Navigate to **Diploma > Settings > Headcount**
2. Map Headcount plans to Diploma courses
3. When a user subscribes to a plan, they are automatically enrolled
4. When a subscription expires or is cancelled, the enrollment is marked as expired

## Permissions

| Permission | Description |
|---|---|
| `diploma:accessPlugin` | Access the Diploma CP section |
| `diploma:manageCourses` | Create and edit courses and lessons |
| `diploma:deleteCourses` | Delete courses (nested under manageCourses) |
| `diploma:manageQuizzes` | Create and edit quizzes |
| `diploma:deleteQuizzes` | Delete quizzes (nested under manageQuizzes) |
| `diploma:manageEnrollments` | Manage user enrollments |
| `diploma:viewCertificates` | View issued certificates |
| `diploma:viewAnalytics` | View analytics dashboard (Pro) |
| `diploma:manageSettings` | Modify plugin settings |

## Database Tables

| Table | Purpose |
|-------|---------|
| `diploma_courses` | Course element content (FK to `elements`) |
| `diploma_lessons` | Lesson element content (FK to `elements`) |
| `diploma_quizzes` | Quiz element content (FK to `elements`) |
| `diploma_questions` | Quiz questions |
| `diploma_answers` | Answer options for questions |
| `diploma_enrollments` | User-course enrollments |
| `diploma_progress` | Per-lesson completion tracking |
| `diploma_quiz_attempts` | Quiz attempt records |
| `diploma_quiz_responses` | Individual question responses |
| `diploma_certificates` | Issued certificates with verification codes |
| `diploma_drip_schedules` | Drip content timing (Pro) |

## Support

- [GitHub Issues](https://github.com/justinholtweb/craft-diploma/issues)
