<?php

namespace App\Livewire\Auth;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Password;
use Livewire\Component;

class ForgotPassword extends Component
{
    public string $email = '';

    public bool $sent = false;

    public function send(): void
    {
        $this->validate(['email' => ['required', 'email']]);

        // Pesan sama untuk email terdaftar maupun tidak, agar akun tidak bisa ditebak.
        Password::sendResetLink(['email' => $this->email]);

        $this->sent = true;
    }

    public function render(): View
    {
        return view('livewire.auth.forgot-password')
            ->layout('components.layouts.guest')
            ->title(__('ui.auth.forgot_title'));
    }
}
