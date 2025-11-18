<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();

            $table->foreignId('appointment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();

            $table->decimal('amount', 10, 2);    // ποσό που πληρώθηκε (μπορεί να είναι μερικό)
            $table->boolean('is_full')->default(false); // αν είναι πλήρης εξόφληση
            $table->dateTime('paid_at')->nullable();
            $table->string('method')->nullable(); // μετρητά, κάρτα κτλ (προαιρετικό)
            $table->text('notes')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
