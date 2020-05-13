<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 CMS project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */

namespace JWeiland\Reserve\Domain\Model;

use TYPO3\CMS\Extbase\DomainObject\AbstractEntity;

/**
 * Class Reservation
 */
class Reservation extends AbstractEntity
{
    /**
     * @var \JWeiland\Reserve\Domain\Model\Order
     */
    protected $customerOrder;

    protected $code = '';

    protected $used = '';

    /**
     * @return Order
     */
    public function getCustomerOrder(): Order
    {
        return $this->customerOrder;
    }

    /**
     * @param Order $customerOrder
     */
    public function setCustomerOrder(Order $customerOrder)
    {
        $this->customerOrder = $customerOrder;
    }

    /**
     * @return string
     */
    public function getCode(): string
    {
        return $this->code;
    }

    /**
     * @param string $code
     */
    public function setCode(string $code)
    {
        $this->code = $code;
    }

    /**
     * @return string
     */
    public function getUsed(): string
    {
        return $this->used;
    }

    /**
     * @param string $used
     */
    public function setUsed(string $used)
    {
        $this->used = $used;
    }
}
