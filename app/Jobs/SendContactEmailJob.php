<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use App\Mail\ContactMessageMail;

class SendContactEmailJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    protected $email;
    protected $contactData;

    /**
     * Create a new job instance.
     */
    public function __construct($email, $contactData)
    {
        $this->email = $email;
        $this->contactData = $contactData;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        if ($this->email) {
            Mail::to($this->email)->send(new ContactMessageMail($this->contactData));
        }
    }
}
