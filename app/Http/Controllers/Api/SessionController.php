<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Support\Locales;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Login, logout and language for the React app.
 *
 * Same users table, same session guard and same CSRF protection as the
 * Filament panel: the React app is served by this Laravel app, so the
 * session cookie already authenticates it. No token, no second auth system.
 */
class SessionController extends Controller
{
    /** Who is logged in, which language, and the words the UI needs. */
    public function show(Request $request): JsonResponse
    {
        return response()->json($this->payload($request));
    }

    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
            'remember' => ['boolean'],
        ]);

        $key = 'login:'.Str::lower($data['email']).'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages(['email' => __('app.spa.auth.trop')]);
        }

        if (! Auth::attempt(['email' => $data['email'], 'password' => $data['password']], $data['remember'] ?? false)) {
            RateLimiter::hit($key, 60);

            throw ValidationException::withMessages(['email' => __('app.spa.auth.echec')]);
        }

        RateLimiter::clear($key);
        $request->session()->regenerate();

        return response()->json($this->payload($request));
    }

    public function logout(Request $request): JsonResponse
    {
        $locale = $request->session()->get('locale');

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        // The language is the cashier's choice, not part of the login: keep it on the login screen.
        if ($locale) {
            $request->session()->put('locale', $locale);
        }

        return response()->json($this->payload($request));
    }

    /** Same switch as /langue/{locale}, answered in JSON. */
    public function locale(Request $request): JsonResponse
    {
        $data = $request->validate([
            'locale' => ['required', Rule::in(array_keys(Locales::SUPPORTED))],
        ]);

        session(['locale' => $data['locale']]);
        app()->setLocale($data['locale']);

        return response()->json($this->payload($request));
    }

    /** @return array<string, mixed> */
    private function payload(Request $request): array
    {
        $user = $request->user();
        $locale = app()->getLocale();

        return [
            'user' => $user ? ['id' => $user->id, 'name' => $user->name, 'email' => $user->email] : null,
            'locale' => $locale,
            'dir' => Locales::isRtl($locale) ? 'rtl' : 'ltr',
            'locales' => collect(Locales::SUPPORTED)
                ->map(fn (string $label, string $code) => ['code' => $code, 'label' => $label])
                ->values(),
            'societe' => Setting::get('societe_nom'),
            'devise' => Setting::get('devise', 'DH'),
            // The existing lang/{locale}/app.php, as is: the React UI uses
            // the same words as the rest of the application.
            'messages' => trans('app', [], $locale),
        ];
    }
}
