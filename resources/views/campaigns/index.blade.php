@extends('layouts.app')
@section('title', 'Campaigns')
@section('eyebrow', 'Compliant local business discovery')

@section('content')
<section class="panel">
    <div class="panel-head">
        <div><h2>Campaign history</h2><span class="muted">{{ $campaigns->total() }} total campaigns</span></div>
        <div class="d-flex align-items-center gap-2">
            <div class="btn-group" role="group" aria-label="Campaign view">
                <button id="campaign-list-button" class="btn btn-sm btn-outline-secondary active" type="button" title="List view"><i class="bi bi-list-ul"></i></button>
                <button id="campaign-grid-button" class="btn btn-sm btn-outline-secondary" type="button" title="Grid view"><i class="bi bi-grid-3x3-gap"></i></button>
            </div>
            <a class="btn btn-sm btn-brand" href="{{ route('campaigns.create') }}"><i class="bi bi-plus-lg"></i> Create campaign</a>
        </div>
    </div>
    <div id="campaign-list-view" class="table-responsive">
        <table class="table mb-0">
            <thead><tr><th>Campaign</th><th>Target</th><th>Status</th><th>Progress</th><th>Saved</th><th>Quality</th><th></th></tr></thead>
            <tbody>
            @forelse($campaigns as $campaign)
                @php($isActive = in_array($campaign->status, ['pending', 'running'], true))
                <tr>
                    <td><a href="{{ route('campaigns.show', $campaign) }}" class="business-name">{{ $campaign->title }}</a><div class="muted">{{ $campaign->service?->service_name ?: 'No service' }}</div></td>
                    <td>{{ $campaign->city }}, {{ $campaign->country }}<div class="muted">{{ $campaign->business_category }}</div></td>
                    <td><span class="badge badge-soft">{{ ucfirst($campaign->status) }}</span>@if($isActive)<span class="spinner-grow spinner-grow-sm text-info ms-1" title="Campaign active"></span>@endif</td>
                    <td style="min-width:130px"><div class="progress"><div class="progress-bar bg-success" style="width:{{ $campaign->progress_percentage }}%"></div></div><span class="muted">{{ $campaign->progress_percentage }}%</span></td>
                    <td>{{ $campaign->total_saved }}</td>
                    <td><span class="text-success">{{ $campaign->hot_leads }} hot</span> &middot; {{ $campaign->warm_leads }} warm</td>
                    <td>@include('campaigns.partials.actions', ['campaign' => $campaign, 'isActive' => $isActive])</td>
                </tr>
            @empty
                <tr><td colspan="7" class="text-center py-5 text-secondary">No campaigns yet. Create your first campaign.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div id="campaign-grid-view" class="campaign-grid panel-body d-none">
        @forelse($campaigns as $campaign)
            @php($isActive = in_array($campaign->status, ['pending', 'running'], true))
            <article class="campaign-card">
                <div class="d-flex justify-content-between gap-3"><div><a href="{{ route('campaigns.show', $campaign) }}" class="business-name">{{ $campaign->title }}</a><div class="muted mt-1">{{ $campaign->service?->service_name ?: 'No service' }}</div></div><span class="badge badge-soft align-self-start">{{ ucfirst($campaign->status) }}</span></div>
                <div class="campaign-card-target"><i class="bi bi-geo-alt"></i><span>{{ $campaign->city }}, {{ $campaign->country }}<small>{{ $campaign->business_category }}</small></span></div>
                <div class="d-flex justify-content-between small mb-1"><span>Progress</span><strong>{{ $campaign->progress_percentage }}%</strong></div>
                <div class="progress mb-3"><div class="progress-bar bg-success" style="width:{{ $campaign->progress_percentage }}%"></div></div>
                <div class="campaign-card-stats"><span><strong>{{ $campaign->total_saved }}</strong> saved</span><span><strong>{{ $campaign->hot_leads }}</strong> hot</span><span><strong>{{ $campaign->warm_leads }}</strong> warm</span></div>
                <div class="mt-3">@include('campaigns.partials.actions', ['campaign' => $campaign, 'isActive' => $isActive])</div>
            </article>
        @empty
            <div class="text-center py-5 text-secondary">No campaigns yet. Create your first campaign.</div>
        @endforelse
    </div>
    <div class="panel-body border-top">{{ $campaigns->links() }}</div>
</section>
@endsection

@push('scripts')
<script>
const listView = document.getElementById('campaign-list-view');
const gridView = document.getElementById('campaign-grid-view');
const listButton = document.getElementById('campaign-list-button');
const gridButton = document.getElementById('campaign-grid-button');
const setCampaignView = (view) => {
    const useGrid = view === 'grid';
    listView.classList.toggle('d-none', useGrid);
    gridView.classList.toggle('d-none', !useGrid);
    listButton.classList.toggle('active', !useGrid);
    gridButton.classList.toggle('active', useGrid);
    localStorage.setItem('campaign-view', useGrid ? 'grid' : 'list');
};
listButton.addEventListener('click', () => setCampaignView('list'));
gridButton.addEventListener('click', () => setCampaignView('grid'));
setCampaignView(localStorage.getItem('campaign-view') || 'list');

document.querySelectorAll('.ajax-campaign-run').forEach((form) => {
    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        const button = form.querySelector('button');
        button.disabled = true;
        button.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
        try {
            const response = await fetch(form.action, {method: 'POST', headers: {'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content}, body: new FormData(form)});
            const data = await response.json();
            if (!response.ok) throw new Error(data.message || 'Campaign could not be started.');
            window.location.assign(data.show_url || form.dataset.showUrl);
        } catch (error) {
            button.disabled = false;
            button.innerHTML = '<i class="bi bi-play-fill"></i>';
            window.alert(error.message);
        }
    });
});
</script>
@endpush
