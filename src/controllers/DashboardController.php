<?php

namespace justinholtweb\diploma\controllers;

use Craft;
use craft\web\Controller;
use justinholtweb\diploma\elements\Course;
use justinholtweb\diploma\models\Edition;
use justinholtweb\diploma\Plugin;
use justinholtweb\diploma\records\EnrollmentRecord;
use yii\web\Response;

class DashboardController extends Controller
{
    public function beforeAction($action): bool
    {
        if (!parent::beforeAction($action)) {
            return false;
        }

        $this->requirePermission('diploma:accessPlugin');

        return true;
    }

    public function actionIndex(): Response
    {
        $totalCourses = Course::find()->count();
        $publishedCourses = Course::find()->courseStatus('published')->count();
        $totalEnrollments = (int)EnrollmentRecord::find()->count();
        $activeEnrollments = (int)EnrollmentRecord::find()
            ->where(['enrollmentStatus' => 'active'])
            ->count();

        $recentEnrollments = EnrollmentRecord::find()
            ->orderBy(['enrolledAt' => SORT_DESC])
            ->limit(10)
            ->all();

        return $this->renderTemplate('diploma/dashboard/_index', [
            'totalCourses' => $totalCourses,
            'publishedCourses' => $publishedCourses,
            'totalEnrollments' => $totalEnrollments,
            'activeEnrollments' => $activeEnrollments,
            'recentEnrollments' => $recentEnrollments,
        ]);
    }

    public function actionAnalytics(): Response
    {
        $this->requirePermission('diploma:viewAnalytics');
        Edition::requiresPro('Analytics');

        return $this->renderTemplate('diploma/dashboard/_analytics', []);
    }
}
