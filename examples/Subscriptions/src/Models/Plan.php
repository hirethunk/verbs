<?php

namespace Thunk\Verbs\Examples\Subscriptions\Models;

use Glhd\Bits\Database\HasSnowflakes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Thunk\Verbs\Examples\Subscriptions\Events\PlanReportGenerated;
use Thunk\Verbs\FromState;

class Plan extends Model
{
    use HasFactory;
    use FromState;
    use HasSnowflakes;

    public function generateReport()
    {
        return PlanReportGenerated::fire($this->id)
            ->state
            ->summary();
    }
}
