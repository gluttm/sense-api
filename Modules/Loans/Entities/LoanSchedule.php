<?php

namespace Modules\Loans\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class LoanSchedule extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'description',
        'scheduled_date',
        'effective_date',
        'loan_transaction_id',
        'scheduled_payment',
        'delay_fees',
        'residual',
        'total_monthly',
        'capital_fee',
        'loan_id',
        'created_by',

    ];

    /**
     * Get the post that owns the comment.
     */
    public function loan_transaction()
    {
        return $this->belongsTo('Modules\Loans\Entities\LoanTransaction');
    }

    protected static function newFactory()
    {
        return \Modules\Loans\Database\factories\LoanScheduleFactory::new();
    }

    protected static function boot()
    {
        parent::boot();
        LoanSchedule::saved(function ($model) {
            LoanSchedule::where('id', $model->id)->update(['code' => 'SCH' . str_pad($model->id, 5, '0', STR_PAD_LEFT)]);
        });
    }
}
