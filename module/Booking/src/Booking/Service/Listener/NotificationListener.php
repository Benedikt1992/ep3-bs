<?php

namespace Booking\Service\Listener;

use Base\Manager\OptionManager;
use Base\View\Helper\DateRange;
use Booking\Manager\ReservationManager;
use Square\Manager\SquareManager;
use User\Manager\UserManager;
use User\Service\MailService;
use Zend\EventManager\AbstractListenerAggregate;
use Zend\EventManager\Event;
use Zend\EventManager\EventManagerInterface;
use Zend\I18n\Translator\TranslatorInterface;
use Zend\I18n\View\Helper\DateFormat;

class NotificationListener extends AbstractListenerAggregate
{

    protected $optionManager;
    protected $reservationManager;
    protected $squareManager;
    protected $userManager;
    protected $userMailService;
    protected $dateFormatHelper;
    protected $dateRangeHelper;
    protected $translator;

    public function __construct(OptionManager $optionManager, ReservationManager $reservationManager,
        SquareManager $squareManager, UserManager $userManager, MailService $userMailService,
        DateFormat $dateFormatHelper, DateRange $dateRangeHelper, TranslatorInterface $translator)
    {
        $this->optionManager = $optionManager;
        $this->reservationManager = $reservationManager;
        $this->squareManager = $squareManager;
        $this->userManager = $userManager;
        $this->userMailService = $userMailService;
        $this->dateFormatHelper = $dateFormatHelper;
        $this->dateRangeHelper = $dateRangeHelper;
        $this->translator = $translator;
    }

    public function attach(EventManagerInterface $events)
    {
        $events->attach('create.single', array($this, 'onCreateSingle'));
        $events->attach('cancel.single', array($this, 'onCancelSingle'));
    }

    public function onCreateSingle(Event $event)
    {
        $booking = $event->getTarget();
        $reservation = current($booking->getExtra('reservations'));
        $square = $this->squareManager->get($booking->need('sid'));
        $user = $this->userManager->get($booking->need('uid'));

        $dateFormatHelper = $this->dateFormatHelper;
        $dateRangerHelper = $this->dateRangeHelper;

        if ($user->getMeta('notification.bookings', 'false') == 'true') {

            $reservationTimeStart = explode(':', $reservation->need('time_start'));
            $reservationTimeEnd = explode(':', $reservation->need('time_end'));

            $reservationStart = new \DateTime($reservation->need('date'));
            $reservationStart->setTime($reservationTimeStart[0], $reservationTimeStart[1]);

            $reservationEnd = new \DateTime($reservation->need('date'));
            $reservationEnd->setTime($reservationTimeEnd[0], $reservationTimeEnd[1]);

            $subject = sprintf($this->t('Your %s-booking for %s'),
                $this->optionManager->get('subject.square.type'),
                $dateFormatHelper($reservationStart, \IntlDateFormatter::MEDIUM, \IntlDateFormatter::SHORT));

            $message = sprintf($this->t('we have reserved %s %s, %s for you. Thank you for your booking.'),
                $this->optionManager->get('subject.square.type'),
                $square->need('name'),
                $dateRangerHelper($reservationStart, $reservationEnd));

            $this->userMailService->send($user, $subject, $message);
        }
    }

    public function onCancelSingle(Event $event)
    {
        $booking = $event->getTarget();
        $reservations = $this->reservationManager->getBy(['bid' => $booking->need('bid')], 'date ASC', 1);
        $reservation = current($reservations);
        $square = $this->squareManager->get($booking->need('sid'));
        $user = $this->userManager->get($booking->need('uid'));

        $dateRangerHelper = $this->dateRangeHelper;

        if ($user->getMeta('notification.bookings', 'false') == 'true') {

            $reservationTimeStart = explode(':', $reservation->need('time_start'));
            $reservationTimeEnd = explode(':', $reservation->need('time_end'));

            $reservationStart = new \DateTime($reservation->need('date'));
            $reservationStart->setTime($reservationTimeStart[0], $reservationTimeStart[1]);

            $reservationEnd = new \DateTime($reservation->need('date'));
            $reservationEnd->setTime($reservationTimeEnd[0], $reservationTimeEnd[1]);

            $subject = sprintf($this->t('Your %s-booking has been cancelled'),
                $this->optionManager->get('subject.square.type'));

            $message = sprintf($this->t('we have just cancelled %s %s, %s for you.'),
                $this->optionManager->get('subject.square.type'),
                $square->need('name'),
                $dateRangerHelper($reservationStart, $reservationEnd));

            $this->userMailService->send($user, $subject, $message);
        }
    }

    protected function t($message)
    {
        return $this->translator->translate($message);
    }

}