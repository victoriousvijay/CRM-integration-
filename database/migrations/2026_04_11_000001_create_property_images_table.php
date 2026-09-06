<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Photos for a property.
 *
 * The bytes live in the database rather than on a disk because the serverless
 * host this deploys to has no persistent filesystem — anything written outside
 * /tmp is gone by the next request. Object storage is the better home for these
 * once it's configured (see StorageService::persistsUploads()); until then this
 * keeps the feature working with no external dependency.
 *
 * Stored base64-encoded in a text column rather than as a binary type: it costs
 * a third more space but behaves identically across Postgres, MySQL and SQLite,
 * which this codebase all supports.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('property_images')) {
            return;
        }

        Schema::create('property_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('filename');
            $table->string('mime_type', 100);
            $table->unsignedInteger('size_bytes');
            $table->longText('content');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_primary')->default(false);
            $table->timestamps();

            $table->index(['property_id', 'sort_order']);
            $table->index(['tenant_id', 'property_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('property_images');
    }
};
