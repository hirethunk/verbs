<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Thunk\Verbs\Examples\Bank\Models\User;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounts', function (Blueprint $table) {
            $table->id();

            $table->foreignIdFor(User::class);
            $table->integer('balance_in_cents');

            $table->timestamps();
        });
    }
};
