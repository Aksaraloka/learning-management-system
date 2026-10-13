<?php

namespace App\Livewire;

use App\Models\Course;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * Halaman profil: hanya menampilkan data akun. Mengubah data ada di halaman terpisah (ProfileEdit).
 */
class Profile extends Component
{
    public function render(): View
    {
        $user = Auth::user();

        return view('livewire.profile', [
            'user' => $user->load('roles'),
            'courses' => Course::query()->visibleTo($user)->with('term')->orderBy('name')->limit(8)->get(),
        ])->title(__('ui.profile.title'));
    }
}
