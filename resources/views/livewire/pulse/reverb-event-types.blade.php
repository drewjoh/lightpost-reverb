<x-pulse::card :cols="$cols" :rows="$rows" :class="$class">
    <x-pulse::card-header
        name="Reverb Event Types"
        title="Time: {{ number_format($time) }}ms; Run at: {{ $runAt }};"
        details="past {{ $this->periodForHumans() }}"
    />

    <x-pulse::scroll :expand="$expand" wire:poll.5s="">
        @if ($events->isEmpty())
            <x-pulse::no-results />
        @else
            @php
                $scale = 1 / $config['sample_rate'];
                $approx = $config['sample_rate'] < 1 ? '~' : '';
            @endphp
            <x-pulse::table>
                <x-pulse::thead>
                    <tr>
                        <x-pulse::th>Event</x-pulse::th>
                        <x-pulse::th class="text-right">Sent</x-pulse::th>
                        <x-pulse::th class="text-right">Received</x-pulse::th>
                    </tr>
                </x-pulse::thead>
                <tbody>
                    @foreach ($events as $row)
                        <tr wire:key="reverb-event:{{ $row['event'] }}" class="h-8">
                            <x-pulse::td>
                                <code class="text-xs">{{ $row['event'] }}</code>
                            </x-pulse::td>
                            <x-pulse::td class="text-right tabular-nums">
                                {{ $approx }}{{ number_format($row['sent'] * $scale) }}
                            </x-pulse::td>
                            <x-pulse::td class="text-right tabular-nums">
                                {{ $approx }}{{ number_format($row['received'] * $scale) }}
                            </x-pulse::td>
                        </tr>
                    @endforeach
                </tbody>
            </x-pulse::table>
        @endif
    </x-pulse::scroll>
</x-pulse::card>
