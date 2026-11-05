<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->constrained()->restrictOnDelete();
            $table->foreignId('invoice_id')->nullable()->unique()
                ->constrained()->restrictOnDelete();       // terisi = transaksi otomatis, terkunci
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type', 10);                      // masuk | keluar
            $table->bigInteger('amount');                    // rupiah
            $table->date('tanggal');
            $table->string('description');
            $table->string('attachment_path')->nullable();
            $table->softDeletes();                           // kolom deleted_at
            $table->timestamps();

            $table->index(['organization_id', 'tanggal']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
