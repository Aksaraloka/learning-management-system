<?php

namespace App\Livewire;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Livewire\Component;

class Profile extends Component
{
    public string $name = '';

    public string $current_password = '';

    public string $password = '';

    public string $password_confirmation = '';

    public function mount(): void
    {
        $this->name = Auth::user()->name;
    }

    public function saveInfo(): void
    {
        $data = $this->validate(['name' => ['required', 'string', 'max:255']]);

        Auth::user()->update($data);

        $this->dispatch('toast', message: __('ui.profile.saved'), type: 'success');
    }

    public function savePassword(): void
    {
        $this->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ], [], ['current_password' => __('ui.profile.current_password'), 'password' => __('ui.auth.new_password')]);

        Auth::user()->update(['password' => $this->password]);

        $this->reset('current_password', 'password', 'password_confirmation');
        $this->dispatch('toast', message: __('ui.profile.saved'), type: 'success');
    }

    public function render(): View
    {
        return view('livewire.profile', ['user' => Auth::user()->load('roles')])
            ->title(__('ui.profile.title'));
    }
}
