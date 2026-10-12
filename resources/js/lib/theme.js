const MEDIA = '(prefers-color-scheme: dark)';

export function applyTheme(choice) {
    const dark = choice === 'dark' || (choice !== 'light' && window.matchMedia(MEDIA).matches);

    document.documentElement.classList.toggle('dark', dark);
    document.documentElement.style.colorScheme = dark ? 'dark' : 'light';

    return dark;
}

// Kunci & format sama dengan Alpine persist (`$persist(...).as('theme')`) agar
// skrip inline di <head> (anti-kedip) dan komponen lain membaca nilai yang sama.
const KEY = '_x_theme';

function read() {
    try {
        const value = JSON.parse(localStorage.getItem(KEY) ?? '"system"');

        return ['light', 'dark', 'system'].includes(value) ? value : 'system';
    } catch {
        return 'system';
    }
}

function write(choice) {
    try {
        localStorage.setItem(KEY, JSON.stringify(choice));
    } catch {
        // Penyimpanan diblokir (mode privat); tema tetap berlaku untuk sesi ini.
    }
}

/**
 * Store `theme`: pilihan 'light' | 'dark' | 'system'. Event `theme-changed`
 * dikirim untuk library yang perlu render ulang (Chart.js) dan untuk sinkron
 * ke `users.theme` di server.
 */
export function registerTheme(Alpine) {
    Alpine.store('theme', {
        choice: read(),
        dark: document.documentElement.classList.contains('dark'),

        init() {
            window.matchMedia(MEDIA).addEventListener('change', () => this.apply(false));
            this.apply(false);
        },

        set(choice) {
            this.choice = choice;
            write(choice);
            this.apply(true);
        },

        apply(notifyServer) {
            this.dark = applyTheme(this.choice);

            window.dispatchEvent(
                new CustomEvent('theme-changed', { detail: { choice: this.choice, dark: this.dark, persist: notifyServer } }),
            );
        },
    });
}
