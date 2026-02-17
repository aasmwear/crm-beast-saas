<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
            $table->foreignId('project_id')->nullable()->constrained('projects')->nullOnDelete();
            $table->string('number')->unique();
            $table->date('issue_date');
            $table->date('due_date');
            $table->string('status', 20)->default('Draft'); // Draft, Sent, Paid, Overdue
            $table->unsignedBigInteger('total_cents')->default(0);
            $table->string('currency', 3)->default('USD');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['organization_id', 'client_id']);
        });

        Schema::create('invoice_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained('invoices')->cascadeOnDelete();
            $table->string('description');
            $table->decimal('quantity', 12, 2)->default(1);
            $table->unsignedBigInteger('unit_price_cents');
            $table->unsignedBigInteger('amount_cents');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_items');
        Schema::dropIfExists('invoices');
    }
};
