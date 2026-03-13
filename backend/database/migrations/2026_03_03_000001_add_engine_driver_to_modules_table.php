<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('modules', function (Blueprint $table) {
            $table->string('engine', 10)->default('sword')->after('features');  // 'sword' or 'bintex'
            $table->string('driver', 10)->nullable()->after('engine');           // bintex: 'yes1' or 'yes2'; sword: null
        });
    }

    public function down(): void
    {
        Schema::table('modules', function (Blueprint $table) {
            $table->dropColumn(['engine', 'driver']);
        });
    }
};
