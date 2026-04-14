<?php

namespace App\Pulse\Livewire;

use App\Pulse\Recorders\ReverbEventTypes as ReverbEventTypesRecorder;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\View;
use Laravel\Pulse\Facades\Pulse;
use Laravel\Pulse\Livewire\Card;
use Laravel\Pulse\Livewire\Concerns\HasPeriod;
use Laravel\Pulse\Livewire\Concerns\RemembersQueries;
use Livewire\Attributes\Lazy;

class ReverbEventTypes extends Card
{
    use HasPeriod, RemembersQueries;

    #[Lazy]
    public function render()
    {
        [$events, $time, $runAt] = $this->remember(function () {
            $rows = Pulse::aggregateTypes(
                ['reverb_event_type:sent', 'reverb_event_type:received'],
                'count',
                $this->periodAsInterval(),
                limit: 200,
            );

            return $rows->map(fn ($row) => [
                'event' => $row->key,
                'sent' => (int) ($row->{'reverb_event_type:sent'} ?? 0),
                'received' => (int) ($row->{'reverb_event_type:received'} ?? 0),
            ])
                ->sortByDesc(fn ($row) => $row['sent'] + $row['received'])
                ->values();
        });

        return View::make('livewire.pulse.reverb-event-types', [
            'events' => $events,
            'time' => $time,
            'runAt' => $runAt,
            'config' => Config::get('pulse.recorders.'.ReverbEventTypesRecorder::class),
        ]);
    }
}
