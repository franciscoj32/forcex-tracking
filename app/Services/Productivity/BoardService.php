<?php

namespace App\Services\Productivity;

use App\Models\Announcement;
use App\Models\AppSetting;
use App\Models\User;
use App\Notifications\AnnouncementPublished;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;

/** Pizarra de oficina: avisos de la Gerencia y cumpleanos del equipo. */
class BoardService
{
    public function publish(array $data, User $author): Announcement
    {
        $announcement = new Announcement($data + ['publicado_en' => now()]);
        $announcement->tipo = Announcement::TYPE_NOTICE;
        $announcement->autor_id = $author->id;
        $announcement->save();

        $this->notifyTeam($announcement, except: [$author->id]);

        return $announcement;
    }

    /**
     * Crea el saludo de cada cumpleanero de hoy y avisa al resto del equipo.
     * No duplica: si ya existe el anuncio de esa persona y ese dia, lo salta.
     *
     * @return Collection<int, Announcement> los anuncios creados
     */
    public function postBirthdays(?Carbon $day = null): Collection
    {
        $day = ($day ?? today())->copy()->startOfDay();
        $created = collect();

        $this->team()
            ->filter(fn (User $user) => $user->isBirthdayOn($day))
            ->each(function (User $user) use ($day, $created) {
                $exists = Announcement::query()
                    ->where('tipo', Announcement::TYPE_BIRTHDAY)
                    ->where('usuario_id', $user->id)
                    ->whereDate('fecha_evento', $day)
                    ->exists();

                if ($exists) {
                    return;
                }

                $announcement = new Announcement([
                    'titulo' => "¡Hoy es el cumpleaños de {$user->name}!",
                    'cuerpo' => "Hoy {$user->firstName()} está de cumpleaños. Pasa a saludarle 🎂",
                    'publicado_en' => $day,
                    'expira_en' => $day->copy()->endOfDay(),
                ]);
                $announcement->tipo = Announcement::TYPE_BIRTHDAY;
                $announcement->usuario_id = $user->id;
                $announcement->fecha_evento = $day->toDateString();

                try {
                    $announcement->save();
                } catch (UniqueConstraintViolationException) {
                    return; // Otro proceso lo creo en el mismo instante.
                }

                $this->notifyTeam($announcement, except: [$user->id]);
                $created->push($announcement);
            });

        return $created;
    }

    /**
     * Respaldo por si el cron no corrio: la primera pagina del dia publica los
     * cumpleanos. Despues es una sola lectura de cache por pagina.
     */
    public function ensureTodayBirthdays(): void
    {
        if (! AppSetting::moduleEnabled('productividad')) {
            return;
        }

        if (Cache::add('forcex:cumpleanos:'.today()->toDateString(), true, now()->endOfDay())) {
            $this->postBirthdays();
        }
    }

    /**
     * Cumpleanos de un mes, sin el ano (para el bloque "Cumpleanos del mes").
     *
     * @return Collection<int, User>
     */
    public function birthdaysInMonth(int $month): Collection
    {
        return $this->team()
            ->filter(fn (User $user) => $user->fecha_nacimiento && (int) $user->fecha_nacimiento->month === $month)
            ->sortBy(fn (User $user) => $user->fecha_nacimiento->day)
            ->values();
    }

    /** El equipo visible: internos activos (la cuenta de Desarrollo no). */
    private function team(): Collection
    {
        return User::query()
            ->whereIn('role', User::INTERNAL_ROLES)
            ->where('estado', User::ESTADO_ACTIVO)
            ->get(['id', 'name', 'role', 'estado', 'fecha_nacimiento']);
    }

    /** Aviso en la campanita de todo el equipo (y de Desarrollo), menos los excluidos. */
    private function notifyTeam(Announcement $announcement, array $except = []): void
    {
        $recipients = User::query()
            ->where(fn ($query) => $query->whereIn('role', User::INTERNAL_ROLES)->orWhere('role', User::ROLE_DEVELOPER))
            ->where('estado', User::ESTADO_ACTIVO)
            ->whereNotIn('id', $except)
            ->get();

        Notification::send($recipients, new AnnouncementPublished($announcement));
    }
}
