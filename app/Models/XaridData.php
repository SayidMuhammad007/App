<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Sushi\Sushi;
use Illuminate\Support\Facades\Http;

class XaridData extends Model
{
    use Sushi;

    protected $casts = [
        'actions' => 'json',
        'sign' => 'json',
        'fields' => 'json',
        'unique_viewers' => 'json',
        'opts' => 'json',
        'company_data' => 'json',
        'creator_data' => 'json',
        'gos' => 'boolean',
        'nad' => 'boolean',
        'agree' => 'boolean',
        'company_is_green' => 'boolean',
        'product_is_green' => 'boolean',
        'created_at' => 'datetime',
        'agreed_at' => 'datetime',
    ];


    public function getRows()
    {
        $requestData = [
            "id" => 1,
            "jsonrpc" => "2.0",
            "method" => "get_proc",
            "params" => [
                "proc_id" => session('xarid_data_proc_id')
            ]
        ];

        $apiCall = Http::asJson()
            ->acceptJson()
            ->post('https://api.xt-xarid.uz/urpc', $requestData);

        $result = $apiCall->json('result');

        // Flatten nested arrays and convert to string
        foreach (['actions', 'sign', 'fields', 'unique_viewers', 'opts', 'company_data', 'creator_data'] as $field) {
            if (isset($result[$field]) && is_array($result[$field])) {
                $result[$field] = json_encode($result[$field]);
            }
        }

        // Convert timestamps to DateTime objects
        foreach (['created_at', 'agreed_at'] as $dateField) {
            if (isset($result[$dateField])) {
                $result[$dateField] = date('Y-m-d H:i:s', $result[$dateField] / 1000);
            }
        }

        // Ensure boolean fields are actual booleans
        foreach (['gos', 'nad', 'agree', 'company_is_green', 'product_is_green'] as $boolField) {
            if (isset($result[$boolField])) {
                $result[$boolField] = (bool) $result[$boolField];
            }
        }
        session(['result' => $result]);
        return $result ? [$result] : [];
    }
}
