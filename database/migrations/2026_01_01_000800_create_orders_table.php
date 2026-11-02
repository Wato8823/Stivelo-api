<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Demandes de commande. La référence publique (STV-2026-00A31) est
     * unique et suffit à retrouver un dossier sans exposer l'identifiant
     * technique. `total_estimate` est un montant recalculé côté serveur.
     */
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 32)->unique();
            $table->string('customer_name', 120);
            $table->string('customer_phone', 32);
            $table->string('customer_email', 160)->nullable();
            $table->string('customer_city', 120)->nullable();
            $table->text('customer_note')->nullable();
            $table->string('status', 30)->default('pending_confirmation');
            $table->unsignedInteger('total_estimate')->default(0);
            $table->char('currency', 3)->default('XAF');
            $table->string('channel', 20)->default('whatsapp');
            $table->boolean('consent_given')->default(false);
            $table->string('source_ip_hash', 64)->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
