<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reports', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('plan_id')->nullable()->index();
            $table->integer('subscribes_since_last_report')->default(0);
            $table->integer('unsubscribes_since_last_report')->default(0);
            $table->integer('total_subscriptions')->default(0);
            $table->text('summary');
            $table->timestamps();
        });
    }
};
