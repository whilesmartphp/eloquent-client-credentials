<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->string('secret')->nullable()->change();
            $table->json('redirect_uris')->nullable();
            $table->json('grant_types')->nullable();
        });

        Schema::table('client_access_tokens', function (Blueprint $table) {
            $table->string('subject_type')->nullable();
            $table->string('subject_id')->nullable();
            $table->json('claims')->nullable();

            $table->index(['subject_type', 'subject_id']);
        });
    }

    public function down(): void
    {
        Schema::table('client_access_tokens', function (Blueprint $table) {
            $table->dropIndex(['subject_type', 'subject_id']);
            $table->dropColumn(['subject_type', 'subject_id', 'claims']);
        });

        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn(['redirect_uris', 'grant_types']);
        });
    }
};
