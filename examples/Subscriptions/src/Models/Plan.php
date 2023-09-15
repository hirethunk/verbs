<?php

namespace Thunk\Verbs\Examples\Subscriptions\Models;

use Thunk\Verbs\FromState;
use Glhd\Bits\Database\HasSnowflakes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Thunk\Verbs\Examples\Subscriptions\States\PlanReportState;
use Thunk\Verbs\Examples\Subscriptions\States\GlobalReportState;
use Thunk\Verbs\Examples\Subscriptions\Events\PlanReportGenerated;
use Thunk\Verbs\Examples\Subscriptions\Events\GlobalReportGenerated;

class Plan extends Model
{
    use FromState;
    use HasFactory;
    use HasSnowflakes;

    public function generateReport(): PlanReportState
    {
        return PlanReportGenerated::fire($this->id)
            ->state;
    }

    public static function generateGlobalReport(): GlobalReportState
    {
        return GlobalReportGenerated::fire()
            ->state;
    }
}
