<section class="admin-tally" aria-label="Ringkasan pilihan sesuai filter" wire:loading.class="is-loading" wire:target="tableFilters,tableSearch,activeTab">
    <p class="admin-tally-summary">
        <strong>{{ $tally['ballots'] }}</strong> suara sesuai filter
    </p>

    <div class="admin-tally-grid">
        @foreach ($tally['fields'] as $field)
            <article class="admin-tally-field" style="--stagger: {{ $loop->index }}">
                <header class="admin-tally-head">
                    <h3>{{ $field['name'] }}</h3>
                    <span>{{ $field['picks'] }} pilihan</span>
                </header>

                <div class="admin-tally-rows">
                    @forelse ($field['candidates'] as $candidate)
                        <div class="admin-tally-row {{ $loop->first && $candidate['votes'] > 0 ? 'is-leading' : '' }}">
                            <span class="admin-tally-name">{{ $candidate['name'] }}</span>
                            <span class="admin-tally-count">{{ $candidate['votes'] }} <span class="admin-tally-share">· {{ $candidate['share'] }}%</span></span>
                            <span class="admin-tally-bar" aria-hidden="true"><span style="width: {{ $candidate['share'] }}%"></span></span>
                        </div>
                    @empty
                        <p class="admin-tally-empty">Belum ada kandidat.</p>
                    @endforelse
                </div>
            </article>
        @endforeach
    </div>
</section>
