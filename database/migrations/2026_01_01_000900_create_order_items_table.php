<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Lignes d'une demande. `product_snapshot` fige le nom, le prix, la
     * couleur, le tissu et la taille au moment de la commande : une
     * modification ultérieure du catalogue ne change pas l'historique.
     */
    public function up(): void
    {
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->json('product_snapshot');
            $table->unsignedInteger('unit_price')->default(0);
            $table->unsignedSmallInteger('quantity')->default(1);
            $table->unsignedInteger('line_total')->default(0);
            $table->boolean('custom_size')->default(false);
            $table->timestamps();

            $table->index('order_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};
