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

            // The 'state_id' column needs to be set up differently depending
            // on if you're using Snowflakes vs. ULIDs/etc.
            $this->createConfiguredStateIdType($table);

            $table->string('state_type')->index();

            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('verb_state_events');
    }

    protected function createConfiguredStateIdType(Blueprint $table)
    {
        $id_type = strtolower(config('verbs.id_type', 'snowflake'));

        return match ($id_type) {
            'snowflake' => $table->snowflake('state_id')->index(),
            'ulid' => $table->ulid('state_id')->index(),
            'uuid' => $table->uuid('state_id')->index(),
            'default' => throw new UnexpectedValueException("Unknown Verbs ID type: '{$id_type}'"),
        };
    }
};
