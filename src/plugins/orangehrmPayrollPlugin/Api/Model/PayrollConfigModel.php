<?php

namespace OrangeHRM\Payroll\Api\Model;

use OrangeHRM\Core\Api\V2\Serializer\ModelTrait;
use OrangeHRM\Core\Api\V2\Serializer\Normalizable;
use OrangeHRM\Entity\PayrollConfig;

class PayrollConfigModel implements Normalizable
{
    use ModelTrait;

    public function __construct(PayrollConfig $c)
    {
        $this->setEntity($c);
        $this->setFilters([
            'id',
            'scheduleEnabled',
            'scheduleDay',
            'scheduleHour',
            'maxRetries',
            'passwordProtectedFiles',
            'signedDownload',
            'defaultEmailLocale',
            'defaultSubject',
            'defaultBody',
        ]);
    }
}
