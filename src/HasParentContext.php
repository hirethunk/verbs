<?php

namespace Thunk\Verbs;

use Illuminate\Support\Facades\DB;
use Thunk\Verbs\Events\AttachedToParent;
use Thunk\Verbs\Events\ChildAttached;

/** @mixin Context */
trait HasParentContext
{
    protected array $parent_context_ids = [];

    public function attachToParent(Context $parent): static
    {
        DB::transaction(function () use ($parent) {
            AttachedToParent::withContext($this)->fire($parent->id);
            ChildAttached::withContext($parent)->fire($this->id);
        });

        return $this;
    }

    public function applyAttachedToParent(AttachedToParent $parent)
    {
        $this->parent_context_ids[] = $parent->parent_id;
    }
}
