@extends('layouts.admin')
@section('title', 'Cupones de Descuento')
@section('header', 'Cupones de Descuento')

@section('actions')
<a href="{{ route('admin.discount-codes.create') }}" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition">
    <i class="fas fa-plus mr-1"></i> Nuevo Cupón
</a>
@endsection

@section('content')
<div class="bg-white rounded-lg shadow overflow-hidden">
    <table class="w-full">
        <thead class="bg-gray-50">
            <tr>
                <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 uppercase">Código</th>
                <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 uppercase">Tipo</th>
                <th class="text-right px-6 py-3 text-xs font-medium text-gray-500 uppercase">Valor</th>
                <th class="text-center px-6 py-3 text-xs font-medium text-gray-500 uppercase">Usos</th>
                <th class="text-center px-6 py-3 text-xs font-medium text-gray-500 uppercase">Vigencia</th>
                <th class="text-center px-6 py-3 text-xs font-medium text-gray-500 uppercase">Estado</th>
                <th class="text-right px-6 py-3 text-xs font-medium text-gray-500 uppercase">Acciones</th>
            </tr>
        </thead>
        <tbody class="divide-y">
            @forelse($codes as $code)
                <tr class="hover:bg-gray-50">
                    <td class="px-6 py-4 font-mono font-bold text-sm">{{ $code->code }}</td>
                    <td class="px-6 py-4 text-sm text-gray-500">
                        {{ $code->type === 'percentage' ? 'Porcentaje' : 'Monto fijo' }}
                    </td>
                    <td class="px-6 py-4 text-right text-sm font-medium">
                        {{ $code->type === 'percentage' ? $code->value . '%' : '$' . number_format($code->value, 2) }}
                    </td>
                    <td class="px-6 py-4 text-center text-sm text-gray-500">
                        {{ $code->times_used }} / {{ $code->max_uses ?? '∞' }}
                    </td>
                    <td class="px-6 py-4 text-center text-sm text-gray-500">
                        @if($code->valid_from || $code->valid_until)
                            {{ $code->valid_from?->format('d/m/Y') ?? '—' }} → {{ $code->valid_until?->format('d/m/Y') ?? '—' }}
                        @else
                            Sin límite
                        @endif
                    </td>
                    <td class="px-6 py-4 text-center">
                        @if($code->active)
                            <span class="px-2 py-1 text-xs rounded-full bg-green-100 text-green-700">Activo</span>
                        @else
                            <span class="px-2 py-1 text-xs rounded-full bg-gray-100 text-gray-500">Inactivo</span>
                        @endif
                    </td>
                    <td class="px-6 py-4 text-right">
                        <a href="{{ route('admin.discount-codes.edit', $code) }}" class="text-gray-500 hover:text-blue-600 mr-3">
                            <i class="fas fa-edit"></i>
                        </a>
                        <form action="{{ route('admin.discount-codes.destroy', $code) }}" method="POST" class="inline" onsubmit="return confirm('¿Eliminar este cupón?')">
                            @csrf @method('DELETE')
                            <button type="submit" class="text-gray-500 hover:text-red-600"><i class="fas fa-trash"></i></button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" class="px-6 py-8 text-center text-gray-500">No hay cupones de descuento.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="p-4 border-t">
        {{ $codes->links() }}
    </div>
</div>
@endsection
