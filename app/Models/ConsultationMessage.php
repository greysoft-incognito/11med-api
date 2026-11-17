<?php

namespace App\Models;

use App\Enums\MessageSender;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One message of a consultation's chat (app `zConsultationMessage`). Medical data: never logged.
 */
class ConsultationMessage extends Model
{
    protected $fillable = [
        'consultation_id',
        'sender',
        'body',
        'attachment_media_id',
        'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'sender' => MessageSender::class,
            'sent_at' => 'datetime',
        ];
    }

    public function consultation(): BelongsTo
    {
        return $this->belongsTo(Consultation::class);
    }

    public function attachment(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'attachment_media_id');
    }
}
