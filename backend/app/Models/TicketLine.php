<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TicketLine extends Model
{
    protected $fillable = [
        'ticket_id',
        'line_number',
        'ean',
        'sku',
        'collection',
        'product_quantity',
        'product_total',
        'vat',
        'image',
    ];

    protected $casts = [
        'product_quantity' => 'decimal:2',
        'product_total' => 'decimal:2',
    ];

    public function ticket()
    {
        return $this->belongsTo(Ticket::class, 'ticket_id', 'ticket_id');
    }
}
