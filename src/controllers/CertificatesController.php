<?php

namespace justinholtweb\diploma\controllers;

use Craft;
use craft\web\Controller;
use justinholtweb\diploma\models\Edition;
use justinholtweb\diploma\Plugin;
use yii\web\NotFoundHttpException;
use yii\web\Response;

class CertificatesController extends Controller
{
    protected array|bool|int $allowAnonymous = ['verify', 'download'];

    public function beforeAction($action): bool
    {
        if (!parent::beforeAction($action)) {
            return false;
        }

        if (!in_array($action->id, ['verify', 'download'])) {
            $this->requirePermission('diploma:viewCertificates');
        }

        return true;
    }

    public function actionIndex(): Response
    {
        $certificates = Plugin::getInstance()->certificates->getAllCertificates();

        return $this->renderTemplate('diploma/certificates/_index', [
            'certificates' => $certificates,
        ]);
    }

    public function actionVerify(string $code): Response
    {
        $certificate = Plugin::getInstance()->certificates->getCertificateByCode($code);

        if (!$certificate) {
            throw new NotFoundHttpException('Certificate not found.');
        }

        $course = Plugin::getInstance()->courses->getById($certificate->courseId);
        $user = Craft::$app->getUsers()->getUserById($certificate->userId);

        return $this->renderTemplate('diploma/certificates/_verify', [
            'certificate' => $certificate,
            'course' => $course,
            'user' => $user,
        ]);
    }

    public function actionDownload(string $code): Response
    {
        $this->requireLogin();
        Edition::requiresPro('PDF certificates');

        $certificate = Plugin::getInstance()->certificates->getCertificateByCode($code);
        if (!$certificate) {
            throw new NotFoundHttpException('Certificate not found.');
        }

        $pdf = Plugin::getInstance()->certificates->generatePdf($certificate);
        if (!$pdf) {
            throw new \RuntimeException('PDF generation is not available. Ensure dompdf is installed.');
        }

        $response = Craft::$app->getResponse();
        $response->content = $pdf;
        $response->format = Response::FORMAT_RAW;
        $response->getHeaders()->set('Content-Type', 'application/pdf');
        $response->getHeaders()->set('Content-Disposition', 'attachment; filename="certificate-' . $certificate->verificationCode . '.pdf"');

        return $response;
    }
}
