<ol class="admin-voter-order">
    @foreach ($fields as $field)
        <li class="admin-voter-step" style="--stagger: {{ $loop->index }}">
            <span class="admin-voter-step-number">{{ $loop->iteration }}</span>
            <span class="admin-voter-step-body">
                <strong>{{ $field->name }}</strong>
                <small>Pilih satu kandidat dari {{ $candidateCount }} kandidat</small>
            </span>
        </li>
    @endforeach
</ol>

<p class="admin-voter-note">Pemilih mengisi langkah-langkah ini berurutan, dan setiap langkah wajib diisi sebelum lanjut.</p>
