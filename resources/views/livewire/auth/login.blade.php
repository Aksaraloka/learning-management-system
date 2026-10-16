<div>
    <h1 class="text-3xl font-bold">{{ __('ui.auth.login_title') }}</h1>

    @if ($ssoEnabled)
        <p class="mt-1 text-sm text-text-muted">{{ __('ui.auth.sso_hint') }}</p>

        @if (session('sso_error'))
            <p role="alert" class="mt-4 rounded-lg border border-danger/40 bg-danger/10 px-4 py-3 text-sm text-danger">{{ session('sso_error') }}</p>
        @endif

        <x-button :href="route('academic.redirect')" size="lg" icon="academic-cap" class="mt-8 w-full">{{ __('ui.auth.sso_button') }}</x-button>

        {{-- Jalur darurat admin bila SSO gagal; tetap terbuka bila sebelumnya ada galat kata sandi. --}}
        <div class="mt-6" x-data="{ admin: @js($errors->has('identifier')) }">
            <button type="button" class="text-sm text-primary underline" x-on:click="admin = ! admin" x-bind:aria-expanded="admin">{{ __('ui.auth.admin_login') }}</button>

            <div x-show="admin" x-cloak>
                <p class="mt-3 text-sm text-text-muted">{{ __('ui.auth.admin_login_hint') }}</p>
                @include('livewire.auth.password-form')
            </div>
        </div>
    @else
        <p class="mt-1 text-sm text-text-muted">{{ __('ui.auth.login_hint') }}</p>

        @include('livewire.auth.password-form', ['autofocus' => true])
    @endif
</div>
