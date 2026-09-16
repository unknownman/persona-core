<?php

namespace Persona\Tests;

use Illuminate\Database\Eloquent\Model;
use Persona\Traits\HasPersona;

class Company extends Model
{
    use HasPersona;

    protected $guarded = [];

    protected $table = 'test_companies';
}
