<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class MyobClient extends Model
{

    public $timestamps = true;

    protected $table = 'myob_clients';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'myob_companyFile_guid','crf_uri'
    ];


    /**
     * Client has many invoices relation
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function invoices()
    {
        return $this->hasMany('App\MyobInvoice');
    }

    public static function createMyobClient($data) {
        $matchThese = array(
            'myob_companyFile_guid' => $data[0]['Id'],
            'crf_uri' => $data[0]['Uri']
        );

        $client = MyobClient::updateOrCreate($matchThese);
        return $client;
    }

}
