<?php

namespace App\Events;

use App\Models\Thesis;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ThesisStatusChanged
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Thesis $thesis,
        public ?string $fromStatus,
        public string $toStatus,
        public ?string $remarks = null
    ) {
    }
}