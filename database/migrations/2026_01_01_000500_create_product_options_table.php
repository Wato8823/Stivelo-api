<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Options propres à chaque modèle : couleurs, tissus et tailles standard.
     * `value` est la valeur technique envoyée par le client Angular
     * ("noir", "lin", "M", "Sur mesure"…), `label` le libellé affiché.
     */
    public function up(): void
    {
        Schema::create('product_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('type', 20); // color | fabric | size
            $table->string('label', 120);
            $table->string('value', 120);
            $table->integer('price_delta')->default(0); // supplément en FCFA
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['product_id', 'type', 'value']);
            $table->index(['product_id', 'type', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_options');
    }
};
