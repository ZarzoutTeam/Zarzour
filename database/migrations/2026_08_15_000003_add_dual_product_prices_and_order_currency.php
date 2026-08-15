<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->renameColumn('price', 'price_syp');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->decimal('price_syp', 14, 2)->change();
            $table->decimal('price_usd', 10, 2)->nullable()->after('price_syp');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->string('currency', 3)->default('SYP')->after('payment_method');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('currency');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('price_usd');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->decimal('price_syp', 10, 2)->change();
        });

        Schema::table('products', function (Blueprint $table) {
            $table->renameColumn('price_syp', 'price');
        });
    }
};
