<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expenses', function (Blueprint $table) {
            $table->id();

            // Συνδέεται με εταιρεία
            $table->foreignId('company_id')
                ->constrained()
                ->cascadeOnDelete();

            // Ποσό εξόδου
            $table->decimal('amount', 10, 2);

            // Περιγραφή εξόδου
            $table->text('description')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};
