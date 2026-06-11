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
