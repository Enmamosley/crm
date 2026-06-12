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
            'paypal_mode'        => Setting::get('paypal_mode', 'sandbox'),
            'paypal_client_id'   => Setting::get('paypal_client_id', ''),
            'paypal_secret'      => Setting::get('paypal_secret', ''),
            'paypal_webhook_id'  => Setting::get('paypal_webhook_id', ''),
            'bank_name'         => Setting::get('bank_name', ''),
            'bank_beneficiary'  => Setting::get('bank_beneficiary', ''),
            'bank_account'      => Setting::get('bank_account', ''),
            'bank_clabe'        => Setting::get('bank_clabe', ''),
            'bank_reference'    => Setting::get('bank_reference', ''),
            'cosmotown_api_key'  => Setting::get('cosmotown_api_key', ''),
            'cosmotown_base_url' => Setting::get('cosmotown_base_url', ''),
            'meta_pixel_id'        => Setting::get('meta_pixel_id', ''),
            'meta_capi_token'      => Setting::get('meta_capi_token', ''),
            'meta_test_event_code' => Setting::get('meta_test_event_code', ''),
            'invoicing_provider'   => Setting::get('invoicing_provider', 'facturapi'),
            'finkok_username'      => Setting::get('finkok_username', ''),
            'finkok_password'      => Setting::get('finkok_password', ''),
            'finkok_environment'   => Setting::get('finkok_environment', 'demo'),
            'company_legal_name'   => Setting::get('company_legal_name', ''),
            'company_tax_system'   => Setting::get('company_tax_system', ''),
            'company_zip'          => Setting::get('company_zip', ''),
            'csd_cer_path'         => Setting::get('csd_cer_path', ''),
            'csd_key_path'         => Setting::get('csd_key_path', ''),
            'csd_key_password'     => Setting::get('csd_key_password', ''),
        ];

        // Información del CSD cargado (RFC y vigencia) para mostrar en Ajustes
        $csdInfo = null;
        if ($settings['csd_cer_path'] && $settings['csd_key_path']) {
            try {
                $credential = (new \App\Services\CfdiBuilderService())->credential();
                $csdInfo = [
                    'rfc'      => $credential->rfc(),
                    'name'     => $credential->legalName(),
                    'valid_to' => $credential->certificate()->validToDateTime()->format('d/m/Y'),
                ];
            } catch (\Throwable $e) {
                $csdInfo = ['error' => 'CSD cargado pero inválido: ' . $e->getMessage()];
            }
        }

        return view('admin.settings.index', compact('settings', 'csdInfo'));
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
            'paypal_mode'       => 'nullable|in:sandbox,live',
            'paypal_client_id'  => 'nullable|string|max:200',
            'paypal_secret'     => 'nullable|string|max:200',
            'paypal_webhook_id' => 'nullable|string|max:100',
            'cosmotown_api_key'  => 'nullable|string|max:200',
            'cosmotown_base_url' => 'nullable|url|max:255',
            'meta_pixel_id'        => 'nullable|string|max:50',
            'meta_capi_token'      => 'nullable|string|max:600',
            'meta_test_event_code' => 'nullable|string|max:50',
            'invoicing_provider'   => 'nullable|in:facturapi,finkok',
            'finkok_username'      => 'nullable|string|max:150',
            'finkok_password'      => 'nullable|string|max:150',
            'finkok_environment'   => 'nullable|in:demo,live',
            'company_legal_name'   => 'nullable|string|max:255',
            'company_tax_system'   => 'nullable|string|max:3',
            'company_zip'          => 'nullable|string|max:5',
            'csd_cer'              => 'nullable|file|max:10',
            'csd_key'              => 'nullable|file|max:10',
            'csd_key_password'     => 'nullable|string|max:100',
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

        // PayPal: mode siempre se guarda (radio), las demás solo si vienen rellenas
        Setting::set('paypal_mode', $request->input('paypal_mode', 'sandbox'));
        foreach (['paypal_client_id', 'paypal_secret', 'paypal_webhook_id'] as $ppKey) {
            if ($request->filled($ppKey)) {
                Setting::set($ppKey, $request->input($ppKey));
            }
        }

        foreach (['bank_name', 'bank_beneficiary', 'bank_account', 'bank_clabe', 'bank_reference',
                  'cosmotown_base_url'] as $key) {
            Setting::set($key, $request->input($key, ''));
        }

        // Secreto: sólo se actualiza si viene relleno (no se borra al guardar vacío)
        if ($request->filled('cosmotown_api_key')) {
            Setting::set('cosmotown_api_key', $request->input('cosmotown_api_key'));
        }

        // Meta / Facebook: Pixel ID y test code son públicos/borrables; el token CAPI es secreto.
        Setting::set('meta_pixel_id', $request->input('meta_pixel_id', ''));
        Setting::set('meta_test_event_code', $request->input('meta_test_event_code', ''));
        if ($request->filled('meta_capi_token')) {
            Setting::set('meta_capi_token', $request->input('meta_capi_token'));
        }

        // Facturación: proveedor + Finkok + datos del emisor + CSD
        Setting::set('invoicing_provider', $request->input('invoicing_provider', 'facturapi'));
        Setting::set('finkok_environment', $request->input('finkok_environment', 'demo'));
        foreach (['company_legal_name', 'company_tax_system', 'company_zip'] as $key) {
            if ($request->filled($key)) {
                Setting::set($key, $request->input($key));
            }
        }
        // Secretos: sólo se actualizan si vienen rellenos
        foreach (['finkok_username', 'finkok_password', 'csd_key_password'] as $key) {
            if ($request->filled($key)) {
                Setting::set($key, $request->input($key));
            }
        }
        // CSD: archivos .cer/.key a disco privado
        if ($request->hasFile('csd_cer')) {
            Setting::set('csd_cer_path', $request->file('csd_cer')->storeAs('csd', 'certificado.cer', 'local'));
        }
        if ($request->hasFile('csd_key')) {
            Setting::set('csd_key_path', $request->file('csd_key')->storeAs('csd', 'llave.key', 'local'));
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
