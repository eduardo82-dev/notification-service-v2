<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('channel', 10);
            $table->string('recipient_id', 64);
            $table->text('message');
            $table->string('priority', 20);
            $table->string('status', 20)->default('queued');
            $table->string('idempotency_key', 255)->unique();
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('last_attempted_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->text('rejected_reason')->nullable();
            $table->timestamps();

            $table->index('recipient_id');
            $table->index(['recipient_id', 'status']);
            $table->index(['recipient_id', 'channel']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
