<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Branch extends Model
{
    protected $table = "branches";

    protected $dates = ['deleted_at'];

    protected $guarded = ['id'];

    protected $fillable = ['branch_code','branch_name','user_id'];
}
