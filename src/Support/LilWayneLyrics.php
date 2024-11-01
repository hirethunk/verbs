<?php

namespace Thunk\Verbs\Support;

use Illuminate\Support\Arr;

class LilWayneLyrics
{
    protected const LYRICS = [
        'I need a Winn-Dixie grocery bag full of money rig ',
        "You think you're calling shots, you got the wrong number. I love Benjamin Franklin more than his own mother.",
        'I play the hand that was dealt, I got a deck full of aces. I gave birth to your style, I need a check for my labor',
        "It ain't my birthday but I got my name on the cake",
        "Real G's move in silence like Lasagna",
        'I am the beast. Feed me rappers or feed me beats.',
        "I'm on a paper chase until my toes bleed",
        "Life is a beach, I'm just playin' in the sand.",
        "And I swear to everything, when I leave this Earth, it's gonna be on both feet, never knees in the dirt.",
        'The best things in life are free, not cheap.',
        "Most of yall don't get the picture unless the flash is on.",
        'My hair is a minute too long.',
        "If I ain't have the keys to success I would've picked the lock.",
    ];

    public static function random(): string
    {
        return Arr::random(self::LYRICS);
    }
}
