<?php

namespace App\Console\Commands;

use App\Models\Ticket;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class ImportTickets extends Command
{
    protected $signature = 'tickets:import {--dateFrom=} {--dateTo=} {--store=}';
    protected $description = 'Importa tickets (cabecera, pagos y líneas) desde Gisela a la base de datos';

    public function handle(): int
    {
        // Config (services.gisela)
        $url   = config('services.gisela.tickets_url');
        $token = config('services.gisela.tickets_token');
        $store = $this->option('store') ?? config('services.gisela.store');

        // Fechas: por defecto AYER
        $dateFrom = $this->option('dateFrom') ?? now()->subDay()->toDateString();
        $dateTo   = $this->option('dateTo')   ?? $dateFrom;

        if (!$url || !$token || !$store) {
            $this->error('Falta configuración. Revisa config/services.php (services.gisela) y .env (GISELA_TICKETS_URL, GISELA_TICKETS_TOKEN, GISELA_STORE_CODE).');
            return self::FAILURE;
        }

        // Validación simple YYYY-MM-DD
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) {
            $this->error('Formato de fecha inválido. Usa YYYY-MM-DD (ej: 2026-01-28).');
            return self::FAILURE;
        }

        $this->info("Importando tickets store={$store} dateFrom={$dateFrom} dateTo={$dateTo}");

        // Llamada al proveedor
        $response = Http::timeout(60)
            ->withHeaders([
                'X-AUTHORIZATION' => $token,
                'Accept' => 'application/json',
            ])
            ->get($url, [
                'store' => $store,
                'dateFrom' => $dateFrom,
                'dateTo' => $dateTo,
            ]);

        if (!$response->successful()) {
            $this->error('Error HTTP ' . $response->status());
            $this->line(substr($response->body(), 0, 800));
            return self::FAILURE;
        }

        $payload = $response->json();

        // A veces el API devuelve httpStatus en el JSON aunque HTTP sea 200/202
        if (is_array($payload) && isset($payload['httpStatus']) && (int)$payload['httpStatus'] !== 200) {
            $msg = $payload['message'] ?? 'Respuesta con error';
            $this->error("API devolvió httpStatus={$payload['httpStatus']} - {$msg}");
            return self::FAILURE;
        }

        if (!is_array($payload) || !isset($payload['tickets']) || !is_array($payload['tickets'])) {
            $this->error('Formato inválido: se esperaba { tickets: [...] }');
            $this->line(substr($response->body(), 0, 800));
            return self::FAILURE;
        }

        $tickets = $payload['tickets'];

        $imported = 0;
        $skipped = 0;

        foreach ($tickets as $t) {
            DB::transaction(function () use ($t, &$imported, &$skipped) {

                // ----- 1) HASH ESTABLE (ordenar pagos/líneas) -----
                $paymentsSorted = collect($t['payments'] ?? [])
                    ->map(fn ($p) => [
                        'payment_type' => $p['payment_type'] ?? null,
                        'payment_total' => (string)($p['payment_total'] ?? null),
                    ])
                    ->sortBy(fn ($p) => ($p['payment_type'] ?? '') . '|' . ($p['payment_total'] ?? ''))
                    ->values()
                    ->toArray();

                $linesSorted = collect($t['lines'] ?? [])
                    ->map(fn ($l) => [
                        'line_number' => (int)($l['line_number'] ?? 0),
                        'ean' => $l['ean'] ?? null,
                        'sku' => $l['sku'] ?? null,
                        'collection' => $l['collection'] ?? null,
                        'product_quantity' => (string)($l['product_quantity'] ?? null),
                        'product_total' => (string)($l['product_total'] ?? null),
                        'vat' => $l['vat'] ?? null,
                        'image' => $l['image'] ?? null,
                    ])
                    ->sortBy('line_number')
                    ->values()
                    ->toArray();

                $hashData = [
                    'ticket_id' => $t['ticket_id'] ?? null,
                    'ticket_date' => $t['ticket_date'] ?? null,
                    'invoice_code' => $t['invoice_code'] ?? null,
                    'total' => (string)($t['total'] ?? null),
                    'origin' => $t['origin'] ?? null,
                    'client_identifier' => $t['client_identifier'] ?? null,
                    'invoice_name' => $t['invoice_name'] ?? null,
                    'invoice_address' => $t['invoice_address'] ?? null,
                    'invoice_postal_code' => $t['invoice_postal_code'] ?? null,
                    'invoice_county' => $t['invoice_county'] ?? null,
                    'invoice_country' => $t['invoice_country'] ?? null,
                    'employee_name' => $t['employee_name'] ?? null,
                    'payments' => $paymentsSorted,
                    'lines' => $linesSorted,
                ];

                $sourceHash = hash('sha256', json_encode($hashData, JSON_UNESCAPED_UNICODE));

                // ----- 2) SKIP si no cambió -----
                $existing = Ticket::find($t['ticket_id']);

                if ($existing && $existing->source_hash === $sourceHash) {
                    $existing->imported_at = now();
                    $existing->save();
                    $skipped++;
                    return;
                }

                // ----- 3) Upsert ticket (guardando hash) -----
                /** @var \App\Models\Ticket $ticket */
                $ticket = Ticket::updateOrCreate(
                    ['ticket_id' => $t['ticket_id']],
                    [
                        'ticket_date' => $t['ticket_date'],
                        'invoice_code' => $t['invoice_code'] ?? null,
                        'total' => $t['total'],
                        'origin' => $t['origin'] ?? null,
                        'client_identifier' => $t['client_identifier'] ?? null,
                        'invoice_name' => $t['invoice_name'] ?? null,
                        'invoice_address' => $t['invoice_address'] ?? null,
                        'invoice_postal_code' => $t['invoice_postal_code'] ?? null,
                        'invoice_county' => $t['invoice_county'] ?? null,
                        'invoice_country' => $t['invoice_country'] ?? null,
                        'employee_name' => $t['employee_name'] ?? null,
                        'source_hash' => $sourceHash,
                        'imported_at' => now(),
                    ]
                );

                // ----- 4) Refresh pagos -----
                $ticket->payments()->delete();
                foreach (($t['payments'] ?? []) as $p) {
                    $ticket->payments()->create([
                        'payment_type' => $p['payment_type'],
                        'payment_total' => $p['payment_total'],
                    ]);
                }

                // ----- 5) Refresh líneas -----
                $ticket->lines()->delete();
                foreach (($t['lines'] ?? []) as $l) {
                    $ticket->lines()->create([
                        'line_number' => (int) $l['line_number'],
                        'ean' => $l['ean'] ?? null,
                        'sku' => $l['sku'] ?? null,
                        'collection' => $l['collection'] ?? null,
                        'product_quantity' => $l['product_quantity'],
                        'product_total' => $l['product_total'],
                        'vat' => $l['vat'] ?? null,
                        'image' => $l['image'] ?? null,
                    ]);
                }

                $imported++;
            });
        }

        $this->info("Importación OK. Tickets procesados: {$imported} | Skipped (sin cambios): {$skipped}");
        return self::SUCCESS;
    }
}
