<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('verb_snapshots', function (Blueprint $table) {
            // The 'id' column needs to be set up differently depending
            // on if you're using Snowflakes vs. ULIDs/etc.
            $this->createConfiguredIdType($table);

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

    protected function createConfiguredIdType(Blueprint $table)
    {
        $id_type = strtolower(config('verbs.id_type', 'snowflake'));

        return match ($id_type) {
            'snowflake' => $table->snowflakeId(),
            'ulid' => $table->ulid('id')->primary(),
            'uuid' => $table->uuid('id')->primary(),
            'default' => throw new UnexpectedValueException("Unknown Verbs ID type: '{$id_type}'"),
        };
    }
};
