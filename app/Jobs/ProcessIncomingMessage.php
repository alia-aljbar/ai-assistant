<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessIncomingMessage implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array<string, mixed>  $message  The Telegram "message" object.
     */
    public function __construct(public array $message) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        //
    }
}
