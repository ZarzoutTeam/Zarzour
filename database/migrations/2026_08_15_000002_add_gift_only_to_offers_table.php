<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('offers', function (Blueprint $table) {
            $table->enum('type', ['discount_only', 'discount_with_gift', 'gift_only'])->change();
            $table->enum('discount_type', ['percentage', 'fixed'])->nullable()->change();
            $table->decimal('discount_value', 10, 2)->nullable()->change();
        });
    }

    public function down(): void
    {
        DB::table('offers')
            ->where('type', 'gift_only')
            ->update([
                'type' => 'discount_with_gift',
                'discount_type' => 'percentage',
                'discount_value' => 0,
            ]);

        DB::table('offers')
            ->whereNull('discount_type')
            ->update(['discount_type' => 'percentage']);

        DB::table('offers')
            ->whereNull('discount_value')
            ->update(['discount_value' => 0]);

        Schema::table('offers', function (Blueprint $table) {
            $table->enum('type', ['discount_only', 'discount_with_gift'])->change();
            $table->enum('discount_type', ['percentage', 'fixed'])->nullable(false)->change();
            $table->decimal('discount_value', 10, 2)->nullable(false)->change();
        });
    }
};
