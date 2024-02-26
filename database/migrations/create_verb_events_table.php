<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create(config('verbs.tables.verb_events'), function (Blueprint $table) {
            $table->snowflakeId();

            $table->string('type')->index();
            $table->json('data');
            $table->json('metadata');

            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists(config('verbs.tables.verb_events'));
    }
};
