<x-filament-panels::page>
    {{ $this->form }}

    @php
        $sessions = $this->getSessions();
        $otherSessionCount = $sessions->where('current', false)->count();
    @endphp

    <x-filament::section
        heading="Perangkat yang sedang masuk"
        description="Daftar sesi masuk ke panel admin yang belum kedaluwarsa. Akhiri sesi yang tidak Anda kenali."
    >
        @if ($otherSessionCount > 0)
            <x-slot name="headerEnd">
                <x-filament::button
                    color="danger"
                    size="sm"
                    wire:click="terminateOtherSessions"
                    wire:confirm="Akhiri {{ $otherSessionCount }} sesi lain? Perangkat tersebut harus masuk lagi."
                >
                    Akhiri sesi lain
                </x-filament::button>
            </x-slot>
        @endif

        <ul class="divide-y divide-gray-200">
            @foreach ($sessions as $session)
                <li class="flex items-center justify-between gap-4 py-3">
                    <div class="min-w-0">
                        <p class="flex flex-wrap items-center gap-2 font-medium text-gray-950">
                            {{ $session['device'] }}
                            @if ($session['current'])
                                <x-filament::badge color="success" size="sm">Perangkat ini</x-filament::badge>
                            @endif
                        </p>
                        <p class="text-sm text-gray-500">
                            IP {{ $session['ip'] ?? 'tidak diketahui' }} · terakhir aktif {{ $session['lastActive']->locale('id')->diffForHumans() }}
                        </p>
                    </div>

                    @unless ($session['current'])
                        <x-filament::button
                            color="gray"
                            size="sm"
                            wire:click="terminateSession(@js($session['id']))"
                            wire:confirm="Akhiri sesi ini?"
                        >
                            Akhiri
                        </x-filament::button>
                    @endunless
                </li>
            @endforeach
        </ul>
    </x-filament::section>
</x-filament-panels::page>
