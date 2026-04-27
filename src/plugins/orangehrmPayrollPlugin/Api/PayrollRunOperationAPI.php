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

use Exception;
use OrangeHRM\Core\Api\V2\CollectionEndpoint;
use OrangeHRM\Core\Api\V2\Endpoint;
use OrangeHRM\Core\Api\V2\EndpointResult;
use OrangeHRM\Core\Api\V2\EndpointResourceResult;
use OrangeHRM\Core\Api\V2\Model\ArrayModel;
use OrangeHRM\Core\Api\V2\RequestParams;
use OrangeHRM\Core\Api\V2\Validator\ParamRule;
use OrangeHRM\Core\Api\V2\Validator\ParamRuleCollection;
use OrangeHRM\Core\Api\V2\Validator\Rule;
use OrangeHRM\Core\Api\V2\Validator\Rules;
use OrangeHRM\Core\Traits\Auth\AuthUserTrait;
use OrangeHRM\Entity\PayrollPayslip;
use OrangeHRM\Entity\PayrollRun;
use OrangeHRM\Payroll\Dao\PayrollDao;
use OrangeHRM\Payroll\Traits\Service\PayrollServiceTrait;

class PayrollRunOperationAPI extends Endpoint implements CollectionEndpoint
{
    use AuthUserTrait;
    use PayrollServiceTrait;

    public const OPERATION = 'action';
    public const FORMAT = 'fileFormat';
    public const OP_SEND = 'send';
    public const OP_SYNC = 'syncQueue';
    public const OP_CONFIRM_REVIEW = 'confirmReview';
    public const OP_GET_REVIEW_SNAPSHOT = 'getReviewSnapshot';
    public const REVIEW_ROWS = 'reviewRows';
    public const REVIEW_ROWS_JSON = 'reviewRowsJson';

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
        $this->throwNotImplementedException();
    }

    public function getValidationRuleForGetAll(): ParamRuleCollection
    {
        $this->throwNotImplementedException();
    }

    public function create(): EndpointResult
    {
        $id = $this->getRequestParams()->getInt(RequestParams::PARAM_TYPE_ATTRIBUTE, 'id');
        $action = $this->getRequestParams()->getString(RequestParams::PARAM_TYPE_BODY, self::OPERATION);
        $this->throwRecordNotFoundExceptionIfNotExist($this->getPayrollDao()->getRun($id), PayrollRun::class);
        try {
            if ($action === self::OP_CONFIRM_REVIEW) {
                $rows = $this->getRequestParams()->getArray(RequestParams::PARAM_TYPE_BODY, self::REVIEW_ROWS, []);
                if ($rows === []) {
                    $rowsJson = $this->getRequestParams()->getString(
                        RequestParams::PARAM_TYPE_BODY,
                        self::REVIEW_ROWS_JSON,
                        '[]'
                    );
                    $decoded = json_decode($rowsJson, true);
                    $rows = is_array($decoded) ? $decoded : [];
                }
                $r = $this->getPayrollService()->confirmReview($id, (int) $this->getAuthUser()->getUserId(), $rows);
            } elseif ($action === self::OP_SEND) {
                $fmt = $this->getRequestParams()->getString(
                    RequestParams::PARAM_TYPE_BODY,
                    self::FORMAT,
                    PayrollPayslip::FORMAT_XLSX
                );
                if ($fmt !== PayrollPayslip::FORMAT_XLSX && $fmt !== PayrollPayslip::FORMAT_PDF) {
                    $fmt = PayrollPayslip::FORMAT_XLSX;
                }
                $r = $this->getPayrollService()->sendPayslips(
                    $id,
                    $fmt,
                    (int) $this->getAuthUser()->getUserId()
                );
            } elseif ($action === self::OP_SYNC) {
                $n = $this->getPayrollService()->syncMailQueueToLogs();
                return new EndpointResourceResult(ArrayModel::class, ['synced' => $n, 'runId' => $id]);
            } elseif ($action === self::OP_GET_REVIEW_SNAPSHOT) {
                $snapshot = $this->getPayrollDao()->getPayslipAmountMapForRun($id);
                return new EndpointResourceResult(ArrayModel::class, ['runId' => $id, 'snapshot' => $snapshot]);
            } else {
                $this->throwNotImplementedException();
            }
        } catch (Exception $exception) {
            throw $this->getBadRequestException($exception->getMessage());
        }
        return new EndpointResourceResult(
            \OrangeHRM\Payroll\Api\Model\PayrollRunModel::class,
            $r
        );
    }

    public function getValidationRuleForCreate(): ParamRuleCollection
    {
        return new ParamRuleCollection(
            new ParamRule('id', new Rule(Rules::POSITIVE)),
            new ParamRule(
                self::OPERATION,
                new Rule(Rules::STRING_TYPE)
            ),
            $this->getValidationDecorator()->notRequiredParamRule(
                new ParamRule(
                    self::FORMAT,
                    new Rule(Rules::STRING_TYPE)
                )
            ),
            $this->getValidationDecorator()->notRequiredParamRule(
                new ParamRule(
                    self::REVIEW_ROWS,
                    new Rule(Rules::ARRAY_TYPE)
                )
            ),
            $this->getValidationDecorator()->notRequiredParamRule(
                new ParamRule(
                    self::REVIEW_ROWS_JSON,
                    new Rule(Rules::STRING_TYPE)
                )
            )
        );
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
