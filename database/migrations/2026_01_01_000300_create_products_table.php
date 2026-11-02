<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Modèles de vêtements. Le prix est un entier en FCFA (aucun centime).
     * Archivage logique (deleted_at) dès qu'un modèle a été commandé.
     */
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained()->restrictOnDelete();
            $table->string('name', 160);
            $table->string('slug', 180)->unique();
            $table->string('short_description', 300)->nullable();
            $table->text('description')->nullable();
            $table->string('collection_badge', 60)->nullable();
            $table->unsignedInteger('base_price')->default(0);
            $table->string('status', 20)->default('draft');
            $table->boolean('is_featured')->default(false);
            $table->boolean('custom_size_enabled')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'published_at']);
            $table->index(['category_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
