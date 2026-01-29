<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('ticket_payments', function (Blueprint $table) {
            $table->id();

            $table->string('ticket_id', 32)->index();
            $table->string('payment_type', 50);
            $table->decimal('payment_total', 10, 2);

            $table->timestamps();

            $table->foreign('ticket_id')
                ->references('ticket_id')
                ->on('tickets')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_payments');
    }
};
