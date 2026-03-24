<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\DiscountCode;
use Illuminate\Http\Request;

class DiscountCodeController extends Controller
{
    public function index()
    {
        $codes = DiscountCode::latest()->paginate(15);
        return view('admin.discount-codes.index', compact('codes'));
    }

    public function create()
    {
        return view('admin.discount-codes.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'code'        => 'required|string|max:50|unique:discount_codes,code',
            'description' => 'nullable|string|max:255',
            'type'        => 'required|in:percentage,fixed',
            'value'       => 'required|numeric|min:0.01',
            'min_amount'  => 'nullable|numeric|min:0',
            'max_uses'    => 'nullable|integer|min:1',
            'valid_from'  => 'nullable|date',
            'valid_until' => 'nullable|date|after_or_equal:valid_from',
            'active'      => 'boolean',
        ]);

        $validated['code'] = strtoupper($validated['code']);
        $validated['active'] = $request->boolean('active', true);

        $code = DiscountCode::create($validated);
        ActivityLog::log('discount_created', $code, "Cupón '{$code->code}' creado");

        return redirect()->route('admin.discount-codes.index')
            ->with('success', 'Código de descuento creado.');
    }

    public function edit(DiscountCode $discountCode)
    {
        return view('admin.discount-codes.edit', compact('discountCode'));
    }

    public function update(Request $request, DiscountCode $discountCode)
    {
        $validated = $request->validate([
            'code'        => 'required|string|max:50|unique:discount_codes,code,' . $discountCode->id,
            'description' => 'nullable|string|max:255',
            'type'        => 'required|in:percentage,fixed',
            'value'       => 'required|numeric|min:0.01',
            'min_amount'  => 'nullable|numeric|min:0',
            'max_uses'    => 'nullable|integer|min:1',
            'valid_from'  => 'nullable|date',
            'valid_until' => 'nullable|date|after_or_equal:valid_from',
            'active'      => 'boolean',
        ]);

        $validated['code'] = strtoupper($validated['code']);
        $validated['active'] = $request->boolean('active', true);

        $discountCode->update($validated);
        ActivityLog::log('discount_updated', $discountCode, "Cupón '{$discountCode->code}' actualizado");

        return redirect()->route('admin.discount-codes.index')
            ->with('success', 'Código de descuento actualizado.');
    }

    public function destroy(DiscountCode $discountCode)
    {
        ActivityLog::log('discount_deleted', $discountCode, "Cupón '{$discountCode->code}' eliminado");
        $discountCode->delete();

        return redirect()->route('admin.discount-codes.index')
            ->with('success', 'Código de descuento eliminado.');
    }
}
