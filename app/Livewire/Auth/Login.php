<?php

namespace App\Livewire\Auth;

use App\Actions\Auth\AuthenticateUser;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class Login extends Component
{
    public string $identifier = '';

    public string $password = '';

    public bool $remember = false;

    public function login(AuthenticateUser $authenticate): void
    {
        $this->validate([
            'identifier' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string'],
        ], [], ['identifier' => __('ui.auth.identifier'), 'password' => __('ui.auth.password')]);

        $authenticate->handle($this->identifier, $this->password, $this->remember, request()->ip() ?? '');

        session()->regenerate();

        $this->redirectIntended(route('home'));
    }

    public function render(): View
    {
        return view('livewire.auth.login', ['ssoEnabled' => (bool) config('academic.enabled')])
            ->layout('components.layouts.auth')
            ->title(__('ui.auth.login'));
    }
}
