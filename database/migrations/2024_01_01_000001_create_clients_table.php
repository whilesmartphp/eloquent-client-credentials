<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $uuids = (bool) config('client-credentials.uuids', true);

        Schema::create('clients', function (Blueprint $table) use ($uuids) {
            if ($uuids) {
                $table->uuid('id')->primary();
            } else {
                $table->id();
            }
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('secret');
            $table->nullableMorphs('owner');
            $table->boolean('revoked')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clients');
    }
};
