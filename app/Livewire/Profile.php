<?php

namespace App\Livewire;

use App\Actions\Users\UpdateProfile;
use App\Support\Upload;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

class Profile extends Component
{
    use WithFileUploads;

    public string $name = '';

    public string $email = '';

    public string $phone = '';

    public string $bio = '';

    public ?TemporaryUploadedFile $avatar = null;

    public bool $removeAvatar = false;

    public string $current_password = '';

    public string $password = '';

    public string $password_confirmation = '';

    public function mount(): void
    {
        $user = Auth::user();

        $this->name = $user->name;
        $this->email = (string) $user->email;
        $this->phone = (string) $user->phone;
        $this->bio = (string) $user->bio;
    }

    public function saveInfo(UpdateProfile $update): void
    {
        $user = Auth::user();

        // Akun yang masuk dengan NIM boleh tanpa email; akun tanpa NIM wajib punya email agar tetap bisa masuk.
        $emailRule = $user->identity_number ? ['nullable'] : ['required'];

        $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => [...$emailRule, 'email:rfc', 'max:255', Rule::unique('users', 'email')->ignore($user->id)->whereNull('deleted_at')],
            'phone' => ['nullable', 'string', 'max:30', 'regex:/^[0-9+()\-\s]{6,30}$/'],
            'bio' => ['nullable', 'string', 'max:500'],
            'avatar' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:'.min(2048, Upload::maxKb())],
        ], [], [
            'name' => __('ui.profile.name'),
            'email' => 'Email',
            'phone' => __('ui.profile.phone'),
            'bio' => __('ui.profile.bio'),
            'avatar' => __('ui.profile.photo'),
        ]);

        $update->handle($user, [
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'bio' => $this->bio,
        ], $this->avatar, $this->removeAvatar);

        $this->reset('avatar', 'removeAvatar');
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
