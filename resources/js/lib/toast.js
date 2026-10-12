/**
 * Toast global. Dipanggil dari JS: `window.dispatchEvent(new CustomEvent('toast', {detail: {message, type}}))`
 * atau dari Livewire: `$this->dispatch('toast', message: '...', type: 'success')`.
 */
export function registerToast(Alpine) {
    Alpine.store('toasts', {
        items: [],
        seq: 0,

        push({ message, type = 'info', timeout = 5000 }) {
            const id = ++this.seq;

            this.items.push({ id, message, type });

            if (timeout > 0) {
                setTimeout(() => this.remove(id), timeout);
            }
        },

        remove(id) {
            this.items = this.items.filter((item) => item.id !== id);
        },
    });

    window.addEventListener('toast', (event) => Alpine.store('toasts').push(event.detail ?? {}));
}
