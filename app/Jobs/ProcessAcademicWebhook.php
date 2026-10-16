<?php

namespace App\Jobs;

use App\Actions\Academic\HandleAcademicEvent;
use App\Models\WebhookReceipt;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessAcademicWebhook implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [30, 120];

    /**
     * @param  array<string, mixed>  $data
     */
    public function __construct(public string $eventId, public string $type, public array $data) {}

    public function handle(HandleAcademicEvent $handler): void
    {
        $handler->handle($this->type, $this->data);

        WebhookReceipt::query()->where('event_id', $this->eventId)->update(['processed_at' => now()]);
    }
}
