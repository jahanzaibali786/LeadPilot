@extends('layouts.app')
@section('title', 'Settings')
@section('eyebrow', 'Defaults, limits and integrations')

@section('content')
<form method="post" action="{{ route('settings.update') }}">
    @csrf
    @method('put')
    <div class="row g-4">
        <div class="col-lg-7">
            <section class="panel h-100">
                <div class="panel-head"><h2>Campaign defaults</h2></div>
                <div class="panel-body">
                    <div class="row g-3">
                        <div class="col-md-6"><label class="form-label">Default city</label><input class="form-control" name="default_city" value="{{ old('default_city', $settings['default_city'] ?? '') }}"></div>
                        <div class="col-md-3"><label class="form-label">Minimum rating</label><input class="form-control" type="number" step=".1" min="0" max="5" name="default_minimum_rating" value="{{ old('default_minimum_rating', $settings['default_minimum_rating'] ?? 3.5) }}"></div>
                        <div class="col-md-3"><label class="form-label">Minimum reviews</label><input class="form-control" type="number" min="0" name="default_minimum_reviews" value="{{ old('default_minimum_reviews', $settings['default_minimum_reviews'] ?? 10) }}"></div>
                        <div class="col-md-6"><label class="form-label">Maximum leads per campaign</label><input class="form-control" type="number" min="1" max="500" name="maximum_leads_per_campaign" value="{{ old('maximum_leads_per_campaign', $settings['maximum_leads_per_campaign'] ?? 200) }}"></div>
                        <div class="col-md-6 d-flex align-items-end gap-3"><label><input type="checkbox" name="ai_scoring" value="1" @checked(old('ai_scoring', $settings['ai_scoring'] ?? 1))> AI scoring</label><label><input type="checkbox" name="auto_pitch_generation" value="1" @checked(old('auto_pitch_generation', $settings['auto_pitch_generation'] ?? 0))> Auto pitch</label></div>
                    </div>
                </div>
            </section>
        </div>

        <div class="col-lg-5">
            <section class="panel h-100">
                <div class="panel-head"><h2>Google Places API</h2></div>
                <div class="panel-body">
                    <div class="alert {{ $googlePlacesMaskedKey ? 'alert-success' : 'alert-warning' }} small">
                        <strong>Currently using:</strong> {{ $googlePlacesSource }}<br>
                        <span>{{ $googlePlacesMaskedKey ?: 'No API key configured' }}</span>
                    </div>
                    <label class="form-label">{{ $hasSavedGooglePlacesKey ? 'Replace saved API key' : 'Save API key' }}</label>
                    <div class="input-group">
                        <input id="google-places-api-key" class="form-control" type="password" name="google_places_api_key" autocomplete="new-password" placeholder="Leave blank to keep the current key">
                        <button class="btn btn-outline-secondary" type="button" data-toggle-secret="google-places-api-key" aria-label="Show or hide API key"><i class="bi bi-eye"></i></button>
                    </div>
                    <div class="form-text">Stored encrypted for your account and used immediately.</div>
                    @if($hasSavedGooglePlacesKey)
                        <label class="small text-danger d-block mt-3"><input type="checkbox" name="remove_google_places_api_key" value="1"> Remove saved key and fall back to <code>.env</code></label>
                    @endif
                </div>
            </section>
        </div>

        <div class="col-12">
            <section class="panel">
                <div class="panel-head"><div><h2>AI integrations</h2><p class="muted mb-0">Choose which provider powers lead analysis and outreach generation.</p></div></div>
                <div class="panel-body">
                    <div class="row g-3 mb-4">
                        <div class="col-md-6 col-xl-4">
                            <label class="form-label">Active provider</label>
                            <select class="form-select" name="ai_provider">
                                @foreach($aiProviders as $provider => $status)
                                    <option value="{{ $provider }}" @selected(old('ai_provider', $selectedAiProvider) === $provider)>{{ $status['label'] }}{{ $status['configured'] ? ' - configured' : '' }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="row g-4">
                        @foreach($aiProviders as $provider => $status)
                            @php($definition = \App\Services\AiCredentialService::PROVIDERS[$provider])
                            <div class="col-lg-6">
                                <div class="border rounded-3 p-3 h-100">
                                    <div class="d-flex justify-content-between gap-3 mb-3">
                                        <div><strong>{{ $status['label'] }}</strong><div class="small muted">{{ $status['source'] }}</div></div>
                                        <span class="badge {{ $status['configured'] ? 'text-bg-success' : 'text-bg-secondary' }}">{{ $status['configured'] ? 'Configured' : 'Not configured' }}</span>
                                    </div>
                                    @if($status['masked'])<div class="small mb-3"><strong>Current key:</strong> <code>{{ $status['masked'] }}</code></div>@endif
                                    <label class="form-label">Model</label>
                                    <input class="form-control mb-3" name="ai_model_{{ $provider }}" value="{{ old('ai_model_'.$provider, $status['model']) }}">
                                    <label class="form-label">{{ $status['saved'] ? 'Replace saved API key' : 'Save API key' }}</label>
                                    <div class="input-group">
                                        <input id="{{ $definition['setting'] }}" class="form-control" type="password" name="{{ $definition['setting'] }}" autocomplete="new-password" placeholder="Leave blank to keep the current key">
                                        <button class="btn btn-outline-secondary" type="button" data-toggle-secret="{{ $definition['setting'] }}" aria-label="Show or hide API key"><i class="bi bi-eye"></i></button>
                                    </div>
                                    @if($status['saved'])
                                        <label class="small text-danger d-block mt-2"><input type="checkbox" name="remove_{{ $definition['setting'] }}" value="1"> Remove saved key and fall back to <code>.env</code></label>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </section>
        </div>

        <div class="col-12 text-end"><button class="btn btn-brand px-4">Save settings</button></div>
    </div>
</form>
@endsection

@push('scripts')
<script>
document.querySelectorAll('[data-toggle-secret]').forEach((button) => {
    button.addEventListener('click', () => {
        const input = document.getElementById(button.dataset.toggleSecret);
        const visible = input.type === 'text';
        input.type = visible ? 'password' : 'text';
        button.querySelector('i').className = visible ? 'bi bi-eye' : 'bi bi-eye-slash';
    });
});
</script>
@endpush
