<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workflow_steps', function (Blueprint $table) {
            $table->enum('action_type', ['APPROVE', 'SIGN', 'REVIEW', 'CHECK', 'NOTE_ONLY'])
                ->default('APPROVE')
                ->after('step_name');
        });
    }

    public function down(): void
    {
        Schema::table('workflow_steps', function (Blueprint $table) {
            $table->dropColumn('action_type');
        });
    }
};
