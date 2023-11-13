<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('verb_state_events', function (Blueprint $table) {
            $table->snowflakeId();

            $table->snowflake('event_id')->index();

            $table->snowflake('state_id')->index();
            $table->string('state_type')->index();

            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('verb_state_events');
    }
};
