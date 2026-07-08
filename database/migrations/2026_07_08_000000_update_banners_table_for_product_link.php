<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('banners', function (Blueprint $table) {
            $table->string('subtitle')->nullable()->after('title');
            $table->foreignId('product_id')->after('subtitle')->constrained('products')->cascadeOnDelete();
            $table->integer('priority')->default(0)->after('product_id')->index();
        });

        Schema::table('banners', function (Blueprint $table) {
            $table->dropColumn(['link_url', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::table('banners', function (Blueprint $table) {
            $table->string('link_url')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
        });

        Schema::table('banners', function (Blueprint $table) {
            $table->dropForeign(['product_id']);
            $table->dropColumn(['subtitle', 'product_id', 'priority']);
        });
    }
};
