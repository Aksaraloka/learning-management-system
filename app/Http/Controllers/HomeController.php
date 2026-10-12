<?php

namespace App\Http\Controllers;

use App\Models\AcademicTerm;
use App\Models\User;
use App\Support\Tenancy;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class HomeController extends Controller
{
    public function __invoke(): View|RedirectResponse
    {
        $user = auth()->user();

        if (! $user) {
            return Tenancy::current() ? redirect()->route('login') : view('welcome');
        }

        if ($user->isSuperAdmin()) {
            return redirect()->route('platform.tenants.index');
        }

        $stats = $user->isAdmin()
            ? ['users' => User::count(), 'terms' => AcademicTerm::count()]
            : [];

        return view('dashboard', ['user' => $user->load('roles'), 'stats' => $stats]);
    }
}
