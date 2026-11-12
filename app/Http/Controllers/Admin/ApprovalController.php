<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ApprovalController extends Controller
{
    public function index(): View
    {
        return view('admin.approvals', [
            'pending' => User::whereNull('approved_at')
                ->whereIn('role', [Role::Doctor, Role::Pharmacy])
                ->with('pharmacy')->latest()->get(),
        ]);
    }

    public function approve(User $user): RedirectResponse
    {
        abort_unless($user->role->needsApproval(), 404);

        $user->forceFill(['approved_at' => now()])->save();

        return back()->with('status', $user->name.' approved.');
    }
}
