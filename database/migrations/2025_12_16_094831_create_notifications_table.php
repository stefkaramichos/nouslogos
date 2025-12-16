<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('professional_id')
                ->constrained('professionals')
                ->cascadeOnDelete();

            $table->text('note');                 // σημείωση ειδοποίησης
            $table->dateTime('notify_at');        // ημερομηνία/ώρα ειδοποίησης

            $table->boolean('is_read')->default(false);
            $table->dateTime('read_at')->nullable();

            $table->timestamps();

            $table->index(['professional_id', 'notify_at', 'is_read']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
