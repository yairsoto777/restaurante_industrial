<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Http\Request;

final class PasswordSession
{
    public static function fingerprint(User $user): string
    {
        return hash_hmac('sha256', $user->getAuthPassword(), (string) config('app.key'));
    }

    public static function bind(Request $request, User $user): void
    {
        $request->session()->put('auth_password_fingerprint', self::fingerprint($user));
    }

    public static function valid(Request $request, User $user): bool
    {
        $stored = $request->session()->get('auth_password_fingerprint');
        return is_string($stored) && hash_equals(self::fingerprint($user), $stored);
    }
}
