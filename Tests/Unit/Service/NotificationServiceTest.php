<?php
namespace DERHANSEN\SfEventMgt\Tests\Unit\Service;

/*
 * This file is part of the Extension "sf_event_mgt" for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 */

use DERHANSEN\SfEventMgt\Domain\Model\Dto\CustomNotification;
use DERHANSEN\SfEventMgt\Domain\Model\Event;
use DERHANSEN\SfEventMgt\Domain\Model\Organisator;
use DERHANSEN\SfEventMgt\Domain\Model\Registration;
use DERHANSEN\SfEventMgt\Domain\Repository\CustomNotificationLogRepository;
use DERHANSEN\SfEventMgt\Domain\Repository\RegistrationRepository;
use DERHANSEN\SfEventMgt\Service\EmailService;
use DERHANSEN\SfEventMgt\Service\FluidStandaloneService;
use DERHANSEN\SfEventMgt\Service\Notification\AttachmentService;
use DERHANSEN\SfEventMgt\Service\NotificationService;
use DERHANSEN\SfEventMgt\Utility\MessageRecipient;
use DERHANSEN\SfEventMgt\Utility\MessageType;
use Nimut\TestingFramework\TestCase\UnitTestCase;
use TYPO3\CMS\Extbase\Persistence\ObjectStorage;
use TYPO3\CMS\Extbase\Security\Cryptography\HashService;
use TYPO3\CMS\Extbase\SignalSlot\Dispatcher;

/**
 * Test case for class DERHANSEN\SfEventMgt\Service\NotificationService.
 *
 * @author Torben Hansen <derhansen@gmail.com>
 */
class NotificationServiceTest extends UnitTestCase
{
    /**
     * @var \DERHANSEN\SfEventMgt\Service\NotificationService
     */
    protected $subject = null;

    /**
     * Setup
     *
     * @return void
     */
    protected function setUp()
    {
        $this->subject = new NotificationService();

        $GLOBALS['BE_USER'] = new \stdClass();
        $GLOBALS['BE_USER']->uc['lang'] = '';
    }

    /**
     * Teardown
     *
     * @return void
     */
    protected function tearDown()
    {
        unset($this->subject);
    }

    /**
     * Data provider for messageType
     *
     * @return array
     */
    public function messageTypeDataProvider()
    {
        return [
            'messageTypeRegistrationNew' => [
                MessageType::REGISTRATION_NEW
            ],
            'messageTypeRegistrationWaitlistNew' => [
                MessageType::REGISTRATION_WAITLIST_NEW
            ],
            'messageTypeRegistrationConfirmed' => [
                MessageType::REGISTRATION_CONFIRMED
            ],
            'messageTypeRegistrationWaitlistConfirmed' => [
                MessageType::REGISTRATION_WAITLIST_CONFIRMED
            ]
        ];
    }

    /**
     * @test
     * @dataProvider messageTypeDataProvider
     * @param mixed $messageType
     */
    public function sendUserMessageReturnsFalseIfIgnoreNotificationsSet($messageType)
    {
        $event = new Event();
        $registration = new Registration();
        $registration->setEmail('valid@email.tld');
        $registration->setIgnoreNotifications(true);

        $settings = ['notification' => ['senderEmail' => 'valid@email.tld']];

        $result = $this->subject->sendUserMessage($event, $registration, $settings, $messageType);
        $this->assertFalse($result);
    }

    /**
     * @test
     * @return void
     */
    public function sendUserMessageReturnsFalseIfInvalidArgumentsGiven()
    {
        $result = $this->subject->sendUserMessage(null, null, null, MessageType::REGISTRATION_NEW);
        $this->assertFalse($result);
    }

    /**
     * @test
     * @dataProvider messageTypeDataProvider
     * @param mixed $messageType
     */
    public function sendUserMessageReturnsFalseIfSendFailed($messageType)
    {
        $event = new Event();
        $registration = new Registration();
        $registration->setEmail('valid@email.tld');

        $settings = ['notification' => ['senderEmail' => 'valid@email.tld']];

        $emailService = $this->getMockBuilder(EmailService::class)->setMethods(['sendEmailMessage'])->getMock();
        $emailService->expects($this->once())->method('sendEmailMessage')->will($this->returnValue(false));
        $this->inject($this->subject, 'emailService', $emailService);

        $attachmentService = $this->getMockBuilder(AttachmentService::class)->setMethods(['getAttachments'])->getMock();
        $attachmentService->expects($this->once())->method('getAttachments');
        $this->inject($this->subject, 'attachmentService', $attachmentService);

        $hashService = $this->getMockBuilder(HashService::class)->getMock();
        $hashService->expects($this->once())->method('generateHmac')->will($this->returnValue('HMAC'));
        $hashService->expects($this->once())->method('appendHmac')->will($this->returnValue('HMAC'));
        $this->inject($this->subject, 'hashService', $hashService);

        $fluidStandaloneService = $this->getMockBuilder(FluidStandaloneService::class)
            ->setMethods(['renderTemplate', 'parseStringFluid'])
            ->getMock();
        $fluidStandaloneService->expects($this->once())->method('renderTemplate')->will($this->returnValue(''));
        $fluidStandaloneService->expects($this->once())->method('parseStringFluid')->will($this->returnValue(''));
        $this->inject($this->subject, 'fluidStandaloneService', $fluidStandaloneService);

        $mockSignalSlotDispatcher = $this->getMockBuilder(Dispatcher::class)
            ->setMethods(['dispatch'])
            ->disableOriginalConstructor()
            ->getMock();
        $mockSignalSlotDispatcher->expects($this->atLeast(2))->method('dispatch');
        $this->inject($this->subject, 'signalSlotDispatcher', $mockSignalSlotDispatcher);

        $result = $this->subject->sendUserMessage($event, $registration, $settings, $messageType);
        $this->assertFalse($result);
    }

    /**
     * @test
     * @dataProvider messageTypeDataProvider
     * @param mixed $messageType
     */
    public function sendUserMessageReturnsTrueIfSendSuccessful($messageType)
    {
        $event = new Event();
        $registration = new Registration();
        $registration->setEmail('valid@email.tld');

        $settings = ['notification' => ['senderEmail' => 'valid@email.tld']];

        $emailService = $this->getMockBuilder(EmailService::class)->getMock();
        $emailService->expects($this->once())->method('sendEmailMessage')->will($this->returnValue(true));
        $this->inject($this->subject, 'emailService', $emailService);

        $attachmentService = $this->getMockBuilder(AttachmentService::class)->getMock();
        $attachmentService->expects($this->once())->method('getAttachments');
        $this->inject($this->subject, 'attachmentService', $attachmentService);

        $hashService = $this->getMockBuilder(HashService::class)->getMock();
        $hashService->expects($this->once())->method('generateHmac')->will($this->returnValue('HMAC'));
        $hashService->expects($this->once())->method('appendHmac')->will($this->returnValue('HMAC'));
        $this->inject($this->subject, 'hashService', $hashService);

        $fluidStandaloneService = $this->getMockBuilder(FluidStandaloneService::class)
            ->setMethods(['renderTemplate', 'parseStringFluid'])
            ->disableOriginalConstructor()
            ->getMock();
        $fluidStandaloneService->expects($this->once())->method('renderTemplate')->will($this->returnValue(''));
        $fluidStandaloneService->expects($this->once())->method('parseStringFluid')->will($this->returnValue(''));
        $this->inject($this->subject, 'fluidStandaloneService', $fluidStandaloneService);

        $mockSignalSlotDispatcher = $this->getMockBuilder(Dispatcher::class)
            ->setMethods(['dispatch'])
            ->disableOriginalConstructor()
            ->getMock();
        $mockSignalSlotDispatcher->expects($this->atLeast(2))->method('dispatch');
        $this->inject($this->subject, 'signalSlotDispatcher', $mockSignalSlotDispatcher);

        $result = $this->subject->sendUserMessage($event, $registration, $settings, $messageType);
        $this->assertTrue($result);
    }

    /**
     * @test
     * @dataProvider messageTypeDataProvider
     * @param mixed $messageType
     */
    public function sendAdminNewRegistrationMessageReturnsFalseIfSendFailed($messageType)
    {
        $event = new Event();
        $registration = new Registration();

        $settings = [
            'notification' => [
                'senderEmail' => 'valid@email.tld',
                'adminEmail' => 'valid@email.tld'
            ]
        ];

        $emailService = $this->getMockBuilder(EmailService::class)->getMock();
        $emailService->expects($this->once())->method('sendEmailMessage')->will($this->returnValue(false));
        $this->inject($this->subject, 'emailService', $emailService);

        $attachmentService = $this->getMockBuilder(AttachmentService::class)->getMock();
        $attachmentService->expects($this->once())->method('getAttachments');
        $this->inject($this->subject, 'attachmentService', $attachmentService);

        $hashService = $this->getMockBuilder(HashService::class)->getMock();
        $hashService->expects($this->once())->method('generateHmac')->will($this->returnValue('HMAC'));
        $hashService->expects($this->once())->method('appendHmac')->will($this->returnValue('HMAC'));
        $this->inject($this->subject, 'hashService', $hashService);

        $fluidStandaloneService = $this->getMockBuilder(FluidStandaloneService::class)
            ->setMethods(['renderTemplate', 'parseStringFluid'])
            ->disableOriginalConstructor()
            ->getMock();
        $fluidStandaloneService->expects($this->once())->method('renderTemplate')->will($this->returnValue(''));
        $fluidStandaloneService->expects($this->once())->method('parseStringFluid')->will($this->returnValue(''));
        $this->inject($this->subject, 'fluidStandaloneService', $fluidStandaloneService);

        $mockSignalSlotDispatcher = $this->getMockBuilder(Dispatcher::class)
            ->setMethods(['dispatch'])
            ->disableOriginalConstructor()
            ->getMock();
        $mockSignalSlotDispatcher->expects($this->once())->method('dispatch');
        $this->inject($this->subject, 'signalSlotDispatcher', $mockSignalSlotDispatcher);

        $result = $this->subject->sendAdminMessage($event, $registration, $settings, $messageType);
        $this->assertFalse($result);
    }

    /**
     * @test
     * @return void
     */
    public function sendAdminMessageReturnsFalseIfInvalidArgumentsGiven()
    {
        $result = $this->subject->sendAdminMessage(null, null, null, MessageType::REGISTRATION_NEW);
        $this->assertFalse($result);
    }

    /**
     * @test
     * @dataProvider messageTypeDataProvider
     * @param mixed $messageType
     */
    public function sendAdminNewRegistrationMessageReturnsTrueIfSendSuccessful($messageType)
    {
        $event = new Event();
        $registration = new Registration();

        $settings = [
            'notification' => [
                'senderEmail' => 'valid@email.tld',
                'adminEmail' => 'valid@email.tld'
            ]
        ];

        $emailService = $this->getMockBuilder(EmailService::class)->getMock();
        $emailService->expects($this->once())->method('sendEmailMessage')->will($this->returnValue(true));
        $this->inject($this->subject, 'emailService', $emailService);

        $attachmentService = $this->getMockBuilder(AttachmentService::class)->getMock();
        $attachmentService->expects($this->once())->method('getAttachments');
        $this->inject($this->subject, 'attachmentService', $attachmentService);

        $hashService = $this->getMockBuilder(HashService::class)->getMock();
        $hashService->expects($this->once())->method('generateHmac')->will($this->returnValue('HMAC'));
        $hashService->expects($this->once())->method('appendHmac')->will($this->returnValue('HMAC'));
        $this->inject($this->subject, 'hashService', $hashService);

        $fluidStandaloneService = $this->getMockBuilder(FluidStandaloneService::class)
            ->setMethods(['renderTemplate', 'parseStringFluid'])
            ->disableOriginalConstructor()
            ->getMock();
        $fluidStandaloneService->expects($this->once())->method('renderTemplate')->will($this->returnValue(''));
        $fluidStandaloneService->expects($this->once())->method('parseStringFluid')->will($this->returnValue(''));
        $this->inject($this->subject, 'fluidStandaloneService', $fluidStandaloneService);

        $mockSignalSlotDispatcher = $this->getMockBuilder(Dispatcher::class)
            ->setMethods(['dispatch'])
            ->disableOriginalConstructor()
            ->getMock();
        $mockSignalSlotDispatcher->expects($this->once())->method('dispatch');
        $this->inject($this->subject, 'signalSlotDispatcher', $mockSignalSlotDispatcher);

        $result = $this->subject->sendAdminMessage($event, $registration, $settings, $messageType);
        $this->assertTrue($result);
    }

    /**
     * @test
     * @dataProvider messageTypeDataProvider
     * @param mixed $messageType
     */
    public function sendAdminMessageDoesNotSendEmailIfNotifyAdminAndNotifyOrganiserIsFalse($messageType)
    {
        $event = new Event();
        $event->setNotifyAdmin(false);
        $event->setNotifyOrganisator(false);
        $registration = new Registration();

        $settings = [
            'notification' => [
                'senderEmail' => 'valid@email.tld',
                'adminEmail' => 'valid@email.tld'
            ]
        ];

        $emailService = $this->getMockBuilder(EmailService::class)->getMock();
        $emailService->expects($this->never())->method('sendEmailMessage');
        $this->inject($this->subject, 'emailService', $emailService);

        $result = $this->subject->sendAdminMessage($event, $registration, $settings, $messageType);
        $this->assertFalse($result);
    }

    /**
     * @test
     * @dataProvider messageTypeDataProvider
     * @param mixed $messageType
     */
    public function sendAdminMessageSendsEmailToOrganisatorIfConfigured($messageType)
    {
        $organisator = new Organisator();
        $event = new Event();
        $event->setNotifyAdmin(false);
        $event->setNotifyOrganisator(true);
        $event->setOrganisator($organisator);
        $registration = new Registration();

        $settings = [
            'notification' => [
                'senderEmail' => 'valid@email.tld',
                'adminEmail' => 'valid@email.tld'
            ]
        ];

        $emailService = $this->getMockBuilder(EmailService::class)->getMock();
        $emailService->expects($this->once())->method('sendEmailMessage')->will($this->returnValue(true));
        $this->inject($this->subject, 'emailService', $emailService);

        $attachmentService = $this->getMockBuilder(AttachmentService::class)->getMock();
        $attachmentService->expects($this->once())->method('getAttachments');
        $this->inject($this->subject, 'attachmentService', $attachmentService);

        $hashService = $this->getMockBuilder(HashService::class)->getMock();
        $hashService->expects($this->once())->method('generateHmac')->will($this->returnValue('HMAC'));
        $hashService->expects($this->once())->method('appendHmac')->will($this->returnValue('HMAC'));
        $this->inject($this->subject, 'hashService', $hashService);

        $fluidStandaloneService = $this->getMockBuilder(FluidStandaloneService::class)
            ->setMethods(['renderTemplate', 'parseStringFluid'])
            ->disableOriginalConstructor()
            ->getMock();
        $fluidStandaloneService->expects($this->once())->method('renderTemplate')->will($this->returnValue(''));
        $fluidStandaloneService->expects($this->once())->method('parseStringFluid')->will($this->returnValue(''));
        $this->inject($this->subject, 'fluidStandaloneService', $fluidStandaloneService);

        $mockSignalSlotDispatcher = $this->getMockBuilder(Dispatcher::class)
            ->setMethods(['dispatch'])
            ->disableOriginalConstructor()
            ->getMock();
        $mockSignalSlotDispatcher->expects($this->once())->method('dispatch');
        $this->inject($this->subject, 'signalSlotDispatcher', $mockSignalSlotDispatcher);

        $result = $this->subject->sendAdminMessage($event, $registration, $settings, $messageType);
        $this->assertTrue($result);
    }

    /**
     * @test
     */
    public function sendAdminMessageUsesRegistrationDataAsSenderIfConfigured()
    {
        $organisator = new Organisator();
        $event = new Event();
        $event->setNotifyAdmin(false);
        $event->setNotifyOrganisator(true);
        $event->setOrganisator($organisator);

        $settings = [
            'notification' => [
                'registrationDataAsSenderForAdminEmails' => 1,
            ]
        ];

        $mockRegistration = $this->getMockBuilder(Registration::class)->getMock();
        $mockRegistration->expects($this->once())->method('getFullname')->willReturn('Sender');
        $mockRegistration->expects($this->once())->method('getEmail')->willReturn('email@domain.tld');

        $emailService = $this->getMockBuilder(EmailService::class)->getMock();
        $emailService->expects($this->once())->method('sendEmailMessage')->will($this->returnValue(true));
        $this->inject($this->subject, 'emailService', $emailService);

        $attachmentService = $this->getMockBuilder(AttachmentService::class)->getMock();
        $attachmentService->expects($this->once())->method('getAttachments');
        $this->inject($this->subject, 'attachmentService', $attachmentService);

        $hashService = $this->getMockBuilder(HashService::class)->getMock();
        $hashService->expects($this->once())->method('generateHmac')->will($this->returnValue('HMAC'));
        $hashService->expects($this->once())->method('appendHmac')->will($this->returnValue('HMAC'));
        $this->inject($this->subject, 'hashService', $hashService);

        $fluidStandaloneService = $this->getMockBuilder(FluidStandaloneService::class)
            ->setMethods(['renderTemplate', 'parseStringFluid'])
            ->disableOriginalConstructor()
            ->getMock();
        $fluidStandaloneService->expects($this->once())->method('renderTemplate')->will($this->returnValue(''));
        $fluidStandaloneService->expects($this->once())->method('parseStringFluid')->will($this->returnValue(''));
        $this->inject($this->subject, 'fluidStandaloneService', $fluidStandaloneService);

        $mockSignalSlotDispatcher = $this->getMockBuilder(Dispatcher::class)
            ->setMethods(['dispatch'])
            ->disableOriginalConstructor()
            ->getMock();
        $mockSignalSlotDispatcher->expects($this->once())->method('dispatch');
        $this->inject($this->subject, 'signalSlotDispatcher', $mockSignalSlotDispatcher);

        $result = $this->subject->sendAdminMessage($event, $mockRegistration, $settings, MessageRecipient::ADMIN);
        $this->assertTrue($result);
    }

    /**
     * Test if the adminEmail settings get exploded and only 2 e-mails get sent
     *
     * @test
     * @dataProvider messageTypeDataProvider
     * @param mixed $messageType
     * @return void
     */
    public function sendMultipleAdminNewRegistrationMessageReturnsTrueIfSendSuccessful($messageType)
    {
        $event = new Event();
        $registration = new Registration();

        $settings = [
            'notification' => [
                'senderEmail' => 'valid@email.tld',
                'adminEmail' => 'valid1@email.tld,valid2@email.tld ,invalid-email,,'
            ]
        ];

        $emailService = $this->getMockBuilder(EmailService::class)->getMock();
        $emailService->expects($this->exactly(3))->method('sendEmailMessage')->will($this->returnValue(true));
        $this->inject($this->subject, 'emailService', $emailService);

        $attachmentService = $this->getMockBuilder(AttachmentService::class)->getMock();
        $attachmentService->expects($this->once())->method('getAttachments');
        $this->inject($this->subject, 'attachmentService', $attachmentService);

        $hashService = $this->getMockBuilder(HashService::class)->getMock();
        $hashService->expects($this->once())->method('generateHmac')->will($this->returnValue('HMAC'));
        $hashService->expects($this->once())->method('appendHmac')->will($this->returnValue('HMAC'));
        $this->inject($this->subject, 'hashService', $hashService);

        $fluidStandaloneService = $this->getMockBuilder(FluidStandaloneService::class)
            ->setMethods(['renderTemplate', 'parseStringFluid'])
            ->disableOriginalConstructor()
            ->getMock();
        $fluidStandaloneService->expects($this->once())->method('renderTemplate')->will($this->returnValue(''));
        $fluidStandaloneService->expects($this->once())->method('parseStringFluid')->will($this->returnValue(''));
        $this->inject($this->subject, 'fluidStandaloneService', $fluidStandaloneService);

        $mockSignalSlotDispatcher = $this->getMockBuilder(Dispatcher::class)
            ->setMethods(['dispatch'])
            ->disableOriginalConstructor()
            ->getMock();
        $mockSignalSlotDispatcher->expects($this->once())->method('dispatch');
        $this->inject($this->subject, 'signalSlotDispatcher', $mockSignalSlotDispatcher);

        $result = $this->subject->sendAdminMessage($event, $registration, $settings, $messageType);
        $this->assertTrue($result);
    }

    /**
     * @test
     */
    public function sendUserMessageReturnsFalseIfNoCustomMessageGiven()
    {
        $event = new Event();
        $registration = new Registration();
        $registration->setEmail('valid@email.tld');

        $settings = ['notification' => ['senderEmail' => 'valid@email.tld']];

        $result = $this->subject->sendUserMessage($event, $registration, $settings, MessageType::CUSTOM_NOTIFICATION, '');
        $this->assertFalse($result);
    }

    /**
     * Test that only confirmed registrations get notified. Also test, if the ignoreNotifications
     * flag is evaluated
     *
     * @test
     * @return void
     */
    public function sendCustomNotificationReturnsExpectedAmountOfNotificationsSent()
    {
        $event = new Event();

        $registration1 = new Registration();
        $registration1->setConfirmed(true);
        $registration2 = new Registration();
        $registration2->setConfirmed(true);

        /** @var \TYPO3\CMS\Extbase\Persistence\ObjectStorage $registrations */
        $registrations = new ObjectStorage();
        $registrations->attach($registration1);
        $registrations->attach($registration2);

        $mockNotificationService = $this->getMockBuilder(NotificationService::class)
            ->setMethods(['sendUserMessage'])
            ->getMock();
        $mockNotificationService->expects($this->any())->method('sendUserMessage')->will($this->returnValue(true));

        $registrationRepository = $this->getMockBuilder(RegistrationRepository::class)
            ->setMethods(['findNotificationRegistrations'])
            ->disableOriginalConstructor()
            ->getMock();
        $registrationRepository->expects($this->once())->method('findNotificationRegistrations')->will(
            $this->returnValue($registrations)
        );
        $this->inject($mockNotificationService, 'registrationRepository', $registrationRepository);

        $customNotification = new CustomNotification();
        $customNotification->setTemplate('aTemplate');

        $result = $mockNotificationService->sendCustomNotification($event, $customNotification, ['someSettings']);
        $this->assertEquals(2, $result);
    }

    /**
     * @test
     * @return void
     */
    public function createCustomNotificationLogentryCreatesLog()
    {
        $mockLogRepo = $this->getMockBuilder(CustomNotificationLogRepository::class)
            ->setMethods(['add'])
            ->disableOriginalConstructor()
            ->getMock();
        $mockLogRepo->expects($this->once())->method('add');
        $this->inject($this->subject, 'customNotificationLogRepository', $mockLogRepo);

        $event = new Event();
        $this->subject->createCustomNotificationLogentry($event, 'A description', 1);
    }
}
