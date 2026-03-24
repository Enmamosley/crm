<!DOCTYPE html>
<html lang="es">
<head>
    <title>Nuevo Ticket — {{ $client->legal_name }}</title>
    @include('buy._head')
</head>
<body class="bg-gray-50 min-h-screen">
    <header class="bg-white/80 backdrop-blur-md shadow-sm border-b sticky top-0 z-10">
        <div class="max-w-2xl mx-auto px-6 py-4 flex items-center justify-between">
            <div class="flex items-center gap-3">
                @php $logo = \App\Models\Setting::get('company_logo'); @endphp
                @if($logo)
                    <img src="{{ asset('storage/' . $logo) }}" alt="" class="h-8">
                @endif
                <div>
                    <h1 class="text-lg font-bold text-gray-900">Nuevo Ticket</h1>
                    <p class="text-xs text-gray-400">{{ $client->legal_name }}</p>
                </div>
            </div>
            <a href="{{ route('portal.tickets.index', $client->portal_token) }}"
               class="text-xs text-brand-600 hover:text-brand-800 bg-brand-50 px-3 py-1.5 rounded-lg font-medium">
                <i class="fas fa-arrow-left mr-1"></i> Mis Tickets
            </a>
        </div>
    </header>

    <main class="max-w-2xl mx-auto px-6 py-8">
        @if($errors->any())
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg mb-6">
                <ul class="list-disc pl-5 text-sm">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 animate-fade-in">
            <form action="{{ route('portal.tickets.store', $client->portal_token) }}" method="POST">
                @csrf
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Asunto *</label>
                    <input type="text" name="subject" value="{{ old('subject') }}" required
                        class="input-field" placeholder="Resumen breve del problema">
                </div>

                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Prioridad *</label>
                    <select name="priority" class="input-field">
                        <option value="low" {{ old('priority') === 'low' ? 'selected' : '' }}>Baja</option>
                        <option value="medium" {{ old('priority', 'medium') === 'medium' ? 'selected' : '' }}>Media</option>
                        <option value="high" {{ old('priority') === 'high' ? 'selected' : '' }}>Alta</option>
                        <option value="urgent" {{ old('priority') === 'urgent' ? 'selected' : '' }}>Urgente</option>
                    </select>
                </div>

                <div class="mb-6">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Descripción *</label>
                    <textarea name="description" rows="5" required class="input-field"
                        placeholder="Describe el problema o solicitud con detalle...">{{ old('description') }}</textarea>
                </div>

                <div class="flex gap-3">
                    <button type="submit" class="btn-primary px-6 py-2.5 rounded-xl text-sm">
                        <i class="fas fa-paper-plane mr-1"></i> Enviar Ticket
                    </button>
                    <a href="{{ route('portal.tickets.index', $client->portal_token) }}"
                       class="px-6 py-2.5 rounded-xl text-sm border border-gray-200 text-gray-600 hover:bg-gray-50">
                        Cancelar
                    </a>
                </div>
            </form>
        </div>
    </main>
</body>
</html>
