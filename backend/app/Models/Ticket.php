<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Ticket extends Model
{
    protected $primaryKey = 'ticket_id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'ticket_id',
        'ticket_date',
        'invoice_code',
        'total',
        'origin',
        'client_identifier',
        'invoice_name',
        'invoice_address',
        'invoice_postal_code',
        'invoice_county',
        'invoice_country',
        'employee_name',
        'source_hash',
        'imported_at',

    ];

    protected $casts = [
        'ticket_date' => 'datetime',
        'total' => 'decimal:2',
        'imported_at' => 'datetime',

    ];

    public function payments()
    {
        return $this->hasMany(TicketPayment::class, 'ticket_id', 'ticket_id');
    }

    public function lines()
    {
        return $this->hasMany(TicketLine::class, 'ticket_id', 'ticket_id');
    }
}
