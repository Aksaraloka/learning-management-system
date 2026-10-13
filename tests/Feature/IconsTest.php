<?php

use App\Enums\MaterialType;
use App\Enums\QuestionType;
use Symfony\Component\Finder\Finder;

/**
 * Nama ikon yang dipakai di kode (statis) harus ada di Heroicons (resources/icons).
 */
function usedIconNames(): array
{
    $names = [];

    foreach ((new Finder)->files()->in([app_path(), resource_path('views')])->name(['*.php', '*.blade.php']) as $file) {
        $source = $file->getContents();

        foreach ([
            '/<x-icon[^>]*\sname="([a-z0-9-]+)"/',
            '/\sicon="([a-z0-9-]+)"/',
            '/[\'"]icon[\'"]\s*=>\s*[\'"]([a-z0-9-]+)[\'"]/',
            '/\sname="([a-z0-9-]+)"\s+class="[^"]*size-\d/',
        ] as $pattern) {
            preg_match_all($pattern, $source, $m);
            array_push($names, ...$m[1]);
        }

        // Ternary: :icon="$x ? 'lock' : 'check'" / :name="..."
        preg_match_all('/:(?:icon|name)="[^"]*\?\s*\'([a-z0-9-]+)\'\s*:\s*\'([a-z0-9-]+)\'/', $source, $t);
        array_push($names, ...$t[1], ...$t[2]);
    }

    foreach (MaterialType::cases() as $type) {
        $names[] = $type->icon();
    }

    foreach (QuestionType::cases() as $type) {
        $names[] = $type->icon();
    }

    return array_values(array_unique($names));
}

it('has an outline Heroicon for every icon used in the code', function () {
    $missing = collect(usedIconNames())
        ->reject(fn (string $name) => is_file(resource_path('icons/outline/'.config('icons.aliases.'.$name, $name).'.svg')))
        ->values()->all();

    expect(usedIconNames())->not->toBeEmpty()->and($missing)->toBe([]);
});

it('maps every legacy alias to an existing Heroicon', function () {
    foreach (config('icons.aliases') as $alias => $target) {
        expect(is_file(resource_path("icons/outline/{$target}.svg")))->toBeTrue("alias {$alias} -> {$target}");
    }
});

it('ships the solid 16px set used by badges', function () {
    foreach (['upload', 'clock', 'check-circle', 'arrow-path', 'minus-circle'] as $name) {
        expect(is_file(resource_path('icons/solid/'.config('icons.aliases.'.$name, $name).'.svg')))->toBeTrue($name);
    }
});

it('renders Heroicons as inline accessible SVG', function () {
    $this->blade('<x-icon name="trash-2" class="size-4" />')
        ->assertSee('<svg', false)->assertSee('aria-hidden="true"', false)->assertSee('stroke-width="1.5"', false)->assertSee('size-4', false);

    $this->blade('<x-icon name="check" solid />')->assertSee('fill="currentColor"', false);
});

it('fails loudly in development when an icon does not exist', function () {
    expect(fn () => $this->blade('<x-icon name="tidak-ada-ikon" />')->assertSee(''))->toThrow(Exception::class);
});

it('no longer depends on Lucide', function () {
    $package = json_decode(file_get_contents(base_path('package.json')), true);

    expect($package['devDependencies'])->toHaveKey('heroicons')->not->toHaveKey('lucide-static');
});
