<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('preparation_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('preparation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_item_id')->constrained();
            $table->decimal('quantity_prepared', 12, 2)->default(0);
            $table->string('difference_reason')->nullable();
            $table->text('difference_notes')->nullable();
            $table->timestamps();

            $table->unique(['preparation_id', 'order_item_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('preparation_items');
    }
};
