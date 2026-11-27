<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $uuids = (bool) config('client-credentials.uuids', true);

        Schema::table('clients', function (Blueprint $table) {
            $table->string('secret')->nullable()->change();
            $table->json('redirect_uris')->nullable();
            $table->json('grant_types')->nullable();
            $table->boolean('confidential')->default(true);
        });

        Schema::table('client_access_tokens', function (Blueprint $table) {
            $table->string('user_type')->nullable();
            $table->string('user_id')->nullable();
            $table->json('claims')->nullable();

            $table->index(['user_type', 'user_id']);
        });

        Schema::create('client_authorization_codes', function (Blueprint $table) use ($uuids) {
            $table->string('id', 100)->primary();
            if ($uuids) {
                $table->uuid('client_id');
            } else {
                $table->unsignedBigInteger('client_id');
            }
            $table->string('user_type');
            $table->string('user_id');
            $table->json('scopes')->nullable();
            $table->json('claims')->nullable();
            $table->boolean('revoked')->default(false);
            $table->timestamp('expires_at');
            $table->timestamps();

            $table->foreign('client_id')->references('id')->on('clients')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_authorization_codes');

        Schema::table('client_access_tokens', function (Blueprint $table) {
            $table->dropIndex(['user_type', 'user_id']);
            $table->dropColumn(['user_type', 'user_id', 'claims']);
        });

        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn(['redirect_uris', 'grant_types', 'confidential']);
        });
    }
};
