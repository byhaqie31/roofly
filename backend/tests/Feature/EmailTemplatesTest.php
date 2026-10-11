<?php

namespace Tests\Feature;

use App\Models\Agreement;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Property;
use App\Models\Unit;
use App\Models\User;
use App\Notifications\AdminInvite;
use App\Notifications\AdminNewEnquiry;
use App\Notifications\AgreementReviewed;
use App\Notifications\AgreementSent;
use App\Notifications\OwnerWarning;
use App\Notifications\OwnerWelcome;
use App\Notifications\PaymentClaimRejected;
use App\Notifications\PaymentClaimSubmitted;
use App\Notifications\ResetPassword;
use App\Notifications\TenantInvite;
use App\Notifications\WaitlistConfirmation;
use App\Notifications\WaitlistInvitation;
use Closure;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Notifications\Messages\MailMessage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/** Every Roofly email renders on the shared shell (emails/layout) with the legal footer. */
class EmailTemplatesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config([
            'app.frontend_url'      => 'https://uat.roofly.my/',
            'app.demo_url'          => 'https://demo.example.test',
            'app.invite_signup_url' => 'https://uat.roofly.my/auth/register',
        ]);
    }

    private static function person(string $name = 'Aminah binti Yusof'): User
    {
        return (new User)->forceFill(['id' => 'user-1', 'name' => $name, 'email' => 'aminah@example.com']);
    }

    private static function agreement(): Agreement
    {
        $unit = (new Unit)->forceFill(['label' => 'A-12-3'])
            ->setRelation('property', (new Property)->forceFill(['name' => 'Residensi Harmoni']));

        return (new Agreement)->forceFill(['id' => 'agr-1', 'rent_amount_cents' => 180000])
            ->setRelation('unit', $unit)
            ->setRelation('tenant', self::person('Arif Hakim'));
    }

    private static function payment(): Payment
    {
        return (new Payment)->forceFill([
            'id'               => 'pay-1',
            'amount_cents'     => 180000,
            'reference'        => 'DN-778812',
            'paid_at'          => '2026-10-05',
            'rejection_reason' => 'Nothing arrived in Maybank',
        ]);
    }

    private static function invoice(): Invoice
    {
        return (new Invoice)->forceFill(['invoice_number' => 'INV-0042']);
    }

    /** @return iterable<string, array{Closure(): array{0: object, 1: object}, list<string>}> */
    public static function emails(): iterable
    {
        yield 'agreement sent' => [
            fn () => [new AgreementSent(self::agreement()), self::person()],
            ['TENANCY AGREEMENT', 'Hi Aminah,', 'Residensi Harmoni · A-12-3', 'RM 1,800.00', 'Tuan rumah anda telah menghantar', 'https://uat.roofly.my/tenant/agreement', 'Review agreement'],
        ];
        yield 'agreement accepted' => [
            fn () => [new AgreementReviewed(self::agreement(), true), self::person()],
            ['AGREED BY TENANT', 'Arif Hakim has agreed', 'Penyewa anda telah bersetuju', 'https://uat.roofly.my/owner/agreements/agr-1', 'Activate agreement'],
        ];
        yield 'agreement changes requested' => [
            fn () => [new AgreementReviewed(self::agreement(), false, 'Can we start on the 15th?'), self::person()],
            ['CHANGES REQUESTED', 'Can we start on the 15th?', 'Penyewa anda meminta perubahan', 'https://uat.roofly.my/owner/agreements/agr-1', 'Edit and re-send'],
        ];
        yield 'payment claim submitted' => [
            fn () => [new PaymentClaimSubmitted(self::payment(), self::invoice(), 'Arif Hakim'), self::person()],
            ['AWAITING CONFIRMATION', 'INV-0042', 'RM 1,800.00', 'DN-778812', '5 Oct 2026', 'Sila semak', 'https://uat.roofly.my/owner/payments?status=awaiting', 'Review payment'],
        ];
        yield 'payment claim rejected' => [
            fn () => [new PaymentClaimRejected(self::payment(), self::invoice()), self::person()],
            ['NOT CONFIRMED', 'INV-0042', 'DN-778812', 'Nothing arrived in Maybank', 'tidak dapat mengesahkan', 'https://uat.roofly.my/tenant/payments', 'View invoice'],
        ];
        yield 'password reset' => [
            fn () => [new ResetPassword('reset-token'), self::person()],
            ['ACCOUNT SECURITY', 'Hi Aminah,', 'Kami menerima permintaan', 'https://uat.roofly.my/auth/reset-password?token=reset-token', 'Reset password', 'This link expires in'],
        ];
        yield 'tenant invite' => [
            fn () => [new TenantInvite('invite-token', 'Lim Wei Ling'), self::person()],
            ['TENANT INVITE', 'Lim Wei Ling has set up your tenancy', 'Tuan rumah anda telah menyediakan', 'https://uat.roofly.my/auth/accept-invite?token=invite-token', 'Set your password', 'This link expires in 7 days.'],
        ];
        yield 'admin invite' => [
            fn () => [new AdminInvite('admin-token'), self::person()],
            ['ADMIN PORTAL', 'Roofly admin portal', 'https://uat.roofly.my/admin/accept-invite?token=admin-token', 'Set your password', 'This link expires in 7 days.'],
        ];
        yield 'owner warning' => [
            fn () => [new OwnerWarning(OwnerWarning::TEMPLATE_PAYMENT_OVERDUE, '20 Oct 2026', 'Call us if you need more time.'), self::person()],
            ['ACTION NEEDED', 'suspended on 20 Oct 2026', 'Call us if you need more time.', 'already settled this', 'https://uat.roofly.my/owner/settings?tab=plan', 'Review your plan'],
        ];
        yield 'owner welcome' => [
            fn () => [new OwnerWelcome, self::person()],
            ['WELCOME ABOARD', 'Hi Aminah,', 'https://uat.roofly.my/owner'],
        ];
        yield 'waitlist confirmation' => [
            fn () => [new WaitlistConfirmation, new AnonymousNotifiable],
            ['WAITLIST CONFIRMED', 'https://demo.example.test'],
        ];
        yield 'waitlist invitation' => [
            fn () => [new WaitlistInvitation('lead@example.com'), new AnonymousNotifiable],
            ['YOUR INVITATION HAS ARRIVED', 'https://uat.roofly.my/auth/register?email=lead%40example.com'],
        ];
        yield 'admin alert' => [
            fn () => [AdminNewEnquiry::make('lead@example.com'), self::person()],
            ['NEW ENQUIRY', 'https://uat.roofly.my/admin/enquiries'],
        ];
    }

    /** @return array{0: MailMessage, 1: string, 2: string} message, html, text */
    private function render(Closure $make): array
    {
        [$notification, $notifiable] = $make();
        $mail = $notification->toMail($notifiable);

        $this->assertIsArray($mail->view, 'renders a branded html + text view pair, not the Laravel default template');

        return [$mail, $mail->render(), view($mail->view[1], $mail->viewData)->render()];
    }

    #[DataProvider('emails')]
    public function test_renders_on_the_branded_shell(Closure $make, array $expected): void
    {
        [, $html] = $this->render($make);

        $this->assertStringContainsString('Roofly<span style="color:#DD7047;">.</span>', $html);
        $this->assertStringContainsString('The Roofly Team', $html);
        foreach ($expected as $needle) {
            $this->assertStringContainsString(e($needle), $html);
        }
    }

    #[DataProvider('emails')]
    public function test_html_and_text_both_carry_the_legal_footer(Closure $make, array $_expected): void
    {
        [, $html, $text] = $this->render($make);

        $this->assertStringContainsString('href="https://uat.roofly.my/legal/privacy"', $html);
        $this->assertStringContainsString('href="https://uat.roofly.my/legal/terms"', $html);
        $this->assertStringContainsString('Axel Nova Ventures', $html);

        $this->assertStringContainsString('https://uat.roofly.my/legal/privacy', $text);
        $this->assertStringContainsString('https://uat.roofly.my/legal/terms', $text);
        $this->assertStringContainsString('Axel Nova Ventures', $text);
        $this->assertStringNotContainsString('<', $text, 'plain-text part has no markup');
    }

    public function test_text_part_has_the_button_link_and_the_malay_copy(): void
    {
        [, , $text] = $this->render(fn () => [new AgreementSent(self::agreement()), self::person()]);

        $this->assertStringContainsString('Review agreement: https://uat.roofly.my/tenant/agreement', $text);
        $this->assertStringContainsString('Tuan rumah anda telah menghantar', $text);
        $this->assertStringContainsString('Monthly rent: RM 1,800.00', $text);
    }

    public function test_user_written_text_is_escaped(): void
    {
        [, $html] = $this->render(fn () => [new AgreementReviewed(self::agreement(), false, '<script>alert(1)</script>'), self::person()]);

        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
        $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $html);
    }

    public function test_footer_drops_unset_operator_details(): void
    {
        config(['legal.operator.ssm_number' => null, 'legal.operator.registered_address' => null, 'legal.contact.email' => null]);

        [, $html, $text] = $this->render(fn () => [new ResetPassword('t'), self::person()]);

        $this->assertStringNotContainsString('SSM', $html);
        $this->assertStringNotContainsString('Contact us', $html);
        $this->assertStringNotContainsString('Contact us', $text);
        $this->assertStringContainsString('Axel Nova Ventures', $html);
    }

    public function test_greeting_falls_back_without_a_name(): void
    {
        [, $html] = $this->render(fn () => [new ResetPassword('t'), self::person('')]);

        $this->assertStringContainsString('Hi there,', $html);
    }

    public function test_subjects_are_unchanged(): void
    {
        $subject = fn (object $n) => $n->toMail(self::person())->subject;

        $this->assertSame('Your tenancy agreement is ready to review', $subject(new AgreementSent(self::agreement())));
        $this->assertSame('Arif Hakim agreed to the tenancy agreement', $subject(new AgreementReviewed(self::agreement(), true)));
        $this->assertSame('Arif Hakim asked for changes to the agreement', $subject(new AgreementReviewed(self::agreement(), false, 'x')));
        $this->assertSame("Arif Hakim says they've paid INV-0042", $subject(new PaymentClaimSubmitted(self::payment(), self::invoice(), 'Arif Hakim')));
        $this->assertSame("Your payment for INV-0042 wasn't confirmed", $subject(new PaymentClaimRejected(self::payment(), self::invoice())));
        $this->assertSame('Reset your Roofly password', $subject(new ResetPassword('t')));
        $this->assertSame('Lim Wei Ling invited you to Roofly', $subject(new TenantInvite('t', 'Lim Wei Ling')));
        $this->assertSame('You have been invited to Roofly', $subject(new TenantInvite('t')));
        $this->assertSame('You have been invited to the Roofly admin portal', $subject(new AdminInvite('t')));
        $this->assertSame('Action needed: your Roofly subscription', $subject(new OwnerWarning(OwnerWarning::TEMPLATE_PAYMENT_OVERDUE, '20 Oct 2026')));
    }
}
