<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $uuids = (bool) config('client-credentials.uuids', true);

        Schema::create('client_refresh_tokens', function (Blueprint $table) use ($uuids) {
            if ($uuids) {
                $table->uuid('id')->primary();
                $table->uuid('access_token_id');
            } else {
                $table->id();
                $table->unsignedBigInteger('access_token_id');
            }
            $table->string('token', 80)->unique();
            $table->timestamp('expires_at')->nullable();
            $table->boolean('revoked')->default(false);
            $table->timestamps();

            $table->foreign('access_token_id')
                ->references('id')
                ->on('client_access_tokens')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_refresh_tokens');
    }
};
