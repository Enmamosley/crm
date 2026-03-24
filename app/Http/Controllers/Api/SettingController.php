<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Setting;

class SettingController extends Controller
{
    public function index()
    {
        $keys = [
            'company_name',
            'company_address',
            'company_phone',
            'company_email',
            'company_rfc',
            'iva_percentage',
            'currency',
        ];

        $settings = [];
        foreach ($keys as $key) {
            $settings[$key] = Setting::get($key);
        }

        return response()->json(['success' => true, 'data' => $settings]);
    }
}
