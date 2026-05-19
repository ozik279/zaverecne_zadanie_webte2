<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('animation_usages', function (Blueprint $table): void {
            $table->id();
            $table->string('simulation', 64);
            $table->string('client_token', 128);
            $table->string('ip_address', 45)->nullable();
            $table->string('city', 128)->nullable();
            $table->string('country', 128)->nullable();
            $table->timestamps();

            $table->index(['simulation', 'client_token']);
            $table->index(['created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('animation_usages');
    }
};