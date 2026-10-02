<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class RegisteredUserController extends Controller
{
    /**
     * Display the registration view (Disabled: direct to login).
     */
    public function create(): RedirectResponse
    {
        return redirect()->route('login')->with('error', 'Public self-registration is closed. Player accounts are created at the venue counter by game managers.');
    }

    /**
     * Handle an incoming registration request (Disabled: direct to login).
     */
    public function store(Request $request): RedirectResponse
    {
        return redirect()->route('login')->with('error', 'Public self-registration is closed. Player accounts are created at the venue counter by game managers.');
    }
}
