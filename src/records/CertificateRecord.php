<?php

namespace justinholtweb\diploma\records;

use craft\db\ActiveRecord;

/**
 * @property int $id
 * @property int $enrollmentId
 * @property int $userId
 * @property int $courseId
 * @property string $verificationCode
 * @property string $issuedAt
 * @property string|null $templateHandle
 * @property string|null $metadata
 * @property string $dateCreated
 * @property string $uid
 */
class CertificateRecord extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%diploma_certificates}}';
    }
}
