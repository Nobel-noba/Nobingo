<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class ForcePasswordResetController extends Controller
{
    /**
     * Display the force password reset view.
     */
    public function show(Request $request): Response|RedirectResponse
    {
        $user = $request->user();

        if (! $user || ! $user->mustResetPassword()) {
            return redirect()->route('dashboard');
        }

        return Inertia::render('Auth/ForcePasswordReset', [
            'user' => [
                'name' => $user->name,
                'email' => $user->email,
            ],
        ]);
    }

    /**
     * Handle the force password reset submission.
     */
    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();

        if (! $user) {
            return redirect()->route('login');
        }

        $validated = $request->validate([
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $user->forceFill([
            'password' => Hash::make($validated['password']),
            'must_reset_password' => false,
        ])->save();

        return redirect()->route('dashboard')->with('success', 'Your password has been successfully established! Welcome to your account.');
    }
}
