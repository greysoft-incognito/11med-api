<?php

namespace App\Jobs;

use App\Mail\ReportGenerated;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Stringable;

class DispatchExportsNotifications implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     *
     * @param  Collection<int, Stringable>  $data_emails
     */
    public function __construct(
        protected Model $dataset,
        protected Collection $data_emails,
        protected string $title,
        protected int $batch = 1,
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        foreach ($this->data_emails->unique()->filter(fn($e) => $e->isNotEmpty()) as $email) {
            RateLimiter::attempt(
                'send-report:' . $email . $this->batch,
                5,
                fn() => Mail::to($email->toString())->send(
                    new ReportGenerated($this->dataset, $this->batch, $this->title)
                )
            );
        }
    }
}
