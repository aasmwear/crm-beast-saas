<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class ConfirmablePasswordController extends Controller
{
    /**
     * Handle the incoming password confirmation.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'password' => ['required', 'string'],
        ]);

        $plain = (string) ($validated['password'] ?? '');

        /** @var \App\Models\User $user */
        $user = $request->user();

        if (! Hash::check($plain, (string) $user->getAuthPassword())) {
            return back()->withErrors([
                'password' => __('The provided password is incorrect.'),
            ]);
        }

        $request->session()->put('auth.password_confirmed_at', time());

        return redirect()->intended();
    }
}
