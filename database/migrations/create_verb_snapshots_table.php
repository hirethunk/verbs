<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('verb_snapshots', function (Blueprint $table) {
            match (config('verbs.id_type', 'snowflake')) {
                'snowflake' => $table->snowflakeId(),
                'ulid' => $table->ulid('id')->primary(),
                'uuid' => $table->uuid('id')->primary(),
            };

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
