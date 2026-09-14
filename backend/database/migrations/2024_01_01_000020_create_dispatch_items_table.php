<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dispatch_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dispatch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('preparation_item_id')->constrained();
            $table->decimal('quantity_dispatched', 12, 2);
            $table->timestamps();

            $table->unique(['dispatch_id', 'preparation_item_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dispatch_items');
    }
};
