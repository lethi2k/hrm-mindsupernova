<?php

/**
 * OrangeHRM is a comprehensive Human Resource Management (HRM) System that captures
 * all the essential functionalities required for any enterprise.
 * Copyright (C) 2006 OrangeHRM Inc., http://www.orangehrm.com
 *
 * OrangeHRM is free software: you can redistribute it and/or modify it under the terms of
 * the GNU General Public License as published by the Free Software Foundation, either
 * version 3 of the License, or (at your option) any later version.
 *
 * OrangeHRM is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
 * without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 * See the GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along with OrangeHRM.
 * If not, see <https://www.gnu.org/licenses/>.
 */

namespace OrangeHRM\Leave\Controller;

use OrangeHRM\Core\Authorization\Controller\CapableViewController;
use OrangeHRM\Core\Controller\AbstractVueController;
use OrangeHRM\Core\Traits\Auth\AuthUserTrait;
use OrangeHRM\Core\Traits\Service\DateTimeHelperTrait;
use OrangeHRM\Core\Traits\UserRoleManagerTrait;
use OrangeHRM\Core\Vue\Component;
use OrangeHRM\Core\Vue\Prop;
use OrangeHRM\Framework\Http\Request;
use OrangeHRM\Leave\Traits\Service\LeaveTypeServiceTrait;
use OrangeHRM\Leave\Traits\Service\WorkScheduleServiceTrait;

class LogLeaveController extends AbstractVueController implements CapableViewController
{
    use WorkScheduleServiceTrait;
    use LeaveTypeServiceTrait;
    use DateTimeHelperTrait;
    use AuthUserTrait;
    use UserRoleManagerTrait;

    /**
     * @inheritDoc
     */
    public function preRender(Request $request): void
    {
        $component = new Component('leave-log');

        $workShiftStartEndTime = $this->getWorkScheduleService()
            ->getWorkSchedule($this->getAuthUser()->getEmpNumber())
            ->getWorkShiftStartEndTime();
        $workShift = [
            'startTime' => $this->getDateTimeHelper()
                ->formatDateTimeToTimeString($workShiftStartEndTime->getStartTime()),
            'endTime' => $this->getDateTimeHelper()
                ->formatDateTimeToTimeString($workShiftStartEndTime->getEndTime()),
        ];
        $leaveTypeOptions = [];
        foreach ($this->getLeaveTypeService()->getLeaveTypeDao()->getLeaveTypeList() as $leaveType) {
            $leaveTypeOptions[] = [
                'id' => $leaveType->getId(),
                'label' => $leaveType->getName(),
            ];
        }
        $component->addProp(new Prop('work-shift', Prop::TYPE_OBJECT, $workShift));
        $component->addProp(new Prop('leave-types', Prop::TYPE_ARRAY, $leaveTypeOptions));
        $this->setComponent($component);
    }

    public function isCapable(Request $request): bool
    {
        $roles = $this->getUserRoleManager()->getUserRolesForAuthUser();
        $hasEssRole = false;
        foreach ($roles as $role) {
            if ($role->getName() === 'Admin') {
                return false;
            }
            if ($role->getName() === 'ESS') {
                $hasEssRole = true;
            }
        }
        return $hasEssRole;
    }
}
