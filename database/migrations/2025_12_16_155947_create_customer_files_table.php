<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_files', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('customer_id');
            $table->unsignedBigInteger('uploaded_by')->nullable(); // professional id

            $table->string('original_name');  // π.χ. "invoice.pdf"
            $table->string('stored_name');    // π.χ. "1734291234_invoice.pdf" (ή hash)
            $table->string('path');           // storage path
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size')->default(0);

            $table->text('notes')->nullable();

            $table->timestamps();

            $table->index(['customer_id', 'created_at']);

            $table->foreign('customer_id')
                ->references('id')
                ->on('customers')
                ->onDelete('cascade');

            // ⚠️ Επειδή κάνεις login με professionals
            $table->foreign('uploaded_by')
                ->references('id')
                ->on('professionals')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_files');
    }
};
