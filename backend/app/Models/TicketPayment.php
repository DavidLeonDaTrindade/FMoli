<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TicketPayment extends Model
{
    protected $fillable = [
        'ticket_id',
        'payment_type',
        'payment_total',
    ];

    protected $casts = [
        'payment_total' => 'decimal:2',
    ];

    public function ticket()
    {
        return $this->belongsTo(Ticket::class, 'ticket_id', 'ticket_id');
    }
}
