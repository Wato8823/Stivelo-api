<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Guide des mesures par modèle : quelles mensurations sont demandées,
     * leur libellé, leur caractère obligatoire et leurs bornes de validité.
     * Ex. costume : poitrine, taille, épaules, manche, longueur jambe.
     */
    public function up(): void
    {
        Schema::create('product_measurement_fields', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('key', 60); // chest_cm, waist_cm, shoulder_cm…
            $table->string('label', 120);
            $table->string('hint', 240)->nullable();
            $table->boolean('is_required')->default(true);
            $table->unsignedSmallInteger('min_cm')->default(20);
            $table->unsignedSmallInteger('max_cm')->default(250);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['product_id', 'key']);
            $table->index(['product_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_measurement_fields');
    }
};
