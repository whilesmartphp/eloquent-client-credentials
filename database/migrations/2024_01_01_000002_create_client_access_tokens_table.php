<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_access_tokens', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->nullableMorphs('client');
            $table->string('token', 80)->unique();
            $table->json('scopes')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->boolean('revoked')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_access_tokens');
    }
};
