// Livewire 4 sudah membundel Alpine beserta plugin persist, focus, collapse, intersect, anchor, mask.
import { Alpine, Livewire } from '../../vendor/livewire/livewire/dist/livewire.esm';

import { registerTheme } from './lib/theme';
import { registerMotion } from './lib/motion';
import { registerToast } from './lib/toast';
import { registerConfirm } from './lib/alert';
import { registerLazyComponents } from './components';

registerTheme(Alpine);
registerToast();
registerConfirm();
registerMotion(Alpine);
registerLazyComponents(Alpine);

window.Alpine = Alpine;

Livewire.start();

// PWA: service worker hanya di production (aset hasil build); di dev (Vite) tidak didaftarkan agar tidak menyimpan aset lama.
if ('serviceWorker' in navigator && import.meta.env.PROD) {
    window.addEventListener('load', () => navigator.serviceWorker.register('/sw.js', { scope: '/' }).catch(() => {}));
}
