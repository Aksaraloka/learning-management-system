<?php

return [

    /*
    | Tenant ditentukan dari segmen pertama URL: /{slug}/login, /{slug}/admin/pengguna, dst.
    | Tanpa slug (/, /login, /platform/institusi) berarti domain utama: landing + panel super admin.
    | Tidak butuh domain, DNS, atau subdomain; cukup satu host (mis. localhost:8000).
    */

    /*
    | Spatie permission memakai kolom `tenant_id` sebagai "team". Peran platform
    | (super_admin) tidak punya tenant, jadi diberi team 0 karena kolom itu bagian primary key.
    */
    'platform_team_id' => 0,

    /*
    | Slug yang tidak boleh dipakai tenant karena bentrok dengan rute platform atau aset.
    | Segmen `livewire-*` (endpoint Livewire) otomatis dikecualikan.
    */
    'reserved_slugs' => [
        'login', 'logout', 'platform', 'admin', 'api', 'ui', 'up', 'theme', 'profil',
        'lupa-kata-sandi', 'reset-kata-sandi', 'kebijakan-privasi',
        'livewire', 'storage', 'build', 'vendor', 'samples', 'www', 'app', 'mail', 'static', 'assets',
    ],
];
