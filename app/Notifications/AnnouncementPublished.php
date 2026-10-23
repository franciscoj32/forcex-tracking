<?php

namespace App\Notifications;

use App\Models\Announcement;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

/** Aviso in-app: hay un anuncio nuevo en la pizarra (o un cumpleanos). */
class AnnouncementPublished extends Notification
{
    public function __construct(public readonly Announcement $announcement)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $birthday = $this->announcement->isBirthday();

        return [
            'tipo' => $birthday ? 'cumpleanos' : 'pizarra',
            'icono' => $birthday ? 'cake' : 'megaphone',
            'titulo' => $birthday ? $this->announcement->titulo : 'Nuevo en la pizarra',
            'texto' => $birthday
                ? 'Pasa a saludar 🎉'
                : Str::limit($this->announcement->titulo, 90),
            'url' => route('pizarra.index').'#anuncio-'.$this->announcement->id,
        ];
    }
}
