<?php

namespace App\Http\Controllers;

use App\Models\AcademicTerm;
use App\Models\Course;
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
            ? ['users' => User::count(), 'terms' => AcademicTerm::count(), 'courses' => Course::count()]
            : [];

        return view('dashboard', [
            'user' => $user->load('roles'),
            'stats' => $stats,
            'courses' => Course::query()->visibleTo($user)->with(['term', 'teachers'])->withCount('students')->orderBy('name')->limit(6)->get(),
        ]);
    }
}
