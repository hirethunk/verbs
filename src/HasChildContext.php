<?php

namespace Thunk\Verbs;

use Illuminate\Support\Facades\DB;
use Thunk\Verbs\Events\AttachedToParent;
use Thunk\Verbs\Events\ChildAttached;

/** @mixin Context */
trait HasChildContext
{
    protected array $child_context_ids = [];

    public function attachChild(Context $child): static
    {
        DB::transaction(function () use ($child) {
            ChildAttached::withContext($this)->fire($child->id);
            AttachedToParent::withContext($child)->fire($this->id);
        });

        return $this;
    }

    public function applyChildAttached(ChildAttached $child)
    {
        $this->child_context_ids[] = $child->child_id;
    }
}
