<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Disable transaction for this migration so extension checks do not abort the transaction block.
     */
    public $withinTransaction = false;

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $hasVector = false;

        if (DB::getDriverName() === 'pgsql') {
            // Check if vector type already exists or if vector extension can be activated safely
            $vectorTypeExists = !empty(DB::select("SELECT 1 FROM pg_type WHERE typname = 'vector'"));

            if ($vectorTypeExists) {
                $hasVector = true;
            } else {
                $vectorExtensionAvailable = !empty(DB::select("SELECT 1 FROM pg_available_extensions WHERE name = 'vector'"));
                if ($vectorExtensionAvailable) {
                    try {
                        DB::statement('CREATE EXTENSION IF NOT EXISTS vector;');
                        $hasVector = !empty(DB::select("SELECT 1 FROM pg_type WHERE typname = 'vector'"));
                    } catch (\Throwable $e) {
                        $hasVector = false;
                    }
                }
            }
        }

        Schema::create('kb_chunks', function (Blueprint $table) use ($hasVector) {
            $table->id('chunk_id');
            $table->foreignId('document_id')->constrained('kb_documents', 'document_id')->cascadeOnDelete();
            $table->integer('chunk_index');
            $table->text('content');

            if ($hasVector) {
                $table->addColumn('vector', 'embedding')->nullable();
            } else {
                $table->text('embedding')->nullable();
            }

            $table->jsonb('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['document_id', 'chunk_index']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('kb_chunks');
    }
};
