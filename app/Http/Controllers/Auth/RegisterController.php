<?php

namespace App\Http\Controllers\Auth;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\Pharmacy;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class RegisterController extends Controller
{
    public function create(): View
    {
        return view('auth.register', ['roles' => Role::registrable()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'role' => ['required', Rule::enum(Role::class)->only(Role::registrable())],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(8)],
            'phone' => ['nullable', 'string', 'max:30'],
            'birthdate' => ['nullable', 'date', 'before:today'],
            'license_no' => ['required_if:role,doctor,pharmacy', 'nullable', 'string', 'max:50'],
            'pharmacy_name' => ['required_if:role,pharmacy', 'nullable', 'string', 'max:255'],
            'pharmacy_address' => ['nullable', 'string', 'max:255'],
        ]);

        $role = Role::from($data['role']);

        $user = DB::transaction(function () use ($data, $role) {
            $user = new User(collect($data)->only(['name', 'email', 'password', 'phone', 'birthdate', 'license_no'])->all());
            $user->role = $role;
            $user->approved_at = $role->needsApproval() ? null : now();
            $user->save();

            if ($role === Role::Pharmacy) {
                Pharmacy::create([
                    'user_id' => $user->id,
                    'name' => $data['pharmacy_name'],
                    'address' => $data['pharmacy_address'] ?? null,
                    'contact' => $data['phone'] ?? null,
                ]);
            }

            return $user;
        });

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('dashboard');
    }
}
