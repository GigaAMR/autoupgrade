<?php

/**
 * Copyright since 2007 PrestaShop SA and Contributors
 * PrestaShop is an International Registered Trademark & Property of PrestaShop SA
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License 3.0 (AFL-3.0)
 * that is bundled with this package in the file LICENSE.md.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/AFL-3.0
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade PrestaShop to newer
 * versions in the future. If you wish to customize PrestaShop for your
 * needs please refer to https://devdocs.prestashop.com/ for more information.
 *
 * @author    PrestaShop SA and Contributors <contact@prestashop.com>
 * @copyright Since 2007 PrestaShop SA and Contributors
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License 3.0 (AFL-3.0)
 */

namespace PrestaShop\Module\AutoUpgrade\Task;

use Exception;
use PrestaShop\Module\AutoUpgrade\AjaxResponse;
use PrestaShop\Module\AutoUpgrade\Analytics;
use PrestaShop\Module\AutoUpgrade\Log\Logger;
use PrestaShop\Module\AutoUpgrade\UpgradeContainer;
use PrestaShop\Module\AutoUpgrade\Upgrader;
use PrestaShop\Module\AutoUpgrade\UpgradeTools\Translator;

abstract class AbstractTask
{
    /**
     * usage :  key = the step you want to skip
     *          value = the next step you want instead
     * example : public static $skipAction = array();
     *
     * @var array<string, string>
     */
    public static $skipAction = [];

    /**
     * @var Logger
     */
    protected $logger;

    /**
     * @var Translator
     */
    protected $translator;

    /**
     * @var UpgradeContainer
     */
    protected $container;

    /**
     * @var TaskType::TASK_TYPE_*|null
     */
    const TASK_TYPE = null;

    // Task progress details
    /**
     * @var bool
     */
    protected $stepDone;
    /**
     * @var string
     */
    protected $status;
    /**
     * @var bool
     */
    protected $error;
    /**
     * @var array<string, string|array<string>>
     */
    protected $nextParams = [];
    /**
     * @var TaskName::TASK_*|null
     */
    protected $next;

    /**
     * @throws Exception
     */
    public function __construct(UpgradeContainer $container)
    {
        $this->container = $container;
        $this->logger = $this->container->getLogger();
        $this->translator = $this->container->getTranslator();
        $this->checkTaskMayRun();

        if ($this::TASK_TYPE !== null) {
            $logPath = $this->container->getLogsPath($this::TASK_TYPE);
            if ($logPath !== null) {
                $this->logger->updateLogsPath($logPath);
            }
        }
    }

    /**
     * @return string base64 encoded data from AjaxResponse
     */
    public function getEncodedResponse(): string
    {
        return base64_encode($this->getJsonResponse());
    }

    /**
     * @return string Json encoded data from AjaxResponse
     */
    public function getJsonResponse(): string
    {
        return $this->getResponse()->getJson();
    }

    /**
     * Get result of the task and data to send to the next request.
     *
     * @return AjaxResponse
     */
    public function getResponse(): AjaxResponse
    {
        $response = new AjaxResponse($this->container->getState(), $this->logger);

        return $response->setError($this->error)
            ->setStepDone($this->stepDone)
            ->setNext($this->next)
            ->setNextParams($this->nextParams)
            ->setUpgradeConfiguration($this->container->getUpgradeConfiguration());
    }

    private function checkTaskMayRun(): void
    {
        // PrestaShop demo mode
        if (defined('_PS_MODE_DEMO_') && _PS_MODE_DEMO_ == true) {
            return;
        }

        $currentAction = get_class($this);
        if (isset(self::$skipAction[$currentAction])) {
            $this->next = self::$skipAction[$currentAction];
            $this->logger->info($this->translator->trans('Action %s skipped', [$currentAction]));
        }
    }

    public function setErrorFlag(): void
    {
        $this->error = true;
        // TODO: Add this? $this->next = 'error';

        if (static::TASK_TYPE) {
            $propertiesType = null;

            switch (static::TASK_TYPE) {
                case TaskType::TASK_TYPE_UPDATE:
                    $propertiesType = Analytics::WITH_UPDATE_PROPERTIES;
                    break;
                case TaskType::TASK_TYPE_BACKUP:
                    $propertiesType = Analytics::WITH_BACKUP_PROPERTIES;
                    break;
                case TaskType::TASK_TYPE_RESTORE:
                    $propertiesType = Analytics::WITH_RESTORE_PROPERTIES;
                    break;
            }

            $this->container->getAnalytics()->track(
                ucfirst(static::TASK_TYPE) . ' Failed',
                $propertiesType
            );
        }
    }

    /**
     * @throws Exception
     */
    public function init(): void
    {
        $this->container->initPrestaShopCore();
        $this->setupEnvironment();
    }

    /**
     * @throws Exception
     */
    protected function setupEnvironment(): void
    {
        if ($this::TASK_TYPE === TaskType::TASK_TYPE_UPDATE && $this->container->getUpgrader()->getChannel() === Upgrader::CHANNEL_LOCAL) {
            $archiveXml = $this->container->getUpgradeConfiguration()->getLocalChannelXml();
            $this->container->getFileLoader()->addXmlMd5File($this->container->getUpgrader()->getDestinationVersion(), $this->container->getProperty(UpgradeContainer::DOWNLOAD_PATH) . DIRECTORY_SEPARATOR . $archiveXml);
        }

        if ($this::TASK_TYPE !== TaskType::TASK_TYPE_RESTORE && !$this->container->getState()->isInitialized()) {
            $this->container->getState()->initDefault($this->container->getProperty(UpgradeContainer::PS_VERSION), $this->container->getUpgrader()->getDestinationVersion());
            $this->logger->debug($this->translator->trans('Successfully initialized state.'));
        }
    }

    abstract public function run(): int;
}
