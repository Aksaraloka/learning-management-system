<?php

use App\Enums\Role;

it('renders dashboard numbers server-side so they are correct without JavaScript', function () {
    makeUser(Role::Student);

    $this->actingAs(makeUser(Role::Admin))->get('/')
        ->assertOk()
        ->assertSee('x-countup', false)
        ->assertSee('x-reveal', false)
        ->assertSeeInOrder(['>2<', '>0<', '>0<'], false);
});

it('marks course lists as revealable', function () {
    $teacher = makeUser(Role::Teacher);
    makeCourse($teacher);

    $this->actingAs($teacher)->get('/kelas')->assertOk()->assertSee('x-reveal', false);
});

it('keeps animation out of the critical path', function () {
    $entry = file_get_contents(resource_path('js/app.js'));
    $motion = file_get_contents(resource_path('js/lib/motion.js'));

    // GSAP hanya boleh dimuat lewat import() dinamis, dan hormati reduced-motion.
    expect($entry)->not->toContain("from 'gsap'")
        ->and($motion)->not->toMatch("/^import .* from 'gsap'/m")
        ->and($motion)->toContain("import('gsap')")
        ->and($motion)->toContain('prefers-reduced-motion');
});
