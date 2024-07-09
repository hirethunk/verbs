<?php

namespace Thunk\Verbs\Examples\Bank\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Thunk\Verbs\Examples\Bank\Models\User;

class DepositAvailable extends Mailable
{
    use SerializesModels;

    public function __construct(
        public int $user_id
    ) {}

    public function build()
    {
        return $this->to(User::find($this->user_id))
            ->subject('Your deposit is available')
            ->html('Yay now you have more money!');
    }
}
