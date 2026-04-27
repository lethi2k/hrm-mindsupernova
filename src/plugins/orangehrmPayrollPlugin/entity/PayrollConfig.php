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

namespace OrangeHRM\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Table(name="ohrm_payroll_config")
 * @ORM\Entity
 */
class PayrollConfig
{
    /**
     * @ORM\Column(name="id", type="integer")
     * @ORM\Id
     */
    private int $id = 1;

    /**
     * @ORM\Column(name="schedule_enabled", type="boolean")
     */
    private bool $scheduleEnabled = false;

    /**
     * @ORM\Column(name="schedule_day", type="integer")
     */
    private int $scheduleDay = 1;

    /**
     * @ORM\Column(name="schedule_hour", type="integer")
     */
    private int $scheduleHour = 2;

    /**
     * @ORM\Column(name="max_retries", type="integer")
     */
    private int $maxRetries = 5;

    /**
     * @ORM\Column(name="password_protected_files", type="boolean")
     */
    private bool $passwordProtectedFiles = false;

    /**
     * @ORM\Column(name="signed_download", type="boolean")
     */
    private bool $signedDownload = false;

    /**
     * @ORM\Column(name="default_email_locale", type="string", length=10)
     */
    private string $defaultEmailLocale = 'vi_VN';

    /**
     * @ORM\Column(name="default_subject", type="string", length=500)
     */
    private string $defaultSubject = '';

    /**
     * @ORM\Column(name="default_body", type="text")
     */
    private string $defaultBody = '';

    public function getId(): int
    {
        return $this->id;
    }

    public function setId(int $id): void
    {
        $this->id = $id;
    }

    public function isScheduleEnabled(): bool
    {
        return $this->scheduleEnabled;
    }

    public function setScheduleEnabled(bool $scheduleEnabled): void
    {
        $this->scheduleEnabled = $scheduleEnabled;
    }

    public function getScheduleDay(): int
    {
        return $this->scheduleDay;
    }

    public function setScheduleDay(int $scheduleDay): void
    {
        $this->scheduleDay = $scheduleDay;
    }

    public function getScheduleHour(): int
    {
        return $this->scheduleHour;
    }

    public function setScheduleHour(int $scheduleHour): void
    {
        $this->scheduleHour = $scheduleHour;
    }

    public function getMaxRetries(): int
    {
        return $this->maxRetries;
    }

    public function setMaxRetries(int $maxRetries): void
    {
        $this->maxRetries = $maxRetries;
    }

    public function isPasswordProtectedFiles(): bool
    {
        return $this->passwordProtectedFiles;
    }

    public function setPasswordProtectedFiles(bool $passwordProtectedFiles): void
    {
        $this->passwordProtectedFiles = $passwordProtectedFiles;
    }

    public function isSignedDownload(): bool
    {
        return $this->signedDownload;
    }

    public function setSignedDownload(bool $signedDownload): void
    {
        $this->signedDownload = $signedDownload;
    }

    public function getDefaultEmailLocale(): string
    {
        return $this->defaultEmailLocale;
    }

    public function setDefaultEmailLocale(string $defaultEmailLocale): void
    {
        $this->defaultEmailLocale = $defaultEmailLocale;
    }

    public function getDefaultSubject(): string
    {
        return $this->defaultSubject;
    }

    public function setDefaultSubject(string $defaultSubject): void
    {
        $this->defaultSubject = $defaultSubject;
    }

    public function getDefaultBody(): string
    {
        return $this->defaultBody;
    }

    public function setDefaultBody(string $defaultBody): void
    {
        $this->defaultBody = $defaultBody;
    }
}
