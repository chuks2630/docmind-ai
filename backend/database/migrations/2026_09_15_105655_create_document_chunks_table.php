<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('document_chunks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('chunk_index');
            $table->text('text');
            $table->unsignedInteger('start_page');
            $table->unsignedInteger('end_page');
            $table->unsignedInteger('start_offset');
            $table->unsignedInteger('end_offset');
            $table->timestamps();
        });

        // pgvector column sized for Voyage AI's voyage-3 model (1024 dimensions),
        // the standardized embedding provider/model for this project.
        DB::statement('ALTER TABLE document_chunks ADD COLUMN embedding vector(1024)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('document_chunks');
    }
};
