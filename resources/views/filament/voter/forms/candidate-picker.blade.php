<div class="voter-picker-wrap" x-data="{
    profile: null,
    moveFocus(event, offset) {
        const buttons = [...event.currentTarget.querySelectorAll('.voter-candidate:not(:disabled)')];
        const index = buttons.indexOf(event.target);

        buttons[(index + offset + buttons.length) % buttons.length]?.focus();
    },
}">
    <div class="voter-picker-header">
        <span class="voter-picker-ordinal">Bidang {{ $fieldNumber }} dari {{ $fieldCount }}</span>
        <span class="voter-picker-hint">Ketuk kandidat untuk memilih. Ketuk lagi untuk membatalkan.</span>
    </div>

    <div
        role="group"
        class="voter-picker"
        aria-label="Pilih kandidat untuk {{ $fieldName }}"
        x-on:keydown.arrow-right.prevent="moveFocus($event, 1)"
        x-on:keydown.arrow-down.prevent="moveFocus($event, 1)"
        x-on:keydown.arrow-left.prevent="moveFocus($event, -1)"
        x-on:keydown.arrow-up.prevent="moveFocus($event, -1)"
    >
        @foreach ($candidates as $index => $candidate)
            @php
                $isSelected = (string) $selectedId === (string) $candidate->id;
                $isDisabled = in_array($candidate->id, $disabledIds, true);
                $photoUrl = $candidate->photoUrl();
            @endphp
            <div class="voter-candidate-wrap" style="--i: {{ $index }}">
                <button
                    type="button"
                    aria-pressed="{{ $isSelected ? 'true' : 'false' }}"
                    class="voter-candidate{{ $isSelected ? ' is-selected' : '' }}"
                    wire:click="$set('{{ $statePath }}', {{ $isSelected ? 'null' : "'".$candidate->id."'" }})"
                    @disabled($isDisabled || ! $isOpen)
                >
                    <span class="voter-candidate-media">
                        @if ($photoUrl)
                            <img src="{{ $photoUrl }}" alt="Foto {{ $candidate->name }}" class="voter-candidate-photo" loading="lazy">
                        @else
                            <span class="voter-candidate-initial" aria-hidden="true">{{ mb_substr($candidate->name, 0, 1) }}</span>
                        @endif
                        @if ($isSelected)
                            <span class="voter-candidate-stamp" aria-hidden="true">Pilihan Anda</span>
                        @elseif ($isDisabled)
                            <span class="voter-candidate-stamp is-blocked">Sudah dipilih di {{ $maxFieldsPerCandidate }} bidang lain</span>
                        @endif
                        <span class="voter-candidate-check" aria-hidden="true">
                            @if ($isSelected)
                                <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path class="voter-check-path" pathLength="1" d="M4.5 10.5l3.5 3.5 7.5-8"/></svg>
                            @endif
                        </span>
                    </span>

                    <span class="voter-candidate-name">{{ $candidate->name }}</span>

                    @if (filled($candidate->golongan))
                        <span class="voter-candidate-rank">Golongan {{ $candidate->golongan }}</span>
                    @endif
                </button>

                <button
                    type="button"
                    class="voter-candidate-profile"
                    x-on:click="profile = @js([
                        'name' => $candidate->name,
                        'photo' => $photoUrl,
                        'initial' => mb_substr($candidate->name, 0, 1),
                        'rank' => filled($candidate->golongan) ? 'Golongan '.$candidate->golongan : null,
                    ]); $refs.profileDialog.showModal()"
                >Lihat profil</button>
            </div>
        @endforeach
    </div>

    <dialog x-ref="profileDialog" class="voter-dialog voter-profile-dialog" aria-label="Profil kandidat">
        <div class="voter-profile-body">
            <div class="voter-profile-media">
                <template x-if="profile?.photo">
                    <img class="voter-candidate-photo" alt="" x-bind:src="profile.photo">
                </template>
                <template x-if="! profile?.photo">
                    <span class="voter-candidate-initial" aria-hidden="true" x-text="profile?.initial"></span>
                </template>
            </div>
            <h3 class="voter-profile-name" x-text="profile?.name"></h3>
            <p class="voter-profile-rank" x-show="profile?.rank" x-text="profile?.rank"></p>
            <button type="button" class="voter-dialog-confirm voter-profile-close" x-on:click="$refs.profileDialog.close()">Tutup</button>
        </div>
    </dialog>
</div>
