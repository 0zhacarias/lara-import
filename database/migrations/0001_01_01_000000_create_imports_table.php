<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('imports', function (Blueprint $table) {
            $table->id();
            $table->ulid('uuid')->unique();
            $table->string('user_id')->nullable();
            $table->string('tenant_id', 64)->nullable();
            $table->string('file_name');
            $table->string('original_name');
            $table->string('file_path');
            $table->string('disk');
            $table->string('mime_type')->nullable();
            $table->string('extension', 10);
            $table->unsignedBigInteger('file_size')->default(0);
            $table->string('importer');
            $table->string('status', 30)->index();
            $table->string('mode', 20)->default('partial');
            $table->unsignedBigInteger('total_rows')->default(0);
            $table->unsignedBigInteger('processed_rows')->default(0);
            $table->unsignedBigInteger('successful_rows')->default(0);
            $table->unsignedBigInteger('failed_rows')->default(0);
            $table->unsignedBigInteger('skipped_rows')->default(0);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('error_message')->nullable();
            $table->json('options')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index(['tenant_id', 'created_at']);
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('imports');
    }
};
