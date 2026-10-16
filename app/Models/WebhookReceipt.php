<?php

namespace App\Models;

use Carbon\Carbon;
use Database\Factories\WebhookReceiptFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Catatan webhook Academic yang sudah diterima; `event_id` unik menjamin event yang sama tidak diproses dua kali.
 *
 * @property int $id
 * @property string $event_id
 * @property string $type
 * @property Carbon $received_at
 * @property Carbon|null $processed_at
 */
#[Fillable(['event_id', 'type', 'received_at', 'processed_at'])]
class WebhookReceipt extends Model
{
    /** @use HasFactory<WebhookReceiptFactory> */
    use HasFactory;

    public $timestamps = false;

    protected function casts(): array
    {
        return ['received_at' => 'datetime', 'processed_at' => 'datetime'];
    }
}
