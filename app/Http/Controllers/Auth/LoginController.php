<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Lightweight session-based auth (no Breeze dependency).
 * Swap for Laravel Breeze/Fortify later if richer auth is needed.
 */
class LoginController extends Controller
{
    /** Max failed attempts before lockout per account. */
    private const MAX_ATTEMPTS = 5;

    /** Lockout duration in minutes. */
    private const LOCKOUT_MINUTES = 15;

    public function showLoginForm(): View
    {
        return view('auth.login');
    }

    public function login(LoginRequest $request): RedirectResponse
    {
        $credentials = $request->validated();

        $remember = $request->boolean('remember');

        // Per-account progressive lockout — keyed on the email so a bot
        // rotating IPs can't bypass a single global throttle.
        $lockoutKey = 'login.lockout:'.$credentials['email'];
        $attemptsKey = 'login.attempts:'.$credentials['email'];

        if (cache()->has($lockoutKey)) {
            $seconds = cache()->get($lockoutKey) - now()->timestamp;
            if ($seconds > 0) {
                return back()
                    ->withErrors(['email' => sprintf(
                        'This account is temporarily locked. Try again in %d minute(s).',
                        max(1, (int) ceil($seconds / 60)),
                    )])
                    ->onlyInput('email');
            }
            // Lockout expired — reset.
            cache()->forget($lockoutKey);
            cache()->forget($attemptsKey);
        }

        if (! Auth::attempt($credentials, $remember)) {
            // Increment failed-attempt counter and lock if threshold hit.
            $attempts = cache()->increment($attemptsKey);
            cache()->put($attemptsKey, $attempts, now()->addMinutes(self::LOCKOUT_MINUTES));

            $remaining = self::MAX_ATTEMPTS - $attempts;

            if ($remaining <= 0) {
                cache()->put($lockoutKey, now()->addMinutes(self::LOCKOUT_MINUTES)->timestamp, now()->addMinutes(self::LOCKOUT_MINUTES));

                return back()
                    ->withErrors(['email' => sprintf(
                        'Too many failed login attempts. This account is locked for %d minutes.',
                        self::LOCKOUT_MINUTES,
                    )])
                    ->onlyInput('email');
            }

            return back()
                ->withErrors(['email' => sprintf(
                    'These credentials do not match our records. %d attempt(s) remaining.',
                    $remaining,
                )])
                ->onlyInput('email');
        }

        // Successful login — clear any failed-attempt counter.
        cache()->forget($attemptsKey);
        cache()->forget($lockoutKey);

        $user = Auth::user();

        if (! $user->is_active) {
            Auth::logout();
            cache()->forget($attemptsKey);

            return back()->withErrors(['email' => 'This account has been deactivated.']);
        }

        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
