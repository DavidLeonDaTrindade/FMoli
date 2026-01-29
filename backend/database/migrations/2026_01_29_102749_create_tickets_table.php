<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('tickets', function (Blueprint $table) {
            $table->string('ticket_id', 32)->primary();

            $table->dateTime('ticket_date')->index();
            $table->string('invoice_code', 64)->nullable()->index();

            $table->decimal('total', 10, 2);

            $table->string('origin', 50)->nullable()->index();

            $table->string('client_identifier', 64)->nullable();

            $table->string('invoice_name', 255)->nullable();
            $table->string('invoice_address', 255)->nullable();
            $table->string('invoice_postal_code', 20)->nullable();
            $table->string('invoice_county', 100)->nullable();
            $table->string('invoice_country', 100)->nullable();

            $table->string('employee_name', 50)->nullable()->index();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tickets');
    }
};
