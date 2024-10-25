<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Sushi\Sushi;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;

class XaridData extends Model
{
    use Sushi;

    protected $fillable = [
        'actions',
        'ad',
        'all_regions',
        'anonym_viewers',
        'area_id',
        'batch_id',
        'comment_from_request',
        'company',
        'company_data',
        'company_is_green',
        'created_at',
        'creator',
        'creator_data',
        'db',
        'drop_address',
        'fields',
        'inn',
        'lot_id',
        'module',
        'my_role',
        'nad',
        'participants_count',
        'price',
        'proc_id',
        'procedure',
        'product_is_green',
        'region',
        'status',
        'subtype',
        'timebox',
        'type'
    ];

    protected $casts = [
        'actions' => 'array',
        'all_regions' => 'boolean',
        'anonym_viewers' => 'integer',
        'company_data' => 'array',
        'company_is_green' => 'boolean',
        'created_at' => 'datetime',
        'creator_data' => 'array',
        'fields' => 'array',
        'participants_count' => 'integer',
        'price' => 'integer',
        'product_is_green' => 'boolean',
        'timebox' => 'integer'
    ];

    /**
     * Prepare data for database insertion by converting arrays to JSON strings
     * @param array $data
     * @return array
     */
    protected function prepareForDatabase($data)
    {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = json_encode($value, JSON_UNESCAPED_UNICODE);
            }
        }
        return $data;
    }

    /**
     * Transform API response to match database structure
     * @param array $apiData
     * @return array
     */
    protected function transformApiData($apiData)
    {
        // Transform date fields
        if (isset($apiData['created_at'])) {
            $apiData['created_at'] = date('Y-m-d H:i:s', $apiData['created_at']);
        }

        // Handle nested arrays
        $arrayFields = ['actions', 'company_data', 'creator_data', 'fields'];
        foreach ($arrayFields as $field) {
            if (isset($apiData[$field]) && is_array($apiData[$field])) {
                $apiData[$field] = json_encode($apiData[$field], JSON_UNESCAPED_UNICODE);
            }
        }

        // Ensure boolean fields
        $booleanFields = ['all_regions', 'company_is_green', 'product_is_green'];
        foreach ($booleanFields as $field) {
            if (isset($apiData[$field])) {
                $apiData[$field] = (bool) $apiData[$field];
            }
        }

        // Ensure numeric fields
        $numericFields = ['anonym_viewers', 'participants_count', 'price', 'timebox'];
        foreach ($numericFields as $field) {
            if (isset($apiData[$field])) {
                $apiData[$field] = (int) $apiData[$field];
            }
        }

        return $apiData;
    }

    /**
     * Get rows for Sushi
     * @return array
     */
    public function getRows()
    {
        try {
            $requestData = [
                "id" => 1,
                "jsonrpc" => "2.0",
                "method" => "get_proc",
                "params" => [
                    "proc_id" => session('xarid_data_proc_id')
                ]
            ];

            $response = Http::asJson()
                ->acceptJson()
                ->post('https://api.xt-xarid.uz/urpc', $requestData);

            if (!$response->successful()) {
                return [];
            }

            $result = $response->json('result');
            if (!$result) {
                return [];
            }

            $transformedData = $this->transformApiData($result);
            $preparedData = $this->prepareForDatabase($transformedData);

            return [$preparedData];

        } catch (\Exception $e) {
            Log::error('XaridData fetch error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Schema definition for Sushi
     * @return array
     */
    public function getSchema()
    {
        return [
            'id' => 'integer',
            'actions' => 'text',
            'ad' => 'text',
            'all_regions' => 'boolean',
            'anonym_viewers' => 'integer',
            'area_id' => 'string',
            'batch_id' => 'string',
            'comment_from_request' => 'text',
            'company' => 'integer',
            'company_data' => 'text',
            'company_is_green' => 'boolean',
            'created_at' => 'datetime',
            'creator' => 'integer',
            'creator_data' => 'text',
            'db' => 'string',
            'drop_address' => 'text',
            'fields' => 'text',
            'inn' => 'string',
            'lot_id' => 'string',
            'module' => 'string',
            'my_role' => 'string',
            'nad' => 'integer',
            'participants_count' => 'integer',
            'price' => 'integer',
            'proc_id' => 'integer',
            'procedure' => 'string',
            'product_is_green' => 'boolean',
            'region' => 'string',
            'status' => 'string',
            'subtype' => 'string',
            'timebox' => 'integer',
            'type' => 'string'
        ];
    }
}