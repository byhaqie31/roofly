<?php

namespace App\Support;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Verifies a Google Identity Services ID token (the credential the GIS
 * button hands the SPA) via Google's tokeninfo endpoint. Zero dependencies;
 * bound in AppServiceProvider with the configured client id so it can be
 * swapped with a mock in tests.
 */
class GoogleIdToken
{
    private const ENDPOINT = 'https://oauth2.googleapis.com/tokeninfo';

    private const ISSUERS = ['accounts.google.com', 'https://accounts.google.com'];

    public function __construct(private readonly string $clientId) {}

    /** @return array{sub:string,email:string,name:string,picture:?string}|null */
    public function verify(string $credential): ?array
    {
        if ($this->clientId === '') {
            return null;
        }

        try {
            $res = Http::timeout(5)->get(self::ENDPOINT, ['id_token' => $credential]);
        } catch (ConnectionException) {
            return null;
        }
        if (! $res->ok()) {
            return null;
        }
        $p = $res->json();

        if (($p['aud'] ?? null) !== $this->clientId) {
            return null;
        }
        if (! in_array($p['iss'] ?? '', self::ISSUERS, true)) {
            return null;
        }
        if ((int) ($p['exp'] ?? 0) < time()) {
            return null;
        }
        if (($p['email_verified'] ?? 'false') !== 'true') {
            return null;
        }
        if (empty($p['sub']) || empty($p['email'])) {
            return null;
        }

        $email = strtolower($p['email']);

        return [
            'sub' => (string) $p['sub'],
            'email' => $email,
            'name' => (string) ($p['name'] ?? $email),
            'picture' => isset($p['picture']) ? (string) $p['picture'] : null,
        ];
    }
}
