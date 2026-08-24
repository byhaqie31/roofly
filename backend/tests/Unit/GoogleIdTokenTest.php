<?php

namespace Tests\Unit;

use App\Support\GoogleIdToken;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GoogleIdTokenTest extends TestCase
{
    private function payload(array $over = []): array
    {
        return array_merge([
            'iss' => 'https://accounts.google.com',
            'aud' => 'client-123',
            'sub' => '10769150350006150715113082367',
            'email' => 'Owner@Example.com',
            'email_verified' => 'true',
            'exp' => (string) (time() + 600),
            'name' => 'Owner Person',
            'picture' => 'https://lh3.googleusercontent.com/a/pic',
        ], $over);
    }

    public function test_valid_token_returns_normalised_profile(): void
    {
        Http::fake(['oauth2.googleapis.com/*' => Http::response($this->payload())]);
        $out = (new GoogleIdToken('client-123'))->verify('tok');
        $this->assertSame([
            'sub' => '10769150350006150715113082367',
            'email' => 'owner@example.com',
            'name' => 'Owner Person',
            'picture' => 'https://lh3.googleusercontent.com/a/pic',
        ], $out);
    }

    public function test_rejects_wrong_audience(): void
    {
        Http::fake(['oauth2.googleapis.com/*' => Http::response($this->payload(['aud' => 'other']))]);
        $this->assertNull((new GoogleIdToken('client-123'))->verify('tok'));
    }

    public function test_rejects_unverified_email(): void
    {
        Http::fake(['oauth2.googleapis.com/*' => Http::response($this->payload(['email_verified' => 'false']))]);
        $this->assertNull((new GoogleIdToken('client-123'))->verify('tok'));
    }

    public function test_rejects_expired_and_bad_issuer_and_google_error(): void
    {
        Http::fake(['oauth2.googleapis.com/*' => Http::response($this->payload(['exp' => (string) (time() - 5)]))]);
        $this->assertNull((new GoogleIdToken('client-123'))->verify('tok'));

        Http::fake(['oauth2.googleapis.com/*' => Http::response($this->payload(['iss' => 'evil.example']))]);
        $this->assertNull((new GoogleIdToken('client-123'))->verify('tok'));

        Http::fake(['oauth2.googleapis.com/*' => Http::response(['error' => 'invalid_token'], 400)]);
        $this->assertNull((new GoogleIdToken('client-123'))->verify('tok'));
    }

    public function test_rejects_when_client_id_is_empty(): void
    {
        Http::fake(['oauth2.googleapis.com/*' => Http::response($this->payload())]);
        $this->assertNull((new GoogleIdToken(''))->verify('tok'));
    }

    public function test_rejects_missing_sub_or_email(): void
    {
        Http::fake(['oauth2.googleapis.com/*' => Http::response($this->payload(['sub' => '']))]);
        $this->assertNull((new GoogleIdToken('client-123'))->verify('tok'));

        Http::fake(['oauth2.googleapis.com/*' => Http::response($this->payload(['email' => '']))]);
        $this->assertNull((new GoogleIdToken('client-123'))->verify('tok'));
    }

    public function test_rejects_on_connection_exception(): void
    {
        Http::fake(function () {
            throw new ConnectionException('timeout');
        });
        $this->assertNull((new GoogleIdToken('client-123'))->verify('tok'));
    }
}
