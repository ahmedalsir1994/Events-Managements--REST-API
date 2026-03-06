<?php

namespace App\Models;
use App\Models\Attendee;
use App\Models\User;


use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Event extends Model
{
    use HasFactory;
    
     protected $fillable = [ 'user_id', 'name', 'description', 'start_time', 'end_time', 'user_id'];

    public function attendees()
    {
        return $this->hasMany(Attendee::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }   
}