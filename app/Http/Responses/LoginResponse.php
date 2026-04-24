<?php

namespace App\Http\Responses;

use Illuminate\Http\Request;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;

class LoginResponse implements LoginResponseContract
{
    public function toResponse($request)
    {
        /** @var Request $request */
        $user = $request->user();

        $intended = session()->get('url.intended');
        if ($intended && $user && $this->canVisitIntended($user, $intended)) {
            return redirect()->to($intended);
        }

        session()->forget('url.intended');

        return redirect()->to($this->homeFor($user));
    }

    private function homeFor($user): string
    {
        if (!$user) {
            return '/';
        }

        $candidates = [
            ['permission' => 'dashboard.access', 'path' => '/dashboard'],
            ['permission' => 'pembayaran.access', 'path' => '/pembayaran'],
            ['permission' => 'pesanan.access', 'path' => '/pesanan'],
            ['permission' => 'waiting-list.access', 'path' => '/admin/waiting-list'],
            ['permission' => 'kitchen.access', 'path' => '/kitchen'],
            ['permission' => 'menu.access', 'path' => '/menu'],
            ['permission' => 'meja.access', 'path' => '/meja'],
            ['permission' => 'kategori.access', 'path' => '/kategori'],
            ['permission' => 'addon.access', 'path' => '/addon'],
            ['permission' => 'pajak.access', 'path' => '/pajak'],
            ['permission' => 'diskon.access', 'path' => '/diskon'],
            ['permission' => 'users.access', 'path' => '/users'],
            ['permission' => 'roles.access', 'path' => '/roles'],
        ];

        foreach ($candidates as $candidate) {
            if ($user->can($candidate['permission'])) {
                return $candidate['path'];
            }
        }

        return '/';
    }

    private function canVisitIntended($user, string $intendedUrl): bool
    {
        $path = parse_url($intendedUrl, PHP_URL_PATH) ?: '/';

        $map = [
            '/dashboard' => 'dashboard.access',
            '/pembayaran' => 'pembayaran.access',
            '/pesanan' => 'pesanan.access',
            '/admin/waiting-list' => 'waiting-list.access',
            '/kitchen' => 'kitchen.access',
            '/menu' => 'menu.access',
            '/meja' => 'meja.access',
            '/kategori' => 'kategori.access',
            '/addon' => 'addon.access',
            '/pajak' => 'pajak.access',
            '/diskon' => 'diskon.access',
            '/users' => 'users.access',
            '/roles' => 'roles.access',
        ];

        $bestPrefix = null;
        foreach (array_keys($map) as $prefix) {
            if (str_starts_with($path, $prefix) && ($bestPrefix === null || strlen($prefix) > strlen($bestPrefix))) {
                $bestPrefix = $prefix;
            }
        }

        if ($bestPrefix === null) {
            return true;
        }

        return $user->can($map[$bestPrefix]);
    }
}
