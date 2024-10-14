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

namespace PrestaShop\Module\AutoUpgrade\Progress;

class Backlog
{
    /**
     * Number of elements in backlog at the beginning
     *
     * @var int
     */
    private $initialTotal;

    /**
     * Remaining backlog of elements
     *
     * @var mixed[]
     */
    private $backlog;

    /**
     * @param mixed[] $backlog
     */
    public function __construct(array $backlog, int $initialTotal)
    {
        $this->backlog = $backlog;
        $this->initialTotal = $initialTotal;
    }

    /**
     * @param array{'backlog':mixed[],'initialTotal':int} $contents
     */
    public static function fromContents($contents): self
    {
        return new self($contents['backlog'], $contents['initialTotal']);
    }

    /**
     * @return array{'backlog':mixed[],'initialTotal':int}
     */
    public function dump(): array
    {
        return [
            'backlog' => $this->backlog,
            'initialTotal' => $this->initialTotal,
        ];
    }

    /**
     * @return mixed
     */
    public function getNext()
    {
        return array_pop($this->backlog);
    }

    /**
     * @return int|string|null
     */
    public function getFirstKey()
    {
        if (empty($this->backlog)) {
            return null;
        }

        reset($this->backlog);

        return key($this->backlog);
    }

    /**
     * @return mixed|null
     */
    public function getFirstValue()
    {
        if (empty($this->backlog)) {
            return null;
        }

        return reset($this->backlog);
    }

    /**
     * @param int|string $key
     * @param mixed $content
     */
    public function updateItem($key, $content): void
    {
        if (empty($content)) {
            unset($this->backlog[$key]);
        } else {
            $this->backlog[$key] = $content;
        }
    }

    public function getRemainingTotal(int $depth = 1): int
    {
        return $this->countElements($this->backlog, $depth);
    }

    /**
     * @param mixed[] $array
     */
    private function countElements(array $array, int $depth): int
    {
        if ($depth === 1) {
            return count($array);
        }

        $total = 0;
        foreach ($array as $value) {
            if (is_array($value)) {
                $total += $this->countElements($value, $depth - 1);
            } else {
                ++$total;
            }
        }

        return $total;
    }

    public function getInitialTotal(): int
    {
        return $this->initialTotal;
    }
}
