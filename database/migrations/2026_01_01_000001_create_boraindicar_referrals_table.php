<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('boraindicar_referrals', function (Blueprint $table) {
            $table->id();
            $table->string('customer_id')->unique();
            $table->string('code', 64);
            $table->string('click_id')->nullable();
            $table->json('utm')->nullable();
            $table->boolean('attributed')->default(false);
            $table->string('hub_referral_id')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('boraindicar_referrals');
    }
};
