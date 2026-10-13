<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Support\Dashboard;
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

        $kind = Dashboard::viewFor($user);

        $data = match ($kind) {
            'admin' => Dashboard::admin(),
            'principal' => Dashboard::principal(),
            'teacher' => Dashboard::teacher($user),
            'student' => Dashboard::student($user),
            default => [],
        };

        return view('dashboard', [
            'user' => $user->load('roles'),
            'kind' => $kind,
            'data' => $data,
            'courses' => $kind === 'basic'
                ? Course::query()->visibleTo($user)->with(['term', 'teachers'])->withCount('students')->orderBy('name')->limit(6)->get()
                : collect(),
        ]);
    }
}
