<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ptah_pages', function (Blueprint $table) {
            $table->id();
            // Unique slug: identifies the page in the system (e.g. 'admin.users', 'reports.financial')
            $table->string('slug')->unique();
            $table->string('name');
            $table->string('description')->nullable();
            // Laravel route: used to generate automatic links
            $table->string('route')->nullable();
            // Icon heroicon / tabler / fontawesome — free for the system to define
            $table->string('icon')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->integer('sort_order')->default(0);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            // No softDeletes: pages are system records, never "deleted"
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ptah_pages');
    }
};
