<?php

namespace Tests\Feature;

use App\Domains\Expenses\Notifications\OcrScanCompletedNotification;
use App\Domains\Organizations\Models\Organization;
use App\Domains\Organizations\Models\OrganizationInvitation;
use App\Domains\Organizations\Notifications\InvitationNotification;
use App\Domains\Users\Models\User;
use App\Domains\Users\Notifications\TwoFactorDisabledNotification;
use App\Domains\Users\Notifications\VerifyNewEmailNotification;
use Tests\TestCase;

class NotificationMailContentTest extends TestCase
{
    public function test_security_and_ocr_notifications_define_greeting_and_salutation(): void
    {
        app()->setLocale('fr');
        $user = new User(['name' => 'Camille Martin']);

        $messages = [
            (new TwoFactorDisabledNotification)->toMail($user),
            (new VerifyNewEmailNotification('token'))->toMail($user),
            (new OcrScanCompletedNotification('receipt.pdf', true, 'scan-1'))->toMail($user),
        ];

        foreach ($messages as $message) {
            $this->assertNotEmpty($message->greeting);
            $this->assertNotEmpty($message->salutation);
            $this->assertStringNotContainsString('Hello!', $message->greeting);
            $this->assertStringNotContainsString('Regards,', $message->salutation);
        }
    }

    public function test_invitation_notification_defines_a_signature(): void
    {
        app()->setLocale('fr');
        $organization = new Organization(['name' => 'Alpine Services SA']);
        $invitation = new OrganizationInvitation;
        $invitation->setRelation('organization', $organization);

        $message = (new InvitationNotification($invitation, 'plain-token'))
            ->toMail(new User(['name' => 'Invité']));

        $this->assertSame('Bonjour !', $message->greeting);
        $this->assertSame('Cordialement, Gäld', $message->salutation);
        $this->assertStringContainsString('/invitations/plain-token/accept', $message->actionUrl);
    }
}
