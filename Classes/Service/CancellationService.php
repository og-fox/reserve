<?php

declare(strict_types=1);

/*
 * This file is part of the package jweiland/reserve.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace JWeiland\Reserve\Service;

use JWeiland\Reserve\Domain\Model\Order;
use JWeiland\Reserve\Utility\CacheUtility;
use JWeiland\Reserve\Utility\FluidUtility;
use TYPO3\CMS\Core\SingletonInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Persistence\Generic\PersistenceManager;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;
use TYPO3\CMS\Fluid\View\StandaloneView;

/**
 * Service to cancel an order
 */
class CancellationService implements SingletonInterface
{
    /**
     * Reasons for cancellation
     */
    const REASON_CUSTOMER = 'customer';
    const REASON_INACTIVE = 'inactive';

    /**
     * @var PersistenceManager
     */
    protected $persistenceManager;

    /**
     * @var StandaloneView
     */
    protected $standaloneView;

    public function __construct(PersistenceManager $persistenceManager, StandaloneView $standaloneView)
    {
        $this->persistenceManager = $persistenceManager;
        $this->standaloneView = $standaloneView;
    }

    /**
     * @param Order $order
     * @param string $reason use CancellationService::REASON_ constants or add your own reason
     * @param array $vars additional variables that will be assigned to the fluid template
     * @param bool $sendMailToCustomer set false to cancel the order without sending a mail to the customer
     * @param bool $persist set false to persist the order by yourself using $cancellationService->getPersistenceManager()->persistAll()
     */
    public function cancel(Order $order, string $reason = self::REASON_CUSTOMER, $vars = [], bool $sendMailToCustomer = true, bool $persist = true)
    {
        $this->persistenceManager->remove($order);
        if ($sendMailToCustomer) {
            /** @var StandaloneView $standaloneView */
            $standaloneView = GeneralUtility::makeInstance(StandaloneView::class);
            FluidUtility::configureStandaloneViewForMailing($standaloneView);
            $standaloneView->assignMultiple(['order' => $order, 'reason' => $reason]);
            $standaloneView->assignMultiple($vars);
            $standaloneView->setTemplate('Cancellation');
            GeneralUtility::makeInstance(MailService::class)->sendMailToCustomer(
                $order,
                LocalizationUtility::translate('mail.cancellation.subject', 'reserve'),
                $standaloneView->render()
            );
        }
        if ($persist) {
            $this->persistenceManager->persistAll();
            CacheUtility::clearPageCachesForPagesWithCurrentFacility($order->getBookedPeriod()->getFacility()->getUid());
        }
    }

    /**
     * @return PersistenceManager
     */
    public function getPersistenceManager(): PersistenceManager
    {
        return $this->persistenceManager;
    }
}
