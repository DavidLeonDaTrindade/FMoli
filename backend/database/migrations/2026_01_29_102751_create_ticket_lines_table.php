<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('ticket_lines', function (Blueprint $table) {
            $table->id();

            $table->string('ticket_id', 32)->index();
            $table->unsignedInteger('line_number');

            $table->string('ean', 32)->nullable()->index();
            $table->string('sku', 64)->nullable()->index();
            $table->string('collection', 100)->nullable();

            $table->decimal('product_quantity', 10, 2);
            $table->decimal('product_total', 10, 2);

            $table->string('vat', 20)->nullable();
            $table->text('image')->nullable();

            $table->timestamps();

            $table->unique(['ticket_id', 'line_number']);

            $table->foreign('ticket_id')
                ->references('ticket_id')
                ->on('tickets')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_lines');
    }
};
