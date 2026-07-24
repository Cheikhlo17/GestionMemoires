<?php

namespace App\Events;

use App\Models\Thesis;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ThesisSubmitted
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Thesis $thesis)
    {
    }
}