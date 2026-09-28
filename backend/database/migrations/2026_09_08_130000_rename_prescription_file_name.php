<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('prescriptions', 'file_name') && !Schema::hasColumn('prescriptions', 'original_file_name')) {
            Schema::table('prescriptions', function (Blueprint $table) {
                $table->renameColumn('file_name', 'original_file_name');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('prescriptions', 'original_file_name') && !Schema::hasColumn('prescriptions', 'file_name')) {
            Schema::table('prescriptions', function (Blueprint $table) {
                $table->renameColumn('original_file_name', 'file_name');
            });
        }
    }
};