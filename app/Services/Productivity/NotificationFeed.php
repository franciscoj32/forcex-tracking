<?php

namespace App\Services\Productivity;

use App\Models\User;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Carbon;

/** Datos de la campanita y de los avisos de la esquina. */
class NotificationFeed
{
    public function unreadCount(User $user): int
    {
        return $user->unreadNotifications()->count();
    }

    /** Ultimos avisos (leidos y sin leer) para el panel de la campanita. */
    public function latest(User $user, int $limit = 15): array
    {
        return $user->notifications()->latest()->take($limit)->get()
            ->map(fn (DatabaseNotification $notification) => $this->present($notification))
            ->all();
    }

    /** Sin leer creados despues de una fecha (para los avisos de la esquina). */
    public function unreadSince(User $user, ?Carbon $since, int $limit = 3): array
    {
        return $user->unreadNotifications()
            ->when($since, fn ($query) => $query->where('created_at', '>', $since))
            ->latest()
            ->take($limit)
            ->get()
            ->reverse()
            ->values()
            ->map(fn (DatabaseNotification $notification) => $this->present($notification))
            ->all();
    }

    public function present(DatabaseNotification $notification): array
    {
        $data = $notification->data;

        return [
            'id' => $notification->id,
            'tipo' => $data['tipo'] ?? 'aviso',
            'titulo' => $data['titulo'] ?? 'Aviso',
            'texto' => $data['texto'] ?? '',
            'url' => route('avisos.abrir', $notification->id),
            'leido' => $notification->read_at !== null,
            'hace' => $notification->created_at?->locale('es')->diffForHumans(short: true),
            'creado' => $notification->created_at?->toIso8601String(),
        ];
    }
}
