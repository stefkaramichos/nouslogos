<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            // ΔΟΥ
            $table->string('tax_office', 100)->nullable()->after('email');

            // ΑΦΜ
            $table->string('vat_number', 20)->nullable()->after('tax_office');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn(['tax_office', 'vat_number']);
        });
    }
};
