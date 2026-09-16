<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sync_operations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('device_id')->nullable();
            $table->uuid('client_uuid');
            $table->string('entity_type');
            $table->json('payload');
            $table->string('status')->default('PENDIENTE');
            $table->timestamp('processed_at')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            // Bitácora servidor-side de qué client_uuid ya se procesaron,
            // como refuerzo adicional a la unicidad de deliveries.client_uuid.
            $table->unique(['entity_type', 'client_uuid']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sync_operations');
    }
};
