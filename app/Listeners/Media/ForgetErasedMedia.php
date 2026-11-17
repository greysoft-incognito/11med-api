<?php

namespace App\Listeners\Media;

use App\Events\Identity\AccountErasing;
use App\Services\Media\MediaLibrary;

/**
 * An erased account's uploads go with it: photographs, attachments and their files.
 */
class ForgetErasedMedia
{
    public function __construct(
        private readonly MediaLibrary $library,
    ) {}

    public function handle(AccountErasing $event): void
    {
        $this->library->forget($event->user);
    }
}
