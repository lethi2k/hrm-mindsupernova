<?php

namespace OrangeHRM\Payroll\Api\Model;

use OrangeHRM\Core\Api\V2\Serializer\ModelTrait;
use OrangeHRM\Core\Api\V2\Serializer\Normalizable;
use OrangeHRM\Entity\PayrollPayslip;

class PayrollPayslipModel implements Normalizable
{
    use ModelTrait;

    public function __construct(PayrollPayslip $entity)
    {
        $this->setEntity($entity);
        $this->setFilters([
            'id',
            'empNumber',
            'netSalary',
            'filePath',
            'fileFormat',
            'fileChecksum',
        ]);
    }
}
