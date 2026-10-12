<?php

namespace App\Http\Controllers;

use App\Models\AcademicTerm;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class HomeController extends Controller
{
    public function __invoke(): View|RedirectResponse
    {
        $user = auth()->user();

        if (! $user) {
            return redirect()->route('login');
        }

        $stats = $user->isAdmin()
            ? ['users' => User::count(), 'terms' => AcademicTerm::count()]
            : [];

        return view('dashboard', ['user' => $user->load('roles'), 'stats' => $stats]);
    }
}
