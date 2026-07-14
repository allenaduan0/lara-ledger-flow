<?php

namespace App\Modules\Identity\Presentation\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Identity\Application\Actions\RegisterUserAction;
use App\Modules\Identity\Domain\Authorization\RoleName;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AdminSessionController extends Controller
{
    public function create(): View
    {
        return view('auth.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate(['email' => ['required', 'email'], 'password' => ['required', 'string']]);
        if (! Auth::guard('web')->attempt($credentials, $request->boolean('remember'))) {
            throw ValidationException::withMessages(['email' => ['The provided credentials are invalid.']]);
        }
        $request->session()->regenerate();

        return redirect()->intended($request->user()->hasRole(RoleName::Administrator->value) ? route('admin.dashboard') : route('customer.dashboard'));
    }

    public function register(): View
    {
        return view('auth.register');
    }

    public function createAccount(Request $request, RegisterUserAction $action): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(12)->letters()->mixedCase()->numbers()],
        ]);
        $user = $action->execute($data['name'], strtolower($data['email']), $data['password']);
        Auth::guard('web')->login($user);
        $request->session()->regenerate();

        return redirect()->route('customer.dashboard');
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
