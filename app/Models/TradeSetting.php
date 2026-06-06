<?php

namespace App\Models;

use App\Models\Concerns\Auditable;

use Illuminate\Database\Eloquent\Model;

class TradeSetting extends Model
{
    use Auditable;

    protected $fillable = [
        'key',
        'value',
        'description',
        'created_by',
        'updated_by',
    ];
}
