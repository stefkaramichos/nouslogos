<?php


use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settlements', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('company_id');
            $table->date('month'); // 1η του μήνα

            $table->decimal('total_amount', 10, 2)->default(0);
            $table->decimal('cash_to_bank', 10, 2)->default(0);
            $table->decimal('partner1_total', 10, 2)->default(0);
            $table->decimal('partner2_total', 10, 2)->default(0);

            // ✅ created_by = PROFESSIONAL id (όχι users)
            $table->unsignedBigInteger('created_by')->nullable();

            $table->timestamps();

            $table->unique(['company_id', 'month']);

            $table->foreign('company_id')
                ->references('id')
                ->on('companies')
                ->onDelete('cascade');

            $table->foreign('created_by')
                ->references('id')
                ->on('professionals')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settlements');
    }
};