<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('modules', function (Blueprint $table) {
            $table->string('mod_drv', 20)->nullable()->after('features');        // zText, rawText, zCom, etc.
            $table->string('data_path', 500)->nullable()->after('mod_drv');       // DataPath from .conf
            $table->string('source_type_format', 20)->nullable()->after('data_path'); // OSIS, ThML, GBF, TEI, Plain
            $table->string('compress_type', 20)->nullable()->after('source_type_format'); // ZIP, LZSS, etc.
            $table->string('block_type', 20)->nullable()->after('compress_type'); // BOOK, CHAPTER, VERSE
            $table->string('versification', 20)->default('KJV')->after('block_type');
            $table->string('encoding', 20)->default('UTF-8')->after('versification');
            $table->string('direction', 10)->default('LtoR')->after('encoding');  // LtoR, RtoL, BiDi
            $table->string('category', 50)->nullable()->after('direction');
            $table->string('minimum_version', 20)->nullable()->after('category');
            $table->string('cipher_key', 100)->nullable()->after('minimum_version');
            $table->text('about')->nullable()->after('cipher_key');               // Full About text
            $table->string('copyright', 1000)->nullable()->after('about');
            $table->jsonb('global_option_filters')->default('[]')->after('copyright');
            $table->jsonb('conf_data')->nullable()->after('global_option_filters'); // Full raw .conf data
            $table->bigInteger('install_size')->nullable()->after('conf_data');
            $table->foreignUuid('module_source_id')->nullable()->after('install_size')
                ->constrained('module_sources')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('modules', function (Blueprint $table) {
            $table->dropForeign(['module_source_id']);
            $table->dropColumn([
                'mod_drv', 'data_path', 'source_type_format', 'compress_type',
                'block_type', 'versification', 'encoding', 'direction',
                'category', 'minimum_version', 'cipher_key', 'about',
                'copyright', 'global_option_filters', 'conf_data',
                'install_size', 'module_source_id',
            ]);
        });
    }
};
