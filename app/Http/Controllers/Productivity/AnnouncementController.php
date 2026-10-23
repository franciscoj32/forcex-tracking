<?php

namespace App\Http\Controllers\Productivity;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Services\Productivity\BoardService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/** Pizarra de oficina. Todos la ven; publica la Gerencia. */
class AnnouncementController extends Controller
{
    public function __construct(private readonly BoardService $board)
    {
    }

    public function index(Request $request): View
    {
        Gate::authorize('use-productivity');

        $this->board->ensureTodayBirthdays();

        return view('productivity.board.index', [
            'announcements' => Announcement::query()->active()->boardOrder()->with('author:id,name')->get(),
            'expired' => $request->user()->can('manage-announcements')
                ? Announcement::query()->where('tipo', Announcement::TYPE_NOTICE)->where('expira_en', '<=', now())
                    ->where('expira_en', '>=', now()->subDays(30))->latest('expira_en')->take(10)->get()
                : collect(),
            'birthdays' => $this->board->birthdaysInMonth(today()->month),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('manage-announcements');

        $data = $request->validate([
            'titulo' => ['required', 'string', 'max:160'],
            'cuerpo' => ['nullable', 'string', 'max:2000'],
            'expira' => ['nullable', 'date', 'after_or_equal:today'],
            'fijado' => ['nullable', 'boolean'],
        ], [
            'titulo.required' => 'Escribe el anuncio.',
            'expira.after_or_equal' => 'La fecha "mostrar hasta" no puede ser anterior a hoy.',
        ]);

        $this->board->publish([
            'titulo' => trim($data['titulo']),
            'cuerpo' => $data['cuerpo'] ?? null,
            'fijado' => (bool) ($data['fijado'] ?? false),
            'expira_en' => Carbon::parse($data['expira'] ?? today()->addDays(Announcement::DEFAULT_DAYS))->endOfDay(),
        ], $request->user());

        return back()->with('status', 'Anuncio publicado. El equipo recibirá un aviso.');
    }

    public function pin(Announcement $announcement): RedirectResponse
    {
        Gate::authorize('manage-announcements');

        $announcement->forceFill(['fijado' => ! $announcement->fijado])->save();

        return back()->with('status', $announcement->fijado ? 'Anuncio fijado arriba.' : 'Anuncio sin fijar.');
    }

    public function destroy(Announcement $announcement): RedirectResponse
    {
        Gate::authorize('manage-announcements');

        $announcement->delete();

        return back()->with('status', 'Anuncio retirado de la pizarra.');
    }
}
