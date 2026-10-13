import { showToast } from './alert';

/**
 * Toast global (SweetAlert2). Dipanggil dari JS: `window.dispatchEvent(new CustomEvent('toast', {detail: {message, type}}))`
 * atau dari Livewire: `$this->dispatch('toast', message: '...', type: 'success')`.
 */
export function registerToast() {
    window.addEventListener('toast', (event) => showToast(event.detail ?? {}));
}
