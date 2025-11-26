<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            // 1. Drop το παλιό foreign key προς users
            $table->dropForeign('appointments_created_by_foreign');

            // 2. Ξαναφτιάχνουμε το foreign key προς professionals
            $table->foreign('created_by')
                ->references('id')
                ->on('professionals')
                ->nullOnDelete(); // ON DELETE SET NULL
        });
    }

    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            // Αν θέλουμε να το γυρίσουμε πίσω σε users
            $table->dropForeign('appointments_created_by_foreign');

            $table->foreign('created_by')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
        });
    }
};
