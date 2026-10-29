<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('import_errors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('import_id')->constrained('imports')->cascadeOnDelete();
            $table->foreignId('chunk_id')->nullable()->constrained('import_chunks')->cascadeOnDelete();
            $table->unsignedBigInteger('row_number');
            $table->string('column')->nullable();
            $table->text('value')->nullable();
            $table->string('error_code', 50)->index();
            $table->text('message');
            $table->json('data')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['import_id', 'row_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_errors');
    }
};
