@extends('layouts.app')
@section('title', 'Campaigns')
@section('eyebrow', 'Compliant local business discovery')

@section('content')
<section class="panel mb-4">
    <div class="panel-head">
        <div>
            <h2>Create a lead campaign</h2>
            <span class="muted">Google Places API · public business information only</span>
        </div>
    </div>
    <form class="panel-body" method="post" action="{{ route('campaigns.store') }}">
        @csrf
        <div class="row g-3">
            <div class="col-lg-4"><label class="form-label">Campaign title</label><input class="form-control" name="title" placeholder="Restaurants without websites in Abbottabad" required></div>
            <div class="col-lg-3"><label class="form-label">Service</label><select class="form-select" name="service_id" required><option value="">Select service</option>@foreach($services as $service)<option value="{{ $service->id }}">{{ $service->service_name }}</option>@endforeach</select></div>
            <div class="col-lg-2"><label class="form-label">Province</label><select class="form-select" name="province"><option>Khyber Pakhtunkhwa</option><option>Punjab</option><option>Sindh</option><option>Balochistan</option><option>Islamabad Capital Territory</option><option>Gilgit-Baltistan</option><option>Azad Kashmir</option></select></div>
            <div class="col-lg-3"><label class="form-label">City</label><input class="form-control" name="city" placeholder="Abbottabad" required></div>
            <div class="col-lg-3"><label class="form-label">Business category</label><input class="form-control" name="business_category" placeholder="Restaurants" required></div>
            <div class="col-lg-3"><label class="form-label">Search keyword</label><input class="form-control" name="keyword" placeholder="best restaurants"></div>
            <div class="col-lg-2"><label class="form-label">Minimum rating</label><input class="form-control" type="number" step=".1" min="0" max="5" name="minimum_rating" value="3.5"></div>
            <div class="col-lg-2"><label class="form-label">Minimum reviews</label><input class="form-control" type="number" name="minimum_reviews" value="10"></div>
            <div class="col-lg-2"><label class="form-label">Required leads</label><input class="form-control" type="number" name="required_leads" value="50" max="500"></div>
            <div class="col-lg-8 d-flex align-items-end gap-4">
                <label class="small"><input type="checkbox" name="only_without_website" value="1" checked> Only without website</label>
                <label class="small"><input type="checkbox" name="only_with_phone" value="1" checked> Only with phone</label>
            </div>
            <div class="col-lg-4 text-lg-end"><button class="btn btn-brand px-4">Create campaign</button></div>
        </div>
    </form>
</section>

<section class="panel">
    <div class="panel-head"><h2>Campaign history</h2><span class="muted">{{ $campaigns->total() }} total</span></div>
    <div class="table-responsive">
        <table class="table mb-0">
            <thead><tr><th>Campaign</th><th>Target</th><th>Status</th><th>Progress</th><th>Saved</th><th>Quality</th><th></th></tr></thead>
            <tbody>
            @forelse($campaigns as $campaign)
                @php($isActive = in_array($campaign->status, ['pending', 'running'], true))
                <tr>
                    <td><a href="{{ route('campaigns.show', $campaign) }}" class="business-name">{{ $campaign->title }}</a><div class="muted">{{ $campaign->service->service_name }}</div></td>
                    <td>{{ $campaign->city }}<div class="muted">{{ $campaign->business_category }}</div></td>
                    <td>
                        <span class="badge badge-soft">{{ ucfirst($campaign->status) }}</span>
                        @if($isActive)<span class="spinner-grow spinner-grow-sm text-info ms-1" title="Campaign active"></span>@endif
                    </td>
                    <td style="min-width:130px"><div class="progress"><div class="progress-bar bg-success" style="width:{{ $campaign->progress_percentage }}%"></div></div><span class="muted">{{ $campaign->progress_percentage }}%</span></td>
                    <td>{{ $campaign->total_saved }}</td>
                    <td><span class="text-success">{{ $campaign->hot_leads }} hot</span> · {{ $campaign->warm_leads }} warm</td>
                    <td>
                        <div class="d-flex gap-2">
                            @if($isActive)
                                <a class="btn btn-sm btn-outline-info" href="{{ route('campaigns.show', $campaign) }}" title="View live progress"><i class="bi bi-activity"></i></a>
                            @else
                                <form class="ajax-campaign-run" method="post" action="{{ route('campaigns.run', $campaign) }}" data-show-url="{{ route('campaigns.show', $campaign) }}">@csrf<button class="btn btn-sm btn-brand" title="Run campaign"><i class="bi bi-play-fill"></i></button></form>
                            @endif
                            <a class="btn btn-sm btn-light" href="{{ route('campaigns.show', $campaign) }}" title="Open campaign"><i class="bi bi-arrow-right"></i></a>
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="text-center py-5 text-secondary">Create your first campaign above.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="panel-body">{{ $campaigns->links() }}</div>
</section>
@endsection

@push('scripts')
<script>
document.querySelectorAll('.ajax-campaign-run').forEach((form) => {
    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        const button = form.querySelector('button');
        button.disabled = true;
        button.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';

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
