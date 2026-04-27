<?php

namespace OrangeHRM\Payroll\Controller;

use OrangeHRM\Core\Controller\AbstractModuleController;
use OrangeHRM\Core\Traits\UserRoleManagerTrait;
use OrangeHRM\Framework\Http\RedirectResponse;

class PayrollModuleController extends AbstractModuleController
{
    use UserRoleManagerTrait;

    public function handle(): RedirectResponse
    {
        $defaultPath = $this->getUserRoleManager()->getModuleDefaultPage('payroll');
        return $this->redirect($defaultPath);
    }
}
