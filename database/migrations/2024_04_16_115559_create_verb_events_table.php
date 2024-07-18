<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        // If they've already migrated under the previous migration name, just skip
        if (Schema::hasTable($this->tableName())) {
            throw new RuntimeException('The create_verbs_* migrations have been renamed. See <https://verbs.thunk.dev/docs/reference/upgrading>');
        }

        Schema::create($this->tableName(), function (Blueprint $table) {
            $table->snowflakeId();

            $table->string('type')->index();
            $table->json('data');
            $table->json('metadata');

            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists($this->tableName());
    }

    protected function tableName(): string
    {
        return config('verbs.tables.events', 'verb_events');
    }
};