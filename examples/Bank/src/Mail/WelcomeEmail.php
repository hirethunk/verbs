<?php

namespace Thunk\Verbs\Examples\Bank\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Thunk\Verbs\Examples\Bank\Models\User;

class WelcomeEmail extends Mailable
{
    use SerializesModels;

    public function __construct(
        public int $user_id
    ) {}

    public function build()
    {
        return $this->to(User::find($this->user_id))
            ->subject('You opened a new account!')
            ->html('GREAT JOB KID!');
    }
}
