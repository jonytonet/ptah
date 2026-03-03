<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ptah_companies', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            // Abbreviation shown in the company switcher — max 4 characters (e.g. "ACME", "SP01")
            $table->string('label', 4)->nullable();
            $table->string('logo_path')->nullable();
            $table->string('email')->nullable();
            $table->string('phone', 30)->nullable();
            // tax_id: CNPJ, CPF, EIN, VAT — free format to support multiple countries
            $table->string('tax_id', 50)->nullable();
            $table->string('tax_type', 20)->nullable()->comment('cnpj|cpf|ein|vat|other');
            // Address as JSON to adapt to any country/system
            $table->json('address')->nullable();
            // Extra company settings (theme, timezone, locale, etc.)
            $table->json('settings')->nullable();
            $table->boolean('is_default')->default(false)->index();
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ptah_companies');
    }
};
