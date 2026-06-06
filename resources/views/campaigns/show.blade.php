@extends('layouts.app')
@section('title', $campaign->title)
@section('eyebrow', 'Campaign progress and results')

@section('content')
<div id="campaign-monitor" data-active="{{ in_array($campaign->status, ['pending', 'running']) ? '1' : '0' }}">
    <div class="row g-4 mb-4">
        <div class="col-lg-8">
            <section class="panel h-100">
                <div class="panel-body">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <div>
                            <strong id="campaign-status">{{ ucfirst($campaign->status) }}</strong>
                            <span id="live-indicator" class="badge bg-info-subtle text-info-emphasis ms-2 {{ in_array($campaign->status, ['pending', 'running']) ? '' : 'd-none' }}">
                                <span class="spinner-grow spinner-grow-sm me-1"></span> Live
                            </span>
                        </div>
                        <strong id="campaign-percent">{{ $campaign->progress_percentage }}%</strong>
                    </div>

                    <div class="progress campaign-progress mb-3" role="progressbar" aria-label="Campaign progress">
                        <div id="campaign-progress-bar"
                            class="progress-bar progress-bar-striped {{ in_array($campaign->status, ['pending', 'running']) ? 'progress-bar-animated' : '' }}"
                            style="width: {{ $campaign->progress_percentage }}%"
                            aria-valuenow="{{ $campaign->progress_percentage }}" aria-valuemin="0" aria-valuemax="100"></div>
                    </div>

                    <p id="progress-message" class="muted mb-4">
                        @if($campaign->status === 'pending')
                            Preparing the background campaign...
                        @elseif($campaign->status === 'running')
                            Searching and qualifying public business listings...
                        @elseif($campaign->status === 'completed')
                            Campaign completed successfully.
                        @elseif($campaign->status === 'failed')
                            Campaign stopped because of an error.
                        @elseif($campaign->status === 'draft')
                            Ready to run. This campaign is not active.
                        @else
                            Campaign is {{ $campaign->status }}.
                        @endif
                    </p>

                    <div class="row text-center g-3">
                        @foreach([
                            ['found', 'Found', $campaign->total_found],
                            ['saved', 'Saved', $campaign->total_saved],
                            ['duplicates', 'Duplicates', $campaign->duplicates_removed],
                            ['hot', 'Hot', $campaign->hot_leads],
                            ['warm', 'Warm', $campaign->warm_leads],
                            ['failed', 'Failed calls', $campaign->failed_requests],
                        ] as [$key, $label, $value])
                            <div class="col-6 col-md-4 col-xl-2">
                                <strong id="metric-{{ $key }}" class="h4 d-block animated-counter" data-value="{{ $value }}">{{ $value }}</strong>
                                <span class="muted">{{ $label }}</span>
                            </div>
                        @endforeach
                    </div>

                    <div id="campaign-error" class="alert alert-danger mt-4 mb-0 {{ $campaign->failure_reason ? '' : 'd-none' }}">
                        {{ $campaign->failure_reason }}
                    </div>
                </div>
            </section>
        </div>

        <div class="col-lg-4">
            <section class="panel h-100">
                <div class="panel-body">
                    <p class="muted mb-1">Target</p>
                    <strong>{{ $campaign->business_category }} · {{ $campaign->city }}</strong>
                    <hr>
                    <p class="muted mb-1">Qualification</p>
                    <span class="small">Rating {{ $campaign->minimum_rating }}+ · Reviews {{ $campaign->minimum_reviews }}+</span>

                    <div class="d-flex flex-wrap gap-2 mt-4">
                        <div id="active-campaign-actions" class="d-flex gap-2 {{ in_array($campaign->status, ['pending', 'running']) ? '' : 'd-none' }}">
                            <a class="btn btn-outline-info disabled" aria-disabled="true"><span class="spinner-border spinner-border-sm me-1"></span> Running</a>
                            <form method="post" action="{{ route('campaigns.cancel', $campaign) }}">
                                @csrf
                                <button class="btn btn-outline-danger">Cancel</button>
                            </form>
                        </div>
                        <div id="idle-campaign-actions" class="{{ in_array($campaign->status, ['pending', 'running']) ? 'd-none' : '' }}">
                            <form id="run-campaign-form" method="post" action="{{ route('campaigns.run', $campaign) }}">
                                @csrf
                                <button id="run-campaign-button" class="btn btn-brand">
                                    <i class="bi bi-play-fill"></i> Run campaign
                                </button>
                            </form>
                        </div>

                        <a href="{{ route('leads.index', ['campaign_id' => $campaign->id]) }}" class="btn btn-outline-secondary">View leads</a>
                    </div>
                </div>
            </section>
        </div>
    </div>

    <section class="panel">
        <div class="panel-head">
            <div>
                <h2>Collected leads</h2>
                <span class="muted">This list refreshes when the campaign completes.</span>
            </div>
            <a href="{{ route('exports.leads', ['campaign_id' => $campaign->id]) }}" class="btn btn-sm btn-outline-success">
                <i class="bi bi-file-earmark-excel"></i> Export
            </a>
        </div>
        <div class="table-responsive">
            <table class="table mb-0">
                <thead><tr><th>Business</th><th>Phone</th><th>Rating</th><th>Website</th><th>Score</th><th>Status</th></tr></thead>
                <tbody>
                @forelse($leads as $lead)
                    <tr>
                        <td><a class="business-name" href="{{ route('leads.show', $lead) }}">{{ $lead->business_name }}</a><div class="muted">{{ $lead->business_category }}</div></td>
                        <td>{{ $lead->formatted_phone }}</td>
                        <td>★ {{ $lead->rating }} ({{ $lead->total_reviews }})</td>
                        <td>{{ $lead->website_status }}</td>
                        <td><span class="badge quality-{{ $lead->lead_quality }}">{{ $lead->lead_score }} · {{ $lead->lead_quality }}</span></td>
                        <td>{{ $lead->board_status }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center py-5 text-secondary">Run this campaign to collect compliant public business data.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="panel-body">{{ $leads->links() }}</div>
        <div id="lead-refresh-note" class="panel-body pt-0 d-none">
            New leads are ready. <a href="{{ route('campaigns.show', $campaign) }}">Refresh this list</a>.
        </div>
    </section>
</div>
@endsection

@push('scripts')
<script>
(() => {
    const monitor = document.getElementById('campaign-monitor');
    if (!monitor) return;

    const statusUrl = @json(route('campaigns.status', $campaign));
    const terminalStatuses = ['completed', 'failed', 'cancelled'];
    let stopped = monitor.dataset.active !== '1';
    let pollTimer = null;

    const setText = (id, value) => {
        const element = document.getElementById(id);
        if (element) element.textContent = value ?? 0;
    };

    const animateCounter = (id, nextValue) => {
        const element = document.getElementById(id);
        if (!element) return;

        const startValue = Number(element.dataset.value ?? element.textContent) || 0;
        const endValue = Number(nextValue) || 0;
        if (startValue === endValue) return;

        const direction = endValue > startValue ? 'counter-up' : 'counter-down';
        const startedAt = performance.now();
        const duration = Math.min(900, 350 + Math.abs(endValue - startValue) * 80);

        element.classList.remove('counter-up', 'counter-down');
        void element.offsetWidth;
        element.classList.add(direction);

        const frame = (now) => {
            const progress = Math.min(1, (now - startedAt) / duration);
            const eased = 1 - Math.pow(1 - progress, 3);
            element.textContent = Math.round(startValue + (endValue - startValue) * eased);
            if (progress < 1) requestAnimationFrame(frame);
            else {
                element.textContent = endValue;
                element.dataset.value = endValue;
            }
        };

        requestAnimationFrame(frame);
    };

    const toggleCampaignActions = (active) => {
        document.getElementById('active-campaign-actions')?.classList.toggle('d-none', !active);
        document.getElementById('idle-campaign-actions')?.classList.toggle('d-none', active);
    };

    const applyStatus = (data) => {
        setText('campaign-status', data.status_label);
        setText('campaign-percent', `${data.progress_percentage}%`);
        animateCounter('metric-found', data.total_found);
        animateCounter('metric-saved', data.total_saved);
        animateCounter('metric-duplicates', data.duplicates_removed);
        animateCounter('metric-hot', data.hot_leads);
        animateCounter('metric-warm', data.warm_leads);
        animateCounter('metric-failed', data.failed_requests);

        const bar = document.getElementById('campaign-progress-bar');
        bar.style.width = `${data.progress_percentage}%`;
        bar.setAttribute('aria-valuenow', data.progress_percentage);

        const messages = {
            pending: 'Preparing the background campaign...',
            running: 'Searching Google Places and qualifying public business listings...',
            completed: 'Campaign completed successfully.',
            failed: 'Campaign stopped because of an error.',
            cancelled: 'Campaign was cancelled.'
        };
        setText('progress-message', messages[data.status] || `Campaign is ${data.status}.`);

        const errorBox = document.getElementById('campaign-error');
        if (data.failure_reason) {
            errorBox.textContent = data.failure_reason;
            errorBox.classList.remove('d-none');
        } else {
            errorBox.classList.add('d-none');
        }

        const active = !terminalStatuses.includes(data.status);
        toggleCampaignActions(active);
        document.getElementById('live-indicator')?.classList.toggle('d-none', !active);
        bar.classList.toggle('progress-bar-animated', active);

        if (!active) {
            stopped = true;
            monitor.dataset.active = '0';
            const runButton = document.getElementById('run-campaign-button');
            if (runButton) {
                runButton.disabled = false;
                runButton.innerHTML = '<i class="bi bi-play-fill"></i> Run campaign';
            }
            if (data.status === 'completed') {
                document.getElementById('lead-refresh-note')?.classList.remove('d-none');
            }
        }
    };

    const schedulePoll = () => {
        window.clearTimeout(pollTimer);
        pollTimer = window.setTimeout(poll, 1500);
    };

    const poll = async () => {
        if (stopped || document.hidden) return;
        window.clearTimeout(pollTimer);

        try {
            const response = await fetch(statusUrl, {
                headers: {'Accept': 'application/json'},
                cache: 'no-store'
            });
            if (!response.ok) throw new Error('Unable to read campaign status.');
            applyStatus(await response.json());
        } catch (error) {
            setText('progress-message', 'Live update was interrupted. Retrying automatically...');
        }

        if (!stopped) schedulePoll();
    };

    document.addEventListener('visibilitychange', () => {
        if (!document.hidden && !stopped) poll();
    });

    document.getElementById('run-campaign-form')?.addEventListener('submit', async (event) => {
        event.preventDefault();
        const form = event.currentTarget;
        const button = document.getElementById('run-campaign-button');
        button.disabled = true;
        button.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Starting...';

        try {
            const response = await fetch(form.action, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                },
                body: new FormData(form)
            });
            const data = await response.json();
            if (!response.ok) throw new Error(data.message || 'Campaign could not be started.');

            stopped = false;
            monitor.dataset.active = '1';
            document.getElementById('lead-refresh-note')?.classList.add('d-none');
            applyStatus(data.campaign);
            poll();
        } catch (error) {
            button.disabled = false;
            button.innerHTML = '<i class="bi bi-play-fill"></i> Run campaign';
            const errorBox = document.getElementById('campaign-error');
            errorBox.textContent = error.message;
            errorBox.classList.remove('d-none');
        }
    });

    if (!stopped) poll();
})();
</script>
@endpush
