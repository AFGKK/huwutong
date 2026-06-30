<?php

namespace App\Events;

use App\Models\OaSubmission;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OaSubmissionCreated
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public OaSubmission $submission;

    public function __construct(OaSubmission $submission)
    {
        $this->submission = $submission;
    }
}
