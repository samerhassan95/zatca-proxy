<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('zatca_requests', function (Blueprint $table) {
            $table->id();
            $table->uuid('request_id')->unique();
            $table->string('client_ip', 45);
            $table->string('client_id')->nullable();
            $table->enum('type', ['report_invoice', 'generate_qr']);
            $table->json('payload');
            $table->enum('status', ['processing', 'completed', 'failed', 'error'])->default('processing');
            $table->json('response')->nullable();
            $table->string('zatca_uuid')->nullable();
            $table->text('zatca_hash')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            // Indexes for performance
            $table->index(['request_id']);
            $table->index(['client_ip']);
            $table->index(['client_id']);
            $table->index(['type']);
            $table->index(['status']);
            $table->index(['zatca_uuid']);
            $table->index(['created_at']);
            $table->index(['processed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('zatca_requests');
    }
};