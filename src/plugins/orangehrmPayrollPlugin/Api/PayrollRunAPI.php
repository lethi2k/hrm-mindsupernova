<?php

namespace OrangeHRM\Payroll\Api;

use Exception;
use OrangeHRM\Core\Api\CommonParams;
use OrangeHRM\Core\Api\V2\CrudEndpoint;
use OrangeHRM\Core\Api\V2\Endpoint;
use OrangeHRM\Core\Api\V2\EndpointCollectionResult;
use OrangeHRM\Core\Api\V2\EndpointResourceResult;
use OrangeHRM\Core\Api\V2\EndpointResult;
use OrangeHRM\Core\Api\V2\Model\ArrayModel;
use OrangeHRM\Core\Api\V2\ParameterBag;
use OrangeHRM\Core\Api\V2\RequestParams;
use OrangeHRM\Core\Api\V2\Validator\ParamRule;
use OrangeHRM\Core\Api\V2\Validator\ParamRuleCollection;
use OrangeHRM\Core\Api\V2\Validator\Rule;
use OrangeHRM\Core\Api\V2\Validator\Rules;
use OrangeHRM\Core\Traits\Auth\AuthUserTrait;
use OrangeHRM\Core\Traits\ORM\EntityManagerHelperTrait;
use OrangeHRM\Entity\PayrollRun;
use OrangeHRM\Payroll\Api\Model\PayrollRunModel;
use OrangeHRM\Payroll\Dao\PayrollDao;
use OrangeHRM\Payroll\Traits\Service\PayrollServiceTrait;

class PayrollRunAPI extends Endpoint implements CrudEndpoint
{
    use EntityManagerHelperTrait;
    use AuthUserTrait;
    use PayrollServiceTrait;

    public const PARAMETER_YEAR_MONTH = 'yearMonth';
    public const PARAMETER_IDS = 'ids';
    public const PARAM_SORT = 'sort';

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
        $offset = $this->getRequestParams()->getInt(RequestParams::PARAM_TYPE_QUERY, CommonParams::PARAMETER_OFFSET, 0);
        $limit = $this->getRequestParams()->getInt(RequestParams::PARAM_TYPE_QUERY, CommonParams::PARAMETER_LIMIT, 50);
        $items = $this->getPayrollDao()->getPayrollRunList($offset, $limit);
        $count = $this->getPayrollDao()->getPayrollRunCount();
        return new EndpointCollectionResult(
            PayrollRunModel::class,
            $items,
            new ParameterBag([CommonParams::PARAMETER_TOTAL => $count])
        );
    }

    public function getValidationRuleForGetAll(): ParamRuleCollection
    {
        return new ParamRuleCollection(
            $this->getValidationDecorator()->notRequiredParamRule(
                new ParamRule(
                    CommonParams::PARAMETER_OFFSET,
                    new Rule(Rules::INT_VAL),
                    new Rule(Rules::ZERO_OR_POSITIVE)
                )
            ),
            $this->getValidationDecorator()->notRequiredParamRule(
                new ParamRule(
                    CommonParams::PARAMETER_LIMIT,
                    new Rule(Rules::INT_VAL)
                )
            )
        );
    }

    public function getOne(): EndpointResult
    {
        $id = $this->getRequestParams()->getInt(RequestParams::PARAM_TYPE_ATTRIBUTE, 'id');
        $run = $this->getPayrollDao()->getRun($id);
        $this->throwRecordNotFoundExceptionIfNotExist($run, PayrollRun::class);
        return new EndpointResourceResult(PayrollRunModel::class, $run);
    }

    public function getValidationRuleForGetOne(): ParamRuleCollection
    {
        return new ParamRuleCollection(
            new ParamRule('id', new Rule(Rules::POSITIVE))
        );
    }

    public function create(): EndpointResult
    {
        $ym = $this->getRequestParams()->getString(RequestParams::PARAM_TYPE_BODY, self::PARAMETER_YEAR_MONTH);
        try {
            $run = $this->getPayrollService()->createRun(
                $ym,
                (int) $this->getAuthUser()->getUserId()
            );
        } catch (Exception $exception) {
            throw $this->getBadRequestException($exception->getMessage());
        }
        return new EndpointResourceResult(PayrollRunModel::class, $run);
    }

    public function getValidationRuleForCreate(): ParamRuleCollection
    {
        return new ParamRuleCollection(
            $this->getValidationDecorator()->requiredParamRule(
                new ParamRule(
                    self::PARAMETER_YEAR_MONTH,
                    new Rule(Rules::REGEX, ['/^\d{4}-(0[1-9]|1[0-2])$/'])
                )
            )
        );
    }

    public function update(): EndpointResult
    {
        $this->throwNotImplementedException();
    }

    public function getValidationRuleForUpdate(): ParamRuleCollection
    {
        return new ParamRuleCollection();
    }

    public function delete(): EndpointResult
    {
        $ids = $this->getRequestParams()->getArray(RequestParams::PARAM_TYPE_BODY, self::PARAMETER_IDS, []);
        $deletable = $this->getPayrollDao()->getPayrollRunIdsInDraft($ids);
        $deleted = [];
        foreach ($deletable as $id) {
            $run = $this->getPayrollDao()->getRun($id);
            if ($run !== null) {
                $this->getPayrollDao()->removePayrollRun($run);
                $deleted[] = $id;
            }
        }
        $this->getEntityManager()->flush();
        return new EndpointResourceResult(ArrayModel::class, $deleted);
    }

    public function getValidationRuleForDelete(): ParamRuleCollection
    {
        return new ParamRuleCollection(
            new ParamRule(
                self::PARAMETER_IDS,
                new Rule(Rules::INT_ARRAY)
            )
        );
    }
}
