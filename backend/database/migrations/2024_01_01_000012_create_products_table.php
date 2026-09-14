<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->nullable()->constrained('product_categories')->nullOnDelete();
            $table->string('sku');
            $table->string('barcode')->nullable();
            $table->string('name');
            $table->string('description')->nullable();
            $table->string('unit')->default('UNIDAD');
            $table->boolean('is_active')->default(true);
            // Campos preparados para futuras extensiones (sección 6 del brief) - no usados en Fase 1.
            $table->decimal('price', 12, 2)->nullable();
            $table->decimal('cost', 12, 2)->nullable();
            $table->decimal('weight', 10, 3)->nullable();
            $table->decimal('volume', 10, 3)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['company_id', 'sku']);
            $table->index(['company_id', 'barcode']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
