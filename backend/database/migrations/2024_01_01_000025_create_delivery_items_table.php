<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('delivery_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('delivery_id')->constrained()->cascadeOnDelete();
            $table->foreignId('dispatch_item_id')->constrained();
            $table->decimal('quantity_delivered', 12, 2)->default(0);
            $table->decimal('quantity_rejected', 12, 2)->default(0);
            $table->string('rejection_reason')->nullable();
            $table->timestamps();

            $table->unique(['delivery_id', 'dispatch_item_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_items');
    }
};
