<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class MyobInvoice extends Model
{

    protected $table = 'myob_invoices';
    public $timestamps = true;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'customer_uid','account_uid','myob_invoice_id','invoice_id','item_uid'
    ];

    /**
     * Invoice belongs to Client relation
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function client()
    {
        return $this->belongsTo('App\MyobClient');
    }

    public static function createMyobServiceInvoice($data) {
        $matchThese = array(
            'customer_uid' => $data['customer_uid'],
            'account_uid' => $data['account_uid']
        );

        $client = MyobInvoice::updateOrCreate($matchThese);
        return $client;
    }


    public static function createMyobItemInvoice($data) {
        $matchThese = array(
            'customer_uid' => $data['customer_uid'],
            'item_uid' => $data['item_uid']
        );

        $client = MyobInvoice::updateOrCreate($matchThese);
        return $client;
    }
}
