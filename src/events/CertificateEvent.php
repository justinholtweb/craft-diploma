<?php

namespace justinholtweb\diploma\events;

use justinholtweb\diploma\models\Certificate;
use yii\base\Event;

/**
 * Fired after a certificate is issued for a completed course.
 */
class CertificateEvent extends Event
{
    /**
     * @var Certificate The certificate that was issued.
     */
    public Certificate $certificate;
}
