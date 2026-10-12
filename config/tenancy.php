<?php

return [

    /*
    | Domain utama platform (landing + panel super admin). Setiap tenant
    | dilayani di {subdomain}.{root_domain}. Host lain (localhost, IP) dianggap
    | domain utama agar mudah dipakai saat development.
    */
    'root_domain' => env('TENANT_ROOT_DOMAIN', 'aksaraloka.test'),

    /*
    | Spatie permission memakai kolom `tenant_id` sebagai "team". Peran platform
    | (super_admin) tidak punya tenant, jadi diberi team 0 karena kolom itu bagian primary key.
    */
    'platform_team_id' => 0,

    // Subdomain yang tidak boleh dipakai tenant.
    'reserved_subdomains' => ['www', 'admin', 'api', 'app', 'mail', 'static', 'assets'],
];
