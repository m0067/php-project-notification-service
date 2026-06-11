<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('message_id')->constrained()->cascadeOnDelete();
            $table->string('subscriber_id');
            $table->string('status')->default('queued')->comment('queued | sent | delivered | dropped');
            $table->string('provider_message_id')->nullable();
            $table->string('failure_reason')->nullable();
            $table->timestamps();

            $table->index(['subscriber_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
