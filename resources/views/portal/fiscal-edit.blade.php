<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Datos Fiscales — Portal</title>
    @vite('resources/css/app.css')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body class="bg-gray-50 min-h-screen">
    <header class="bg-white shadow-sm border-b">
        <div class="max-w-3xl mx-auto px-6 py-4">
            <a href="{{ route('portal.dashboard', $client->portal_token) }}" class="text-sm text-blue-600 hover:underline"><i class="fas fa-arrow-left mr-1"></i> Volver al portal</a>
            <h1 class="text-xl font-bold text-gray-900 mt-1"><i class="fas fa-building mr-2 text-indigo-500"></i>Datos Fiscales</h1>
        </div>
    </header>

    <main class="max-w-3xl mx-auto px-6 py-8">
        @if(session('success'))
            <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg mb-6">{{ session('success') }}</div>
        @endif
        @if($errors->any())
            <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg mb-6">
                @foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach
            </div>
        @endif

        <form method="POST" action="{{ route('portal.fiscal.update', $client->portal_token) }}" class="bg-white rounded-lg shadow p-6 space-y-6">
            @csrf @method('PUT')

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Razón Social *</label>
                    <input type="text" name="legal_name" value="{{ old('legal_name', $client->legal_name) }}" required class="w-full border rounded-lg px-3 py-2">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">RFC *</label>
                    <input type="text" name="tax_id" value="{{ old('tax_id', $client->tax_id) }}" required class="w-full border rounded-lg px-3 py-2 font-mono uppercase">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Régimen Fiscal *</label>
                    <select name="tax_system" required class="w-full border rounded-lg px-3 py-2">
                        @foreach($taxSystems as $code => $label)
                            <option value="{{ $code }}" {{ old('tax_system', $client->tax_system) == $code ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Uso CFDI *</label>
                    <select name="cfdi_use" required class="w-full border rounded-lg px-3 py-2">
                        @foreach($cfdiUses as $code => $label)
                            <option value="{{ $code }}" {{ old('cfdi_use', $client->cfdi_use) == $code ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                    <input type="email" name="email" value="{{ old('email', $client->email) }}" class="w-full border rounded-lg px-3 py-2">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Teléfono</label>
                    <input type="text" name="phone" value="{{ old('phone', $client->phone) }}" class="w-full border rounded-lg px-3 py-2">
                </div>
            </div>

            <hr>
            <h3 class="font-semibold text-gray-800">Dirección Fiscal</h3>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Código Postal *</label>
                    <input type="text" name="address_zip" value="{{ old('address_zip', $client->address_zip) }}" required class="w-full border rounded-lg px-3 py-2">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Calle</label>
                    <input type="text" name="address_street" value="{{ old('address_street', $client->address_street) }}" class="w-full border rounded-lg px-3 py-2">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">No. Exterior</label>
                    <input type="text" name="address_exterior" value="{{ old('address_exterior', $client->address_exterior) }}" class="w-full border rounded-lg px-3 py-2">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">No. Interior</label>
                    <input type="text" name="address_interior" value="{{ old('address_interior', $client->address_interior) }}" class="w-full border rounded-lg px-3 py-2">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Colonia</label>
                    <input type="text" name="address_neighborhood" value="{{ old('address_neighborhood', $client->address_neighborhood) }}" class="w-full border rounded-lg px-3 py-2">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Ciudad</label>
                    <input type="text" name="address_city" value="{{ old('address_city', $client->address_city) }}" class="w-full border rounded-lg px-3 py-2">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Municipio</label>
                    <input type="text" name="address_municipality" value="{{ old('address_municipality', $client->address_municipality) }}" class="w-full border rounded-lg px-3 py-2">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Estado</label>
                    <input type="text" name="address_state" value="{{ old('address_state', $client->address_state) }}" class="w-full border rounded-lg px-3 py-2">
                </div>
            </div>

            <div class="flex justify-end">
                <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white font-semibold px-6 py-2.5 rounded-lg transition">
                    <i class="fas fa-save mr-2"></i> Guardar Cambios
                </button>
            </div>
        </form>
    </main>
</body>
</html>
