<?php

namespace App\Support;

use App\Enums\Role;
use App\Models\User;

class Navigation
{
    /**
     * Item navigasi (maks. 5, dipakai sidebar dan bottom nav) sesuai peran.
     * Rute yang belum ada dirender sebagai `#` oleh layout.
     *
     * @return list<array{label: string, icon: string, route: string, match?: list<string>}>
     */
    public static function for(?User $user): array
    {
        $profile = ['label' => __('ui.nav.profile'), 'icon' => 'user', 'route' => 'profile.edit'];
        $home = ['label' => __('ui.nav.home'), 'icon' => 'home', 'route' => 'home'];
        $classes = ['label' => __('ui.nav.classes'), 'icon' => 'book-open', 'route' => 'courses.index', 'match' => ['courses.*', 'materials.*']];

        if ($user?->hasAppRole(Role::Admin)) {
            return [
                $home,
                ['label' => __('ui.nav.users'), 'icon' => 'users', 'route' => 'admin.users.index', 'match' => ['admin.users.*']],
                ['label' => __('ui.nav.terms'), 'icon' => 'calendar', 'route' => 'admin.terms.index', 'match' => ['admin.terms.*']],
                $classes,
                $profile,
            ];
        }

        return [
            $home,
            $classes,
            ['label' => __('ui.nav.tasks'), 'icon' => 'clipboard-list', 'route' => 'assignments.index'],
            ['label' => __('ui.nav.notifications'), 'icon' => 'bell', 'route' => 'notifications.index'],
            $profile,
        ];
    }
}
