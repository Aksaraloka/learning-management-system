<?php

/**
 * Membaca token warna dari resources/css/app.css dan memastikan kontras teks memenuhi WCAG 2.1 AA (>= 4.5:1).
 *
 * @return array<string, string> token => #rrggbb
 */
function themeTokens(string $selector): array
{
    $css = file_get_contents(resource_path('css/app.css'));

    preg_match('/'.preg_quote($selector, '/').'\s*\{(.*?)\n\}/s', $css, $block);

    preg_match_all('/--color-([a-z-]+):\s*(#[0-9a-fA-F]{6});/', $block[1] ?? '', $matches, PREG_SET_ORDER);

    return collect($matches)->mapWithKeys(fn ($m) => [$m[1] => strtolower($m[2])])->all();
}

function luminance(string $hex): float
{
    return collect(str_split(ltrim($hex, '#'), 2))
        ->map(fn ($c) => hexdec($c) / 255)
        ->map(fn ($v) => $v <= 0.03928 ? $v / 12.92 : (($v + 0.055) / 1.055) ** 2.4)
        ->pipe(fn ($c) => 0.2126 * $c[0] + 0.7152 * $c[1] + 0.0722 * $c[2]);
}

function contrast(string $a, string $b): float
{
    [$hi, $lo] = [max(luminance($a), luminance($b)), min(luminance($a), luminance($b))];

    return ($hi + 0.05) / ($lo + 0.05);
}

it('uses pure black as the dark theme base', function () {
    expect(themeTokens('.dark')['bg'])->toBe('#000000');
});

it('keeps cards distinguishable from the dark background', function () {
    $dark = themeTokens('.dark');

    expect($dark['surface'])->not->toBe($dark['bg'])
        ->and($dark['surface-muted'])->not->toBe($dark['surface'])
        ->and(contrast($dark['border'], $dark['bg']))->toBeGreaterThan(1.3);
});

it('uses a red accent in both themes', function (string $selector) {
    $primary = ($selector === ':root' ? themeTokens('@theme static') : themeTokens('.dark'))['primary'];

    [$r, $g, $b] = array_map('hexdec', str_split(ltrim($primary, '#'), 2));

    expect($r)->toBeGreaterThan($g * 2)->and($r)->toBeGreaterThan($b * 2);
})->with([':root', '.dark']);

it('meets WCAG AA text contrast in both themes', function (string $selector) {
    $t = $selector === ':root' ? themeTokens('@theme static') : themeTokens('.dark');

    foreach (['bg', 'surface', 'surface-muted'] as $background) {
        foreach (['text', 'text-muted', 'primary', 'success', 'warning', 'danger', 'info'] as $foreground) {
            expect(contrast($t[$foreground], $t[$background]))
                ->toBeGreaterThanOrEqual(4.5, "{$foreground} on {$background} ({$selector})");
        }
    }

    expect(contrast($t['primary-fg'], $t['primary']))->toBeGreaterThanOrEqual(4.5, "primary-fg on primary ({$selector})");
})->with([':root', '.dark']);
