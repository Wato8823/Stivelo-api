<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Mensurations corporelles saisies pour un « sur mesure ».
     * Données personnelles sensibles : accès réservé aux rôles autorisés,
     * valeurs stockées en centimètres avec une décimale.
     */
    public function up(): void
    {
        Schema::create('measurements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_item_id')->constrained()->cascadeOnDelete();
            $table->string('key', 60);
            $table->string('label', 120);
            $table->decimal('value', 5, 1);
            $table->string('unit', 8)->default('cm');
            $table->timestamps();

            $table->unique(['order_item_id', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('measurements');
    }
};
