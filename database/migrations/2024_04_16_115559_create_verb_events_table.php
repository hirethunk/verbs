<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        // If they've already migrated under the previous migration name, just skip
        if (Schema::connection($this->connectionName())->hasTable($this->tableName())) {
            return;
        }

        Schema::connection($this->connectionName())->create($this->tableName(), function (Blueprint $table) {
            $table->snowflakeId();

            $table->string('type')->index();
            $table->json('data');
            $table->json('metadata');

            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::connection($this->connectionName())->dropIfExists($this->tableName());
    }

    protected function connectionName(): ?string
    {
        return config('verbs.connections.events');
    }

    protected function tableName(): string
    {
        return config('verbs.tables.events', 'verb_events');
    }
};
