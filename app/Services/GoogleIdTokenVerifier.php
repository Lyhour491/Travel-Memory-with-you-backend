<?php

namespace App\Services;

use Google\Client as GoogleClient;
use LogicException;

class GoogleIdTokenVerifier
{
    /**
     * @return array<string, mixed>|null
     */
    public function verify(string $idToken): ?array
    {
        $clientId = config('services.google.client_id');

        if (! is_string($clientId) || $clientId === '') {
            throw new LogicException('GOOGLE_CLIENT_ID is not configured.');
        }

        $payload = (new GoogleClient(['client_id' => $clientId]))->verifyIdToken($idToken);

        return is_array($payload) ? $payload : null;
    }
}
