<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Photos des modèles. Le fichier est stocké sur un disque objet (S3 ou
     * équivalent) ; la base ne conserve que la clé et les métadonnées.
     */
    public function up(): void
    {
        Schema::create('product_media', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('storage_key'); // ex. products/42/photo-1.webp
            $table->string('alt', 180);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->timestamps();

            $table->index(['product_id', 'sort_order']);
            $table->unique(['product_id', 'storage_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_media');
    }
};
