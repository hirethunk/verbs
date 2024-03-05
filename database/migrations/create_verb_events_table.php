<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
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
