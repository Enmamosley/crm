<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Setting;
use App\Services\TwentyIService;
use Illuminate\Http\Request;

class SettingController extends Controller
{
    public function index()
    {
        $settings = [
            'company_name'     => Setting::get('company_name', ''),
            'company_address'  => Setting::get('company_address', ''),
            'company_phone'    => Setting::get('company_phone', ''),
            'company_email'    => Setting::get('company_email', ''),
            'company_rfc'      => Setting::get('company_rfc', ''),
            'company_logo'     => Setting::get('company_logo', ''),
            'iva_percentage'   => Setting::get('iva_percentage', '16'),
            'facturapi_api_key' => Setting::get('facturapi_api_key', ''),
            'twentyi_api_key'            => Setting::get('twentyi_api_key', ''),
            'twentyi_package_bundle_id'  => Setting::get('twentyi_package_bundle_id', ''),
            'mp_public_key'              => Setting::get('mp_public_key', ''),
            'mp_access_token'   => Setting::get('mp_access_token', ''),
            'mp_webhook_secret' => Setting::get('mp_webhook_secret', ''),
            'bank_name'         => Setting::get('bank_name', ''),
            'bank_beneficiary'  => Setting::get('bank_beneficiary', ''),
            'bank_account'      => Setting::get('bank_account', ''),
            'bank_clabe'        => Setting::get('bank_clabe', ''),
            'bank_reference'    => Setting::get('bank_reference', ''),
            'cosmotown_api_key'  => Setting::get('cosmotown_api_key', ''),
            'cosmotown_base_url' => Setting::get('cosmotown_base_url', ''),
        ];

        return view('admin.settings.index', compact('settings'));
    }

    public function update(Request $request)
    {
        $request->validate([
            'company_name'      => 'nullable|string|max:255',
            'company_address'   => 'nullable|string',
            'company_phone'     => 'nullable|string|max:50',
            'company_email'     => 'nullable|email|max:255',
            'company_rfc'       => 'nullable|string|max:20',
            'iva_percentage'    => 'nullable|numeric|min:0|max:100',
            'company_logo'      => 'nullable|image|max:2048',
            'facturapi_api_key' => 'nullable|string|max:100',
            'twentyi_api_key'            => 'nullable|string|max:200',
            'twentyi_package_bundle_id'  => 'nullable|string|max:50',
            'mp_public_key'              => 'nullable|string|max:200',
            'mp_access_token'   => 'nullable|string|max:200',
            'mp_webhook_secret' => 'nullable|string|max:200',
            'cosmotown_api_key'  => 'nullable|string|max:200',
            'cosmotown_base_url' => 'nullable|url|max:255',
            'bank_name'         => 'nullable|string|max:100',
            'bank_beneficiary'  => 'nullable|string|max:200',
            'bank_account'      => 'nullable|string|max:30',
            'bank_clabe'        => 'nullable|string|max:18',
            'bank_reference'    => 'nullable|string|max:200',
        ]);

        $keys = ['company_name','company_address','company_phone','company_email','company_rfc','iva_percentage'];
        foreach ($keys as $key) {
            if ($request->filled($key)) {
                Setting::set($key, $request->input($key));
            }
        }

        if ($request->filled('facturapi_api_key')) {
            Setting::set('facturapi_api_key', $request->input('facturapi_api_key'));
        }

        if ($request->filled('twentyi_api_key')) {
            Setting::set('twentyi_api_key', $request->input('twentyi_api_key'));
        }

        if ($request->filled('twentyi_package_bundle_id')) {
            Setting::set('twentyi_package_bundle_id', $request->input('twentyi_package_bundle_id'));
        }

        foreach (['mp_public_key', 'mp_access_token', 'mp_webhook_secret'] as $mpKey) {
            if ($request->filled($mpKey)) {
                Setting::set($mpKey, $request->input($mpKey));
            }
        }

        foreach (['bank_name', 'bank_beneficiary', 'bank_account', 'bank_clabe', 'bank_reference',
                  'cosmotown_api_key', 'cosmotown_base_url'] as $key) {
            Setting::set($key, $request->input($key, ''));
        }

        if ($request->hasFile('company_logo')) {
            $path = $request->file('company_logo')->store('logos', 'public');
            Setting::set('company_logo', $path);
        }

        ActivityLog::log('settings_updated', null, 'Configuración del sistema actualizada por ' . auth()->user()->name);

        return redirect()->route('admin.settings.index')
            ->with('success', 'Configuración actualizada.');
    }

    public function packageBundleTypes()
    {
        try {
            $types = (new TwentyIService())->listPackageBundleTypes();
            return response()->json(['success' => true, 'data' => $types]);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 422);
        }
    }
}
