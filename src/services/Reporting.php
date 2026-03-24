<?php

namespace justinholtweb\diploma\services;

use Craft;
use craft\base\Component;
use justinholtweb\diploma\elements\Course;
use justinholtweb\diploma\records\CertificateRecord;
use justinholtweb\diploma\records\EnrollmentRecord;
use justinholtweb\diploma\records\QuizAttemptRecord;

class Reporting extends Component
{
    public function getOverviewStats(): array
    {
        return [
            'totalCourses' => (int)Course::find()->count(),
            'publishedCourses' => (int)Course::find()->courseStatus('published')->count(),
            'totalEnrollments' => (int)EnrollmentRecord::find()->count(),
            'activeEnrollments' => (int)EnrollmentRecord::find()->where(['enrollmentStatus' => 'active'])->count(),
            'completedEnrollments' => (int)EnrollmentRecord::find()->where(['enrollmentStatus' => 'completed'])->count(),
            'totalCertificates' => (int)CertificateRecord::find()->count(),
            'totalQuizAttempts' => (int)QuizAttemptRecord::find()->count(),
            'passedQuizAttempts' => (int)QuizAttemptRecord::find()->where(['passed' => true])->count(),
        ];
    }

    public function getCourseStats(int $courseId): array
    {
        $totalEnrollments = (int)EnrollmentRecord::find()
            ->where(['courseId' => $courseId])
            ->count();

        $activeEnrollments = (int)EnrollmentRecord::find()
            ->where(['courseId' => $courseId, 'enrollmentStatus' => 'active'])
            ->count();

        $completedEnrollments = (int)EnrollmentRecord::find()
            ->where(['courseId' => $courseId, 'enrollmentStatus' => 'completed'])
            ->count();

        $completionRate = $totalEnrollments > 0
            ? round(($completedEnrollments / $totalEnrollments) * 100, 1)
            : 0;

        $avgScore = QuizAttemptRecord::find()
            ->innerJoin('{{%diploma_quizzes}}', '{{%diploma_quiz_attempts}}.[[quizId]] = {{%diploma_quizzes}}.[[id]]')
            ->where(['{{%diploma_quizzes}}.courseId' => $courseId])
            ->andWhere(['not', ['{{%diploma_quiz_attempts}}.completedAt' => null]])
            ->average('{{%diploma_quiz_attempts}}.score');

        return [
            'totalEnrollments' => $totalEnrollments,
            'activeEnrollments' => $activeEnrollments,
            'completedEnrollments' => $completedEnrollments,
            'completionRate' => $completionRate,
            'averageQuizScore' => $avgScore ? round((float)$avgScore, 1) : null,
        ];
    }

    public function getEnrollmentsByMonth(int $months = 12): array
    {
        $data = [];
        $now = new \DateTime();

        for ($i = $months - 1; $i >= 0; $i--) {
            $date = (clone $now)->modify("-{$i} months");
            $start = $date->format('Y-m-01 00:00:00');
            $end = $date->format('Y-m-t 23:59:59');

            $count = (int)EnrollmentRecord::find()
                ->where(['>=', 'enrolledAt', $start])
                ->andWhere(['<=', 'enrolledAt', $end])
                ->count();

            $data[] = [
                'month' => $date->format('M Y'),
                'count' => $count,
            ];
        }

        return $data;
    }
}
