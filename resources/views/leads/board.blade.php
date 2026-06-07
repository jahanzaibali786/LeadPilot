@extends('layouts.app')
@section('title', 'Sales pipeline')
@section('eyebrow', 'Drag leads through your outreach workflow')

@section('content')
<section class="panel mb-4">
    <form class="panel-body filter-bar lead-filter-form" method="get">
        <input class="form-control" name="search" value="{{ request('search') }}" placeholder="Search this board">
        <select class="form-select auto-submit-filter" name="campaign_id">
            <option value="">All campaigns</option>
            @foreach($campaigns as $campaign)
                <option value="{{ $campaign->id }}" @selected(request('campaign_id') == $campaign->id)>{{ $campaign->title }}</option>
            @endforeach
        </select>
        <input class="form-control" name="city" value="{{ request('city') }}" placeholder="City">
        <input class="form-control" name="category" value="{{ request('category') }}" placeholder="Category">
        <select class="form-select auto-submit-filter" name="lead_quality">
            <option value="">All quality</option>
            @foreach(['Hot', 'Warm', 'Cold'] as $quality)
                <option @selected(request('lead_quality') === $quality)>{{ $quality }}</option>
            @endforeach
        </select>
        <button class="btn btn-brand">Apply</button>
    </form>
    <div class="px-4 pb-3 d-flex align-items-center gap-2">
        <button id="global-unique-button" type="button" class="btn btn-sm btn-outline-success">
            <i class="bi bi-fingerprint"></i> Unique all stages
        </button>
        <span id="unique-filter-note" class="muted">Shows the first card for each Place ID, phone, or business and city.</span>
    </div>
</section>

<div class="board">
@foreach($columns as $status => $leads)
    <section class="board-column" data-status="{{ $status }}">
        <div class="column-head">
            <span>{{ $status }}</span>
            <div class="d-flex align-items-center gap-2">
                <button type="button" class="stage-unique-button" title="Show unique leads in {{ $status }}">
                    <i class="bi bi-fingerprint"></i> Unique
                </button>
                <span class="column-count">{{ $leads->count() }}</span>
            </div>
        </div>
        <div class="card-list" data-status="{{ $status }}">
        @foreach($leads as $lead)
            <article class="lead-card" data-id="{{ $lead->id }}" data-unique-key="{{ $lead->uniqueIdentity() }}">
                <div class="d-flex justify-content-between gap-2">
                    <div>
                        <h3>{{ $lead->business_name }}</h3>
                        <div class="meta">{{ $lead->business_category }} · {{ $lead->city }}</div>
                    </div>
                    <strong class="text-success">{{ $lead->lead_score }}</strong>
                </div>
                <div class="card-badges">
                    <span class="badge quality-{{ $lead->lead_quality }}">{{ $lead->lead_quality }}</span>
                    @unless($lead->has_website)<span class="badge bg-danger-subtle text-danger">No website</span>@endunless
                    @if($lead->phone)<span class="badge bg-info-subtle text-info-emphasis">Has phone</span>@endif
                </div>
                <div class="meta mb-2">★ {{ $lead->rating ?: '—' }} · {{ $lead->total_reviews }} reviews</div>
                @if($lead->follow_up_date)<div class="meta text-warning-emphasis mb-2"><i class="bi bi-calendar-event"></i> {{ $lead->follow_up_date->format('d M Y') }}</div>@endif
                @if($lead->notes)<p class="meta mb-2">{{ \Illuminate\Support\Str::limit($lead->notes, 70) }}</p>@endif
                <div class="card-actions">
                    <a href="{{ route('leads.show', $lead) }}" title="Open"><i class="bi bi-eye"></i></a>
                    @if($lead->google_maps_url)<a href="{{ $lead->google_maps_url }}" target="_blank" title="Maps"><i class="bi bi-geo-alt"></i></a>@endif
                    @if($lead->facebook_url)<a href="{{ $lead->facebook_url }}" target="_blank" rel="noopener" title="Facebook"><i class="bi bi-facebook"></i></a>@endif
                    @if($lead->instagram_url)<a href="{{ $lead->instagram_url }}" target="_blank" rel="noopener" title="Instagram"><i class="bi bi-instagram"></i></a>@endif
                    @if($lead->linkedin_url)<a href="{{ $lead->linkedin_url }}" target="_blank" rel="noopener" title="LinkedIn"><i class="bi bi-linkedin"></i></a>@endif
                    @if($lead->whatsapp_message_english)<button type="button" title="Copy message" onclick='navigator.clipboard.writeText(@json($lead->whatsapp_message_english))'><i class="bi bi-whatsapp"></i></button>@endif
                </div>
            </article>
        @endforeach
        </div>
    </section>
@endforeach
</div>

<div class="position-fixed bottom-0 end-0 p-3">
    <div id="boardToast" class="toast text-bg-success"><div class="toast-body">Lead status updated.</div></div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js"></script>
<script>
document.querySelectorAll('.auto-submit-filter').forEach((select) => {
    select.addEventListener('change', () => select.form.requestSubmit());
});

const token = document.querySelector('meta[name=csrf-token]').content;
const toast = new bootstrap.Toast('#boardToast');
const globalUniqueButton = document.getElementById('global-unique-button');
let globalUnique = false;

function updateColumnCounts() {
    document.querySelectorAll('.board-column').forEach(column => {
        const total = column.querySelectorAll('.lead-card').length;
        const visible = [...column.querySelectorAll('.lead-card')].filter(card => !card.hidden).length;
        column.querySelector('.column-count').textContent = visible === total ? total : `${visible}/${total}`;
    });
}

function applyUniqueFilters() {
    const cards = [...document.querySelectorAll('.lead-card')];
    cards.forEach(card => card.hidden = false);

    if (globalUnique) {
        const seen = new Set();
        cards.forEach(card => {
            if (seen.has(card.dataset.uniqueKey)) card.hidden = true;
            else seen.add(card.dataset.uniqueKey);
        });
    } else {
        document.querySelectorAll('.board-column.unique-stage').forEach(column => {
            const seen = new Set();
            column.querySelectorAll('.lead-card').forEach(card => {
                if (seen.has(card.dataset.uniqueKey)) card.hidden = true;
                else seen.add(card.dataset.uniqueKey);
            });
        });
    }

    updateColumnCounts();
}

globalUniqueButton.addEventListener('click', () => {
    globalUnique = !globalUnique;
    globalUniqueButton.classList.toggle('active', globalUnique);
    globalUniqueButton.innerHTML = globalUnique
        ? '<i class="bi bi-check2-circle"></i> Showing unique all stages'
        : '<i class="bi bi-fingerprint"></i> Unique all stages';
    applyUniqueFilters();
});

document.querySelectorAll('.stage-unique-button').forEach(button => {
    button.addEventListener('click', () => {
        const column = button.closest('.board-column');
        column.classList.toggle('unique-stage');
        const active = column.classList.contains('unique-stage');
        button.classList.toggle('active', active);
        button.innerHTML = active
            ? '<i class="bi bi-check2"></i> Unique'
            : '<i class="bi bi-fingerprint"></i> Unique';
        applyUniqueFilters();
    });
});

document.querySelectorAll('.card-list').forEach(list => new Sortable(list, {
    group: 'leads',
    animation: 160,
    ghostClass: 'sortable-ghost',
    onEnd: async event => {
        const card = event.item;
        const status = event.to.dataset.status;
        const oldStatus = event.from.dataset.status;
        const oldIndex = event.oldIndex;

        try {
            const response = await fetch(`/leads/${card.dataset.id}/status`, {
                method: 'PATCH',
                headers: {'Content-Type': 'application/json', 'X-CSRF-TOKEN': token, 'Accept': 'application/json'},
                body: JSON.stringify({status, board_order: event.newIndex})
            });
            if (!response.ok) throw new Error();
            applyUniqueFilters();
            toast.show();
            saveOrder();
        } catch (_) {
            event.from.insertBefore(card, event.from.children[oldIndex] || null);
            alert(`Status update failed. The card was returned to ${oldStatus}.`);
        }
    }
}));

async function saveOrder() {
    const columns = [...document.querySelectorAll('.card-list')].map(column => ({
        status: column.dataset.status,
        leads: [...column.children].map((lead, order) => ({id: +lead.dataset.id, order}))
    }));
    await fetch('{{ route('leads.board.reorder') }}', {
        method: 'POST',
        headers: {'Content-Type': 'application/json', 'X-CSRF-TOKEN': token, 'Accept': 'application/json'},
        body: JSON.stringify({columns})
    });
}
</script>
@endpush
