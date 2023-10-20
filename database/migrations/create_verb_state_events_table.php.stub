<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up()
    {
        Schema::create('verb_state_events', function (Blueprint $table) {
            $table->bigInteger('id')->unsigned()->primary();

            $table->bigInteger('event_id')->unsigned()->index();

            $table->bigInteger('state_id')->unsigned()->index();
            $table->string('state_type')->index();

            $table->timestamps();
        });
    }
};
