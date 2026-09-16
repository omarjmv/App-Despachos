<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('route_stop_id')->constrained();
            $table->foreignId('dispatch_id')->constrained();
            // Generado en el dispositivo: garantiza idempotencia cuando el
            // motorista reintenta sincronizar (regla 21 y Documento 4 offline).
            $table->uuid('client_uuid');
            $table->foreignId('delivered_by')->constrained('users');
            $table->string('result');
            $table->text('rejection_reason')->nullable();
            $table->text('notes')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->timestamp('delivered_at');
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->unique('client_uuid');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deliveries');
    }
};
