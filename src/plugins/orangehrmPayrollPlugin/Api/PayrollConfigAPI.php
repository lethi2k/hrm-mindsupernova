<?php

namespace OrangeHRM\Payroll\Api;

use OrangeHRM\Core\Api\V2\Endpoint;
use OrangeHRM\Core\Api\V2\EndpointResourceResult;
use OrangeHRM\Core\Api\V2\EndpointResult;
use OrangeHRM\Core\Api\V2\ResourceEndpoint;
use OrangeHRM\Core\Api\V2\RequestParams;
use OrangeHRM\Core\Api\V2\Validator\ParamRule;
use OrangeHRM\Core\Api\V2\Validator\ParamRuleCollection;
use OrangeHRM\Core\Api\V2\Validator\Rule;
use OrangeHRM\Core\Api\V2\Validator\Rules;
use OrangeHRM\Core\Traits\ORM\EntityManagerHelperTrait;
use OrangeHRM\Entity\PayrollConfig;
use OrangeHRM\Payroll\Api\Model\PayrollConfigModel;
use OrangeHRM\Payroll\Traits\Service\PayrollServiceTrait;

class PayrollConfigAPI extends Endpoint implements ResourceEndpoint
{
    use EntityManagerHelperTrait;
    use PayrollServiceTrait;

    public const PARAM_SCHEDULE_ENABLED = 'scheduleEnabled';
    public const PARAM_SCHEDULE_DAY = 'scheduleDay';
    public const PARAM_SCHEDULE_HOUR = 'scheduleHour';
    public const PARAM_MAX_RETRIES = 'maxRetries';
    public const PARAM_PWD = 'passwordProtectedFiles';
    public const PARAM_SIGNED = 'signedDownload';
    public const PARAM_LOCALE = 'defaultEmailLocale';
    public const PARAM_SUBJECT = 'defaultSubject';
    public const PARAM_BODY = 'defaultBody';

    public function getOne(): EndpointResult
    {
        $id = $this->getRequestParams()->getInt(RequestParams::PARAM_TYPE_ATTRIBUTE, 'id');
        if ($id !== 1) {
            $this->throwNotImplementedException();
        }
        $c = $this->getPayrollService()->getOrCreateConfig();
        return new EndpointResourceResult(PayrollConfigModel::class, $c);
    }

    public function getValidationRuleForGetOne(): ParamRuleCollection
    {
        return new ParamRuleCollection(
            new ParamRule('id', new Rule(Rules::POSITIVE))
        );
    }

    public function update(): EndpointResult
    {
        $id = $this->getRequestParams()->getInt(RequestParams::PARAM_TYPE_ATTRIBUTE, 'id');
        if ($id !== 1) {
            $this->throwNotImplementedException();
        }
        $c = $this->getPayrollService()->getOrCreateConfig();
        $params = $this->getRequestParams();
        if ($params->has(RequestParams::PARAM_TYPE_BODY, self::PARAM_SCHEDULE_ENABLED)) {
            $c->setScheduleEnabled($params->getBoolean(RequestParams::PARAM_TYPE_BODY, self::PARAM_SCHEDULE_ENABLED));
        }
        if ($params->has(RequestParams::PARAM_TYPE_BODY, self::PARAM_SCHEDULE_DAY)) {
            $c->setScheduleDay($params->getInt(RequestParams::PARAM_TYPE_BODY, self::PARAM_SCHEDULE_DAY));
        }
        if ($params->has(RequestParams::PARAM_TYPE_BODY, self::PARAM_SCHEDULE_HOUR)) {
            $c->setScheduleHour($params->getInt(RequestParams::PARAM_TYPE_BODY, self::PARAM_SCHEDULE_HOUR));
        }
        if ($params->has(RequestParams::PARAM_TYPE_BODY, self::PARAM_MAX_RETRIES)) {
            $c->setMaxRetries($params->getInt(RequestParams::PARAM_TYPE_BODY, self::PARAM_MAX_RETRIES));
        }
        if ($params->has(RequestParams::PARAM_TYPE_BODY, self::PARAM_PWD)) {
            $c->setPasswordProtectedFiles($params->getBoolean(RequestParams::PARAM_TYPE_BODY, self::PARAM_PWD));
        }
        if ($params->has(RequestParams::PARAM_TYPE_BODY, self::PARAM_SIGNED)) {
            $c->setSignedDownload($params->getBoolean(RequestParams::PARAM_TYPE_BODY, self::PARAM_SIGNED));
        }
        if ($params->has(RequestParams::PARAM_TYPE_BODY, self::PARAM_LOCALE)) {
            $c->setDefaultEmailLocale($params->getString(RequestParams::PARAM_TYPE_BODY, self::PARAM_LOCALE));
        }
        if ($params->has(RequestParams::PARAM_TYPE_BODY, self::PARAM_SUBJECT)) {
            $c->setDefaultSubject($params->getString(RequestParams::PARAM_TYPE_BODY, self::PARAM_SUBJECT));
        }
        if ($params->has(RequestParams::PARAM_TYPE_BODY, self::PARAM_BODY)) {
            $c->setDefaultBody($params->getString(RequestParams::PARAM_TYPE_BODY, self::PARAM_BODY));
        }
        $this->getEntityManager()->flush();
        return new EndpointResourceResult(PayrollConfigModel::class, $c);
    }

    public function getValidationRuleForUpdate(): ParamRuleCollection
    {
        return new ParamRuleCollection();
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
