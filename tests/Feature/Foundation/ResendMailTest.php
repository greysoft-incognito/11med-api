<?php

namespace Tests\Feature\Foundation;

use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use ReflectionProperty;
use Resend\Client;
use Resend\Contracts\Client as ClientContract;
use Resend\Contracts\Transporter;
use Resend\ValueObjects\Transporter\Payload;
use Tests\TestCase;

class ResendMailTest extends TestCase
{
    public function test_the_resend_mailer_sends_through_the_resend_api(): void
    {
        $transporter = new class implements Transporter
        {
            public array $sent = [];

            public function request(Payload $payload): array
            {
                $this->sent[] = (new ReflectionProperty($payload, 'parameters'))->getValue($payload);

                return ['id' => 'email-1'];
            }
        };

        $client = new Client($transporter);

        $this->app->instance(ClientContract::class, $client);
        $this->app->instance('resend', $client);
        config([
            'mail.default' => 'resend',
            'mail.from.address' => 'no-reply@11med.ng',
            'mail.from.name' => '11med',
        ]);

        Mail::raw('Your code is 123456', fn($message) => $message
            ->to('ada@example.com')
            ->subject('Your code'));

        $this->assertCount(1, $transporter->sent);
        $this->assertSame('"11med" <no-reply@11med.ng>', $transporter->sent[0]['from']);
        $this->assertSame(['ada@example.com'], $transporter->sent[0]['to']);
        $this->assertSame('Your code', $transporter->sent[0]['subject']);
        $this->assertSame('Your code is 123456', $transporter->sent[0]['text']);
    }

    public function test_the_resend_webhook_routes_are_not_registered(): void
    {
        $this->assertFalse(Route::has('resend.webhook'));
    }
}
