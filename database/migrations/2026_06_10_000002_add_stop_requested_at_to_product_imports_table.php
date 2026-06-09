<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_imports', function (Blueprint $table) {
            $table->timestamp('stop_requested_at')->nullable()->after('batch_id');
        });
    }

    public function down(): void
    {
        Schema::table('product_imports', function (Blueprint $table) {
            $table->dropColumn('stop_requested_at');
        });
    }
};
