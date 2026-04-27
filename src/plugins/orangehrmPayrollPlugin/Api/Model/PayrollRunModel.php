<?php

namespace OrangeHRM\Payroll\Api\Model;

use OrangeHRM\Core\Api\V2\Serializer\ModelTrait;
use OrangeHRM\Core\Api\V2\Serializer\Normalizable;
use OrangeHRM\Entity\PayrollRun;

class PayrollRunModel implements Normalizable
{
    use ModelTrait;

    public function __construct(PayrollRun $entity)
    {
        $this->setEntity($entity);
        $this->setFilters([
            'id',
            'yearMonth',
            'status',
            'createdBy',
            'reviewedBy',
            'reviewedAt',
            ['isReviewConfirmed'],
            'createdAt',
            'updatedAt',
        ]);
        $this->setAttributeNames([
            'id',
            'yearMonth',
            'status',
            'createdBy',
            'reviewedBy',
            'reviewedAt',
            'reviewConfirmed',
            'createdAt',
            'updatedAt',
        ]);
    }
}
