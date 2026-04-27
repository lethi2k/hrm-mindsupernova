<?php

namespace OrangeHRM\Payroll\Api\Model;

use OrangeHRM\Core\Api\V2\Serializer\ModelTrait;
use OrangeHRM\Core\Api\V2\Serializer\Normalizable;

class PayrollPayslipReviewModel implements Normalizable
{
    use ModelTrait;

    public function __construct(array $entity)
    {
        $this->setEntity($entity);
        $this->setFilters([
            'id',
            'empNumber',
            'employeeId',
            'fullName',
            'nationalId',
            'jobTitle',
            'baseSalary',
            'standardWorkingDays',
            'actualWorkingDays',
            'actualSalary',
            'overtime',
            'allowance',
            'totalSalary',
            'netSalary',
            'filePath',
            'fileFormat',
            'fileChecksum',
        ]);
    }
}
