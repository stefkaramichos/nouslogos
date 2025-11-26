<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('therapist_appointments', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('professional_id');
            $table->unsignedBigInteger('customer_id');

            $table->dateTime('start_time');    // ημερομηνία + ώρα που κλείνει ο θεραπευτής
            $table->text('notes')->nullable();

            $table->timestamps();

            $table->foreign('professional_id')
                  ->references('id')->on('professionals')
                  ->onDelete('cascade');

            $table->foreign('customer_id')
                  ->references('id')->on('customers')
                  ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('therapist_appointments');
    }
};
