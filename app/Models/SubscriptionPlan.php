<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SubscriptionPlan extends Model
{
    use HasFactory;
    protected $guarded = ['id', 'created_at', 'updated_at'];

    public function users()
    {
        return $this->hasMany(UserSubscription::class, 'plan_id');
    }

    public function payments()
    {
        return $this->hasMany(PaymentHistory::class, 'plan_id');
    }
}
