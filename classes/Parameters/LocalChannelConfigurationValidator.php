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

namespace PrestaShop\Module\AutoUpgrade\Parameters;

use Exception;
use PrestaShop\Module\AutoUpgrade\Services\PrestashopVersionService;
use PrestaShop\Module\AutoUpgrade\UpgradeTools\Translator;

class LocalChannelConfigurationValidator
{
    /**
     * @var Translator
     */
    private $translator;

    /**
     * @var PrestashopVersionService
     */
    private $versionService;

    /**
     * @var string
     */
    private $downloadPath;

    /**
     * @var string|null
     */
    private $targetVersion;

    /**
     * @var string|null
     */
    private $xmlVersion;

    public function __construct(Translator $translator, PrestashopVersionService $versionService, string $downloadPath)
    {
        $this->translator = $translator;
        $this->downloadPath = $downloadPath;
        $this->versionService = $versionService;
    }

    /**
     * @param array<string, mixed> $array
     *
     * @return array<array{'message': string, 'target'?: string}>
     */
    public function validate(array $array = []): array
    {
        // Reset variables to ensure they're empty foreach validation
        $this->targetVersion = null;
        $this->xmlVersion = null;

        $errors = [];

        $zipErrors = $this->validateZipFile($array['archive_zip']);
        if ($zipErrors) {
            $errors[] = $zipErrors;
        }

        $xmlErrors = $this->validateXmlFile($array['archive_xml']);
        if ($xmlErrors) {
            $errors[] = $xmlErrors;
        }

        if (empty($errors)) {
            $versionErrors = $this->validateVersionsMatch();
            if ($versionErrors) {
                $errors[] = $versionErrors;
            }
        }

        return $errors;
    }

    /**
     * @return array{'message': string, 'target': string}|null
     */
    private function validateZipFile(string $file): ?array
    {
        $fullFilePath = $this->getFileFullPath($file);

        if (!file_exists($fullFilePath)) {
            return [
                'message' => $this->translator->trans('File %s does not exist. Unable to select that channel.', [$file]),
                'target' => 'archive_zip',
            ];
        }

        try {
            $this->targetVersion = $this->versionService->extractPrestashopVersionFromZip($fullFilePath);
        } catch (Exception $exception) {
            return [
                'message' => $this->translator->trans('We couldn\'t find a PrestaShop version in the .zip file that was uploaded in your local archive. Please try again.'),
                'target' => 'archive_zip',
            ];
        }

        return null;
    }

    /**
     * @return array{'message': string, 'target': string}|null
     */
    private function validateXmlFile(string $file): ?array
    {
        $fullXmlPath = $this->getFileFullPath($file);

        if (!file_exists($fullXmlPath)) {
            return [
                'message' => $this->translator->trans('File %s does not exist. Unable to select that channel.', [$file]),
                'target' => 'archive_xml',
            ];
        }

        try {
            $this->xmlVersion = $this->versionService->extractPrestashopVersionFromXml($fullXmlPath);
        } catch (Exception $exception) {
            return [
                'message' => $this->translator->trans('We couldn\'t find a PrestaShop version in the XML file that was uploaded in your local archive. Please try again.'),
                'target' => 'archive_xml',
            ];
        }

        return null;
    }

    /**
     * @return array{'message': string}|null
     */
    private function validateVersionsMatch(): ?array
    {
        if ($this->xmlVersion !== null && $this->xmlVersion !== $this->targetVersion) {
            return [
                'message' => $this->translator->trans('The PrestaShop version in your archive doesn’t match the one in XML file. Please fix this issue and try again.'),
            ];
        }

        return null;
    }

    private function getFileFullPath(string $file): string
    {
        return $this->downloadPath . DIRECTORY_SEPARATOR . $file;
    }
}
