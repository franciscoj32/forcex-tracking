<?php

namespace App\Http\Controllers\Productivity;

use App\Http\Controllers\Controller;
use App\Services\Productivity\NotificationFeed;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;

/** Campanita: lista de avisos, consulta liviana de nuevos y marcar como leidos. */
class NotificationController extends Controller
{
    public function __construct(private readonly NotificationFeed $feed)
    {
    }

    /** Panel de la campanita. */
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('use-productivity');

        return response()->json([
            'count' => $this->feed->unreadCount($request->user()),
            'items' => $this->feed->latest($request->user()),
        ]);
    }

    /** Cada 2 minutos, mientras la pestana esta visible: cuantos hay y los nuevos. */
    public function poll(Request $request): JsonResponse
    {
        Gate::authorize('use-productivity');

        $since = rescue(fn () => filled($request->query('desde')) ? Carbon::parse((string) $request->query('desde')) : null, null, false);

        return response()->json([
            'count' => $this->feed->unreadCount($request->user()),
            'nuevos' => $this->feed->unreadSince($request->user(), $since),
            'ahora' => now()->toIso8601String(),
        ]);
    }

    /** Abre el aviso: lo marca como leido y lleva a su pantalla. */
    public function open(Request $request, string $id): RedirectResponse
    {
        Gate::authorize('use-productivity');

        $notification = $request->user()->notifications()->findOrFail($id);
        $notification->markAsRead();

        $url = $notification->data['url'] ?? route('dashboard');

        // Solo se redirige dentro del sistema.
        return str_starts_with($url, url('/')) || str_starts_with($url, '/')
            ? redirect()->to($url)
            : redirect()->route('dashboard');
    }

    public function readAll(Request $request): JsonResponse|RedirectResponse
    {
        Gate::authorize('use-productivity');

        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return $request->expectsJson() ? response()->json(['count' => 0]) : back();
    }
}
