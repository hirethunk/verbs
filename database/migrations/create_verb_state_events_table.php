<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Thunk\Verbs\Facades\Id;

return new class extends Migration
{
    public function up()
    {
        Schema::create($this->tableName(), function (Blueprint $table) {
            $table->snowflakeId();

            $table->snowflake('event_id')->index();

            // The 'state_id' column needs to be set up differently depending
            // on if you're using Snowflakes vs. ULIDs/etc.
            Id::createColumnDefinition($table, 'state_id')->index();

            $table->string('state_type')->index();

            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists($this->tableName());
    }

    protected function tableName(): string
    {
        return config('verbs.tables.state_events', 'verb_state_events');
    }
};
