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
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id')
                ->constrained('organization_members')
                ->restrictOnDelete(); // anggota yang punya tagihan tidak bisa dihapus
            $table->char('period', 7);                       // 'YYYY-MM'
            $table->bigInteger('amount');                    // rupiah
            $table->string('status', 20)->default('belum');  // belum | menunggu | lunas
            $table->string('method', 20)->nullable();        // transfer | tunai
            $table->string('proof_path')->nullable();
            $table->string('rejection_reason')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->unique(['member_id', 'period']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
