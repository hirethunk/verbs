<?php

namespace Thunk\Verbs\Tests\Fixtures\Events;

use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\Console\Input\Input;
use Thunk\Verbs\Event;

class SelfFiringEventFired extends Event
{
    public static function asRoute(Request $request, User $user): Event
    {
        $context = $user->aggregate_root;
        
        return (new static($user->name))->withContext($context);
    }
    
    public static function asCommand(Input $input): Event
    {
        
    }
    
    public function __construct(
        public string $name
    ) {
    }
    
    public function authorize($context)
    {
        return Auth::user()->can('fire-neat-events');
    }
    
    public function rules($context)
    {
        return ['count' => 'min:0|max:3'];
    }
    
    public function onFire()
    {
        User::firstWhere('context_id', $this->context->id())
            ->update('foo');
    }
}
