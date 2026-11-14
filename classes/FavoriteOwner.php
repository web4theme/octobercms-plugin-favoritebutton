<?php namespace BABA\Favoritebutton\Classes;

use Auth;
use Cookie;

/**
 * Resolves the current favorite owner: a logged-in user, or a signed guest token.
 */
class FavoriteOwner
{
    public const COOKIE = 'baba_fav_owner';

    public static function key(): string
    {
        $user = self::user();

        return $user ? self::keyForUser($user->id) : self::guestKey();
    }

    public static function userId(): ?int
    {
        $user = self::user();

        return $user ? (int) $user->id : null;
    }

    public static function user()
    {
        try {
            return Auth::getUser();
        } catch (\Throwable $e) {
            return null;
        }
    }

    public static function keyForUser(int $userId): string
    {
        return hash_hmac('sha256', 'user:' . $userId, (string) config('app.key'));
    }

    protected static function guestKey(): string
    {
        $token = (string) request()->cookie(self::COOKIE);

        if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
            $token = (string) session()->get(self::COOKIE);

            if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
                $token = bin2hex(random_bytes(32));
            }

            session()->put(self::COOKIE, $token);
            Cookie::queue(
                self::COOKIE,
                $token,
                525600,
                '/',
                null,
                request()->isSecure(),
                true,
                false,
                'lax'
            );
        }

        return hash_hmac('sha256', 'guest:' . $token, (string) config('app.key'));
    }
}
