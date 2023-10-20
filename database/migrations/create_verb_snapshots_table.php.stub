<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('verb_snapshots', function (Blueprint $table) {
            $table->bigInteger('id')->unsigned()->primary();

            $table->string('type')->index();
            $table->json('data');

            $table->bigInteger('last_event_id')->unsigned()->nullable();

            $table->timestamps();
        });
    }
};
