<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Login dengan Google (OAuth 2.0 Authorization Code).
 * Token ditukar langsung dengan Google memakai client secret, lalu profil diambil dari
 * endpoint OpenID Connect resmi, jadi tidak perlu memverifikasi JWT sendiri.
 */
class GoogleOAuth
{
    private const AUTH_URL = 'https://accounts.google.com/o/oauth2/v2/auth';
    private const TOKEN_URL = 'https://oauth2.googleapis.com/token';
    private const USERINFO_URL = 'https://openidconnect.googleapis.com/v1/userinfo';

    public function enabled(): bool
    {
        return filled(config('services.google.client_id')) && filled(config('services.google.client_secret'));
    }

    public function redirectUri(): string
    {
        return config('services.google.redirect') ?: route('auth.google.callback');
    }

    public function authUrl(string $state): string
    {
        return self::AUTH_URL . '?' . http_build_query([
            'client_id'     => config('services.google.client_id'),
            'redirect_uri'  => $this->redirectUri(),
            'response_type' => 'code',
            'scope'         => 'openid email profile',
            'state'         => $state,
            'prompt'        => 'select_account',
        ]);
    }

    /**
     * @return array{id:string,email:string,name:string,avatar:?string,verified:bool}
     */
    public function profile(string $code): array
    {
        $token = Http::asForm()->timeout(10)->post(self::TOKEN_URL, [
            'code'          => $code,
            'client_id'     => config('services.google.client_id'),
            'client_secret' => config('services.google.client_secret'),
            'redirect_uri'  => $this->redirectUri(),
            'grant_type'    => 'authorization_code',
        ]);

        if ($token->failed() || !$token->json('access_token')) {
            throw new RuntimeException('Google menolak kode login (kedaluwarsa atau redirect URI tidak cocok).');
        }

        $info = Http::withToken($token->json('access_token'))->timeout(10)->get(self::USERINFO_URL);

        if ($info->failed() || !$info->json('sub') || !$info->json('email')) {
            throw new RuntimeException('Gagal membaca profil dari Google.');
        }

        return [
            'id'       => (string) $info->json('sub'),
            'email'    => strtolower($info->json('email')),
            'name'     => $info->json('name') ?: strtok($info->json('email'), '@'),
            'avatar'   => $info->json('picture'),
            'verified' => filter_var($info->json('email_verified'), FILTER_VALIDATE_BOOLEAN),
        ];
    }
}
