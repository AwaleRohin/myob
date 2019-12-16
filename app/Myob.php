<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Myob extends Model
{
    protected $fillable = [
        'email', 'refresh_token','access_token','userid'
    ];
    protected $table = 'myob';
}
