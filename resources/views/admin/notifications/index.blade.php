@extends('layouts.admin')
@section('title', 'Notificaciones')
@section('header', 'Notificaciones')

@section('actions')
@if($notifications->where('read_at', null)->count())
    <form action="{{ route('admin.notifications.read-all') }}" method="POST">
        @csrf
        <button type="submit" class="bg-gray-200 text-gray-700 px-4 py-2 rounded-lg hover:bg-gray-300 text-sm">
            <i class="fas fa-check-double mr-1"></i> Marcar todas como leídas
        </button>
    </form>
@endif
@endsection

@section('content')
<div class="bg-white rounded-lg shadow overflow-hidden">
    <div class="divide-y">
        @forelse($notifications as $notification)
            <div class="flex items-start gap-4 p-4 {{ $notification->isUnread() ? 'bg-blue-50' : '' }} hover:bg-gray-50">
                <div class="mt-1">
                    @if($notification->isUnread())
                        <span class="block w-2.5 h-2.5 bg-blue-500 rounded-full"></span>
                    @else
                        <span class="block w-2.5 h-2.5 bg-gray-300 rounded-full"></span>
                    @endif
                </div>
                <div class="flex-1 min-w-0">
                    <p class="font-medium text-gray-800 text-sm">{{ $notification->title }}</p>
                    <p class="text-sm text-gray-500 mt-0.5">{{ $notification->body }}</p>
                    <p class="text-xs text-gray-400 mt-1">{{ $notification->created_at->diffForHumans() }}</p>
                </div>
                <div class="flex items-center gap-2">
                    @if($notification->url)
                        <form action="{{ route('admin.notifications.read', $notification) }}" method="POST">
                            @csrf
                            <button type="submit" class="text-blue-600 hover:text-blue-800 text-sm">
                                <i class="fas fa-external-link-alt"></i> Ver
                            </button>
                        </form>
                    @elseif($notification->isUnread())
                        <form action="{{ route('admin.notifications.read', $notification) }}" method="POST">
                            @csrf
                            <button type="submit" class="text-gray-500 hover:text-gray-700 text-sm">
                                <i class="fas fa-check"></i>
                            </button>
                        </form>
                    @endif
                </div>
            </div>
        @empty
            <div class="p-8 text-center text-gray-500">
                <i class="fas fa-bell-slash text-gray-300 text-3xl mb-2"></i>
                <p>Sin notificaciones.</p>
            </div>
        @endforelse
    </div>

    <div class="p-4 border-t">
        {{ $notifications->links() }}
    </div>
</div>
@endsection
