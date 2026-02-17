<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            if (! Schema::hasColumn('clients', 'tax_id')) {
                $table->string('tax_id')->nullable()->after('address');
            }
            if (! Schema::hasColumn('clients', 'currency')) {
                $table->string('currency', 3)->default('USD')->after('tax_id');
            }
        });

        if (! Schema::hasTable('client_contacts')) {
            Schema::create('client_contacts', function (Blueprint $table) {
                $table->id();
                $table->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
                $table->string('name');
                $table->string('email')->nullable();
                $table->string('phone')->nullable();
                $table->string('position')->nullable();
                $table->boolean('is_primary')->default(false);
                $table->timestamps();
                $table->index('client_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('client_contacts');

        Schema::table('clients', function (Blueprint $table) {
            if (Schema::hasColumn('clients', 'tax_id')) {
                $table->dropColumn('tax_id');
            }
            if (Schema::hasColumn('clients', 'currency')) {
                $table->dropColumn('currency');
            }
        });
    }
};
