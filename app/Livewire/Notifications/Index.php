<?php

namespace App\Livewire\Notifications;

use Illuminate\Contracts\View\View;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    #[Url(as: 'tampil')]
    public string $filter = 'all';

    public function updatedFilter(): void
    {
        $this->resetPage();
    }

    /**
     * Menandai dibaca lalu membuka tujuan notifikasi. Hanya milik pengguna sendiri (id lain = 404).
     */
    public function open(string $id): void
    {
        /** @var DatabaseNotification $notification */
        $notification = Auth::user()->notifications()->findOrFail($id);
        $notification->markAsRead();

        $this->redirect($notification->data['url'] ?? route('home'), navigate: true);
    }

    public function markRead(string $id): void
    {
        Auth::user()->notifications()->findOrFail($id)->markAsRead();
    }

    public function markAllRead(): void
    {
        Auth::user()->unreadNotifications()->update(['read_at' => now()]);
    }

    public function render(): View
    {
        $user = Auth::user();

        $query = $this->filter === 'unread' ? $user->unreadNotifications() : $user->notifications();

        return view('livewire.notifications.index', [
            'notifications' => $query->simplePaginate(15),
            'unread' => $user->unreadNotifications()->count(),
        ])->title(__('ui.nav.notifications'));
    }
}
