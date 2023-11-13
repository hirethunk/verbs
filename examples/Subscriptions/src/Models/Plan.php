<?php

namespace Thunk\Verbs\Examples\Subscriptions\Models;

use Glhd\Bits\Database\HasSnowflakes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Thunk\Verbs\Examples\Subscriptions\Events\GlobalReportGenerated;
use Thunk\Verbs\Examples\Subscriptions\Events\PlanReportGenerated;
use Thunk\Verbs\Examples\Subscriptions\States\GlobalReportState;
use Thunk\Verbs\Examples\Subscriptions\States\PlanReportState;

class Plan extends Model
{
	use HasFactory;
	use HasSnowflakes;
	
	public function generateReport(): PlanReportState
	{
		return PlanReportGenerated::fire(plan_id: $this->id)
			->states()
			->firstOfType(PlanReportState::class);
	}
	
	public static function generateGlobalReport(): GlobalReportState
	{
		$e = GlobalReportGenerated::fire();
		
		return $e->states()->firstOfType(GlobalReportState::class);
	}
}
