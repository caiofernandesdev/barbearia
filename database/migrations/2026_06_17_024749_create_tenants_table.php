<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();          // usado na URL: /{slug}/admin
            $table->string('nome');
            $table->string('tipo')->default('barbearia'); // barbearia, salao, estetica, etc.
            $table->foreignId('plano_id')->nullable()->constrained('planos')->nullOnDelete();
            $table->json('whatsapp_config')->nullable(); // {base_url, api_key, instance}
            $table->boolean('ativo')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tenants');
    }
};
