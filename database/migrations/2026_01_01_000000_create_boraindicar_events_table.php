<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('boraindicar_events', function (Blueprint $table) {
            $table->id();
            $table->string('type', 16)->index();
            $table->string('idempotency_key')->unique();
            $table->json('payload');
            $table->string('status', 16)->default('pending')->index();
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->text('last_error')->nullable();
            $table->json('response')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('boraindicar_events');
    }
};
