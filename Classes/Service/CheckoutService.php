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

namespace JWeiland\Reserve\Service;

use JWeiland\Reserve\Domain\Model\Order;
use JWeiland\Reserve\Domain\Model\Reservation;
use JWeiland\Reserve\Utility\CheckoutUtility;
use TYPO3\CMS\Core\Mail\MailMessage;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Persistence\Generic\PersistenceManager;
use TYPO3\CMS\Fluid\View\StandaloneView;

class CheckoutService
{
    /**
     * @var PersistenceManager
     */
    protected $persistenceManager;

    public function __construct(PersistenceManager $persistenceManager)
    {
        $this->persistenceManager = $persistenceManager;
    }

    /**
     * Create $amountOfReservations reservation records and add them to $order.
     * Set activation code for $order and all new reservations inside the current $order.
     * Use $order after $checkout to proceed
     *
     * @param Order $order
     * @param int $amountOfReservations
     * @return bool true on success, otherwise false
     */
    public function checkout(Order $order, int $amountOfReservations): bool
    {
        $success = true;
        if ($amountOfReservations > $order->getBookedPeriod()->getMaxParticipantsPerOrder()) {
            $success = false;
        } else {
            $order->setActivationCode(CheckoutUtility::generateActivationCodeForOrder());
            for ($i = 0; $i < $amountOfReservations; $i++) {
                /** @var Reservation $reservation */
                $reservation = GeneralUtility::makeInstance(Reservation::class);
                $reservation->setCustomerOrder($order);
                $reservation->setCode(CheckoutUtility::generateCodeForReservation());
                $order->getReservations()->attach($reservation);
                $this->persistenceManager->add($reservation);
            }
            $this->persistenceManager->add($order);
            $this->persistenceManager->persistAll();
        }
        return $success;
    }

    public function sendConfirmationMail(Order $order): bool
    {
        return $this->sendMailToCustomer(
            $order,
            $order->getBookedPeriod()->getFacility()->getConfirmationMailSubject(),
            $this->replaceMarkerByRenderedTemplate(
                '###ORDER_DETAILS###',
                'Confirmation',
                $order->getBookedPeriod()->getFacility()->getConfirmationMailHtml(),
                ['pageUid' => $GLOBALS['TSFE']->id, 'order' => $order]
            )
        );
    }

    public function confirm(Order $order): bool
    {
        $success = true;
        $order->setActivated(true);
        $this->sendReservationMail($order);
        $this->persistenceManager->add($order);
        $this->persistenceManager->persistAll();
        return $success;
    }

    public function sendReservationMail(Order $order)
    {
        return $this->sendMailToCustomer(
            $order,
            $order->getBookedPeriod()->getFacility()->getReservationMailSubject(),
            $this->replaceMarkerByRenderedTemplate(
                '###RESERVATION###',
                'Reservation',
                $order->getBookedPeriod()->getFacility()->getReservationMailHtml(),
                ['order' => $order]
            )
        );
    }

    protected function sendMailToCustomer(Order $order, string $subject, string $bodyHtml): bool
    {
        /** @var MailMessage $mail */
        $mail = GeneralUtility::makeInstance(MailMessage::class);
        $mail
            ->setSubject($subject)
            ->setTo([$order->getEmail()]);
        if ($order->getBookedPeriod()->getFacility()->getReplyToEmail()) {
            $mail->setReplyTo([$order->getBookedPeriod()->getFacility()->getReplyToEmail() => $order->getBookedPeriod()->getFacility()->getReplyToName()]);
        }
        if (method_exists($mail, 'addPart')) {
            // TYPO3 < 10 (Swift_Message)
            $mail->setBody($bodyHtml, 'text/html');
        } else {
            // TYPO3 >= 10 (Symfony Mail)
            $mail->html($bodyHtml);
        }
        return $mail->send();
    }

    /**
     * @param string $marker content to replace e.g. ###MY_MARKER###
     * @param string $template fluid template name
     * @param string $content string which may contain $marker
     * @param array $vars additional vars for the fluid template
     * @return string
     */
    protected function replaceMarkerByRenderedTemplate(
        string $marker,
        string $template,
        string $content,
        array $vars = []
    ): string {
        /** @var StandaloneView $standaloneView */
        $standaloneView = GeneralUtility::makeInstance(StandaloneView::class);
        $standaloneView->assignMultiple($vars);
        // todo: allow users to override this template
        $standaloneView->setTemplatePathAndFilename('EXT:reserve/Resources/Private/Templates/Checkout/Mail/' . $template . '.html');
        return str_replace($marker, $standaloneView->render(), $content);
    }
}