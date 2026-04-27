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

namespace OrangeHRM\Payroll\Api;

use OrangeHRM\Core\Api\CommonParams;
use OrangeHRM\Core\Api\V2\CollectionEndpoint;
use OrangeHRM\Core\Api\V2\Endpoint;
use OrangeHRM\Core\Api\V2\EndpointCollectionResult;
use OrangeHRM\Core\Api\V2\EndpointResult;
use OrangeHRM\Core\Api\V2\ParameterBag;
use OrangeHRM\Core\Api\V2\RequestParams;
use OrangeHRM\Core\Api\V2\Validator\ParamRule;
use OrangeHRM\Core\Api\V2\Validator\ParamRuleCollection;
use OrangeHRM\Core\Api\V2\Validator\Rule;
use OrangeHRM\Core\Api\V2\Validator\Rules;
use OrangeHRM\Payroll\Api\Model\PayrollEmailLogModel;
use OrangeHRM\Payroll\Dao\PayrollDao;

class PayrollEmailLogAPI extends Endpoint implements CollectionEndpoint
{
    public const PARAM_RUN = 'runId';
    private ?PayrollDao $payrollDao = null;

    public function getPayrollDao(): PayrollDao
    {
        if ($this->payrollDao === null) {
            $this->payrollDao = new PayrollDao();
        }
        return $this->payrollDao;
    }

    public function getAll(): EndpointResult
    {
        $run = $this->getRequestParams()->getInt(RequestParams::PARAM_TYPE_QUERY, self::PARAM_RUN);
        $offset = $this->getRequestParams()->getInt(RequestParams::PARAM_TYPE_QUERY, CommonParams::PARAMETER_OFFSET, 0);
        $limit = $this->getRequestParams()->getInt(RequestParams::PARAM_TYPE_QUERY, CommonParams::PARAMETER_LIMIT, 200);
        $list = $this->getPayrollDao()->getEmailLogsForRun($run, $offset, $limit);
        $c = $this->getPayrollDao()->countEmailLogsForRun($run);
        return new EndpointCollectionResult(
            PayrollEmailLogModel::class,
            $list,
            new ParameterBag([CommonParams::PARAMETER_TOTAL => $c])
        );
    }

    public function getValidationRuleForGetAll(): ParamRuleCollection
    {
        return new ParamRuleCollection(
            new ParamRule(self::PARAM_RUN, new Rule(Rules::POSITIVE)),
            $this->getValidationDecorator()->notRequiredParamRule(
                new ParamRule(CommonParams::PARAMETER_OFFSET, new Rule(Rules::INT_VAL), new Rule(Rules::ZERO_OR_POSITIVE))
            ),
            $this->getValidationDecorator()->notRequiredParamRule(
                new ParamRule(CommonParams::PARAMETER_LIMIT, new Rule(Rules::INT_VAL))
            )
        );
    }

    public function create(): EndpointResult
    {
        $this->throwNotImplementedException();
    }

    public function getValidationRuleForCreate(): ParamRuleCollection
    {
        $this->throwNotImplementedException();
    }

    public function delete(): EndpointResult
    {
        $this->throwNotImplementedException();
    }

    public function getValidationRuleForDelete(): ParamRuleCollection
    {
        $this->throwNotImplementedException();
    }
}
