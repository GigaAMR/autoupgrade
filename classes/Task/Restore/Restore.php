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

namespace PrestaShop\Module\AutoUpgrade\Task\Restore;

use Exception;
use PrestaShop\Module\AutoUpgrade\Analytics;
use PrestaShop\Module\AutoUpgrade\Backup\BackupFinder;
use PrestaShop\Module\AutoUpgrade\Parameters\UpgradeFileNames;
use PrestaShop\Module\AutoUpgrade\Task\AbstractTask;
use PrestaShop\Module\AutoUpgrade\Task\ExitCode;
use PrestaShop\Module\AutoUpgrade\Task\TaskName;
use PrestaShop\Module\AutoUpgrade\Task\TaskType;
use PrestaShop\Module\AutoUpgrade\UpgradeContainer;

/**
 * First step executed during a rollback.
 */
class Restore extends AbstractTask
{
    const TASK_TYPE = TaskType::TASK_TYPE_RESTORE;

    /**
     * @throws Exception
     */
    public function run(): int
    {
        $this->container->getState()->setProgressPercentage(
            $this->container->getCompletionCalculator()->getBasePercentageOfTask(self::class)
        );

        // 1st, need to analyse what was wrong.
        $restoreName = $this->container->getState()->getRestoreName();
        $this->container->getState()->setRestoreFilesFilename($restoreName);
        $restoreDbFilenames = $this->container->getState()->getRestoreDbFilenames();

        if (empty($restoreName)) {
            $this->next = TaskName::TASK_RESTORE_EMPTY;

            return ExitCode::SUCCESS;
        }

        $files = scandir($this->container->getProperty(UpgradeContainer::BACKUP_PATH));
        // find backup filenames, and be sure they exists
        foreach ($files as $file) {
            if (preg_match('#' . preg_quote(BackupFinder::BACKUP_ZIP_NAME_PREFIX . $restoreName) . '#', $file)) {
                $this->container->getState()->setRestoreFilesFilename($file);
                break;
            }
        }
        if (!is_file($this->container->getProperty(UpgradeContainer::BACKUP_PATH) . DIRECTORY_SEPARATOR . $this->container->getState()->getRestoreFilesFilename())) {
            $this->next = TaskName::TASK_ERROR;
            $this->setErrorFlag();
            $this->logger->error($this->translator->trans('[ERROR] File %s is missing: unable to restore files. Operation aborted.', [$this->container->getState()->getRestoreFilesFilename()]));

            return ExitCode::FAIL;
        }
        $files = scandir($this->container->getProperty(UpgradeContainer::BACKUP_PATH) . DIRECTORY_SEPARATOR . $restoreName);
        foreach ($files as $file) {
            if (preg_match('#' . BackupFinder::BACKUP_DB_FOLDER_NAME_PREFIX . '[0-9]{6}_' . preg_quote($restoreName) . '#', $file)) {
                $restoreDbFilenames[] = $file;
            }
        }

        // order files is important !
        sort($restoreDbFilenames);
        $this->container->getState()->setRestoreDbFilenames($restoreDbFilenames);
        if (count($restoreDbFilenames) == 0) {
            $this->next = TaskName::TASK_ERROR;
            $this->setErrorFlag();
            $this->logger->error($this->translator->trans('[ERROR] No backup database files found: it would be impossible to restore the database. Operation aborted.'));

            return ExitCode::FAIL;
        }

        $this->next = TaskName::TASK_RESTORE_FILES;
        $this->logger->info($this->translator->trans('Restoring files ...'));
        // remove tmp files related to restoreFiles
        if (file_exists($this->container->getProperty(UpgradeContainer::WORKSPACE_PATH) . DIRECTORY_SEPARATOR . UpgradeFileNames::FILES_FROM_ARCHIVE_LIST)) {
            unlink($this->container->getProperty(UpgradeContainer::WORKSPACE_PATH) . DIRECTORY_SEPARATOR . UpgradeFileNames::FILES_FROM_ARCHIVE_LIST);
        }
        if (file_exists($this->container->getProperty(UpgradeContainer::WORKSPACE_PATH) . DIRECTORY_SEPARATOR . UpgradeFileNames::FILES_TO_REMOVE_LIST)) {
            unlink($this->container->getProperty(UpgradeContainer::WORKSPACE_PATH) . DIRECTORY_SEPARATOR . UpgradeFileNames::FILES_TO_REMOVE_LIST);
        }

        $this->container->getAnalytics()->track('Restore Launched', Analytics::WITH_RESTORE_PROPERTIES);

        return ExitCode::SUCCESS;
    }

    public function init(): void
    {
        // Do nothing
    }
}
