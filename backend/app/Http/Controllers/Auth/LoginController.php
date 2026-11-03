<?php

namespace App\Http\Controllers\Auth;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class LoginController
{
    public function create(): View
    {
        return view('auth.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'username' => ['required', 'string', 'max:60'],
            'password' => ['required', 'string', 'max:255'],
        ], ['required' => ':attribute wajib diisi.'], ['username' => 'Username', 'password' => 'Password']);
        $username = mb_strtolower(trim($data['username']));
        $key = 'login:'.hash('sha256', $username.'|'.$request->ip());

        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages(['username' => 'Terlalu banyak percobaan masuk. Coba lagi dalam '.RateLimiter::availableIn($key).' detik.']);
        }
        if (!Auth::attempt(['username' => $username, 'password' => $data['password'], 'is_active' => true])) {
            RateLimiter::hit($key, 60);
            throw ValidationException::withMessages(['username' => 'Username atau password tidak sesuai, atau akun tidak aktif.']);
        }

        RateLimiter::clear($key);
        $request->session()->regenerate();
        $request->user()->forceFill(['last_login_at' => now()])->save();

        $request->session()->forget('url.intended');
        return redirect()->route($request->user()->homeRoute());
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('login');
    }
}
