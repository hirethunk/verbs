<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('verb_snapshots', function (Blueprint $table) {
            $table->snowflakeId();

            $table->string('type')->index();
            $table->json('data');

            $table->snowflake('last_event_id')->nullable();

            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('verb_snapshots');
    }
};
