<?php

namespace App\Http\Requests\Platform\Auth;

use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Platform Login Request
 * 
 * Validates and authenticates Platform Admins using the 'platform' guard.
 * Includes rate limiting to prevent brute force attacks.
 */
class LoginRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ];
    }

    /**
     * Attempt to authenticate the request's credentials using the platform guard.
     * 
     * CRITICAL: Uses Auth::guard('platform')->attempt() instead of Auth::attempt().
     * This ensures platform admins authenticate against the platform_admins table,
     * not the users table.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();

        // Use platform guard explicitly
        if (! Auth::guard('platform')->attempt(
            $this->only('email', 'password'),
            $this->boolean('remember')
        )) {
            RateLimiter::hit($this->throttleKey());

            throw ValidationException::withMessages([
                'email' => trans('auth.failed'),
            ]);
        }

        // Check if the admin account is active
        $admin = Auth::guard('platform')->user();
        if ($admin && !$admin->is_active) {
            Auth::guard('platform')->logout();
            
            throw ValidationException::withMessages([
                'email' => 'This account has been deactivated. Please contact support.',
            ]);
        }

        RateLimiter::clear($this->throttleKey());
    }

    /**
     * Ensure the login request is not rate limited.
     * 
     * Rate limit: 5 attempts per minute per email+IP combination.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout($this));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'email' => trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    /**
     * Get the rate limiting throttle key for the request.
     * 
     * Uses 'platform-' prefix to separate from tenant login rate limits.
     */
    public function throttleKey(): string
    {
        return 'platform-'.Str::transliterate(Str::lower($this->string('email')).'|'.$this->ip());
    }
}
