<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('simulation_runs', function (Blueprint $table): void {
            $table->id();
            $table->string('simulation', 64);
            $table->string('client_token', 128)->nullable();
            $table->json('request_payload');
            $table->json('result_payload')->nullable();
            $table->boolean('successful')->default(false);
            $table->unsignedInteger('duration_ms')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('city', 128)->nullable();
            $table->string('country', 128)->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index(['simulation', 'client_token']);
            $table->index(['simulation', 'successful']);
            $table->index(['created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('simulation_runs');
    }
};