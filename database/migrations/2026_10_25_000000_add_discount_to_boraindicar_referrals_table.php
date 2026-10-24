<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('boraindicar_referrals', function (Blueprint $table) {
            // Null: the hub has not answered yet (or the lead predates this column); 0: no discount.
            $table->unsignedInteger('discount_bp')->nullable()->after('hub_referral_id');
            $table->unsignedSmallInteger('discount_months')->nullable()->after('discount_bp');
        });
    }

    public function down(): void
    {
        Schema::table('boraindicar_referrals', function (Blueprint $table) {
            $table->dropColumn(['discount_bp', 'discount_months']);
        });
    }
};
