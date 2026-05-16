<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cas_request_logs', function (Blueprint $table): void {
            $table->id();
            $table->string('client_token', 128)->nullable()->index();
            $table->string('source', 32)->default('console')->index();
            $table->longText('command');
            $table->boolean('successful')->index();
            $table->longText('stdout')->nullable();
            $table->longText('stderr')->nullable();
            $table->text('error_message')->nullable();
            $table->unsignedInteger('execution_ms')->default(0);
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cas_request_logs');
    }
};
