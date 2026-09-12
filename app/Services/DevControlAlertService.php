<?php

namespace App\Services;

use App\Models\Proyecto;
use App\Models\Configuracion;
use Illuminate\Support\Facades\Mail;

class DevControlAlertService
{
    private static array $pending = [];

    private static bool $flushRegistered = false;

    public function send(string $event, string $title, ?Proyecto $project, array $details = []): void
    {
        if (! (bool) Configuracion::valor('alertas_activas', true)) {
            return;
        }

        $recipient = Configuracion::valor('correo_alertas', config('services.alerts.email'));

        if (! $recipient) {
            return;
        }

        $category = match (true) {
            str_contains($event, 'Bug') => 'bugs',
            str_contains($event, 'Incidente') => 'incidentes',
            str_contains($event, 'Tarea') => 'tareas',
            str_contains($event, 'Commit') || str_contains($event, 'GitHub') => 'commits',
            str_contains($event, 'Actualización') => 'actualizaciones',
            default => null,
        };

        if ($category && ! (bool) Configuracion::valor("alertas_{$category}", true)) {
            return;
        }

        $minimum = ['Baja' => 1, 'Media' => 2, 'Alta' => 3][Configuracion::valor('prioridad_minima', 'Baja')] ?? 1;
        $priority = $details['Prioridad'] ?? null;
        if ($priority && (['Baja' => 1, 'Media' => 2, 'Alta' => 3][$priority] ?? 0) < $minimum) {
            return;
        }

        self::$pending[$recipient][] = [
            'event' => $event,
            'projectName' => $project?->nombre ?? 'Sin proyecto asociado',
            'title' => $title,
            'details' => $details,
        ];

        if (! self::$flushRegistered) {
            self::$flushRegistered = true;
            app()->terminating(function (): void {
                $this->flush();
            });
        }
    }

    private function flush(): void
    {
        foreach (self::$pending as $recipient => $alerts) {
            if (count($alerts) === 1) {
                $alert = $alerts[0];
                $event = $alert['event'];
                $data = [
                    'event' => $event,
                    'projectName' => $alert['projectName'],
                    'title' => $alert['title'],
                    'details' => $alert['details'],
                    'alerts' => [],
                    'date' => now()->format('d/m/Y H:i'),
                    'appUrl' => config('app.url'),
                ];
            } else {
                $event = 'Resumen de actividad';
                $data = [
                    'event' => $event,
                    'projectName' => 'Múltiples proyectos',
                    'title' => count($alerts).' cambios registrados en DevControl',
                    'details' => [],
                    'alerts' => $alerts,
                    'date' => now()->format('d/m/Y H:i'),
                    'appUrl' => config('app.url'),
                ];
            }

            Mail::send('emails.devcontrol-alert', $data, function ($message) use ($recipient, $event): void {
                $message->to($recipient)
                    ->subject("[DevControl] {$event}");
            });
        }

        self::$pending = [];
    }
}
