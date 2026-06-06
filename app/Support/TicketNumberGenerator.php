<?php

namespace App\Support;

use App\Models\SupportTicket;
use Illuminate\Support\Str;

class TicketNumberGenerator
{
    public static function generate(): string
    {
        do {
            $number = 'TKT-'.now()->format('Ymd').'-'.strtoupper(Str::random(4));
        } while (SupportTicket::where('ticket_number', $number)->exists());

        return $number;
    }
}
