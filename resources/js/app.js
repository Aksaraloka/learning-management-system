// Livewire 4 sudah membundel Alpine beserta plugin persist, focus, collapse, intersect, anchor, mask.
import { Alpine, Livewire } from '../../vendor/livewire/livewire/dist/livewire.esm';

import { registerTheme } from './lib/theme';
import { registerToast } from './lib/toast';
import { registerLazyComponents } from './components';

registerTheme(Alpine);
registerToast(Alpine);
registerLazyComponents(Alpine);

window.Alpine = Alpine;

Livewire.start();
