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
    @php($defaultCountryCode = old('country_code', 'PK'))
    <form id="campaign-create-form" class="panel-body" method="post" action="{{ route('campaigns.store') }}">
        @csrf
        <div class="row g-3">
            <div class="col-lg-4"><label class="form-label">Campaign title</label><input class="form-control" name="title" value="{{ old('title') }}" placeholder="Restaurants without websites in Toronto" required></div>
            <div class="col-lg-3"><label class="form-label">Service</label><select class="form-select" name="service_id" required><option value="">Select service</option>@foreach($services as $service)<option value="{{ $service->id }}" @selected(old('service_id') == $service->id)>{{ $service->service_name }}</option>@endforeach</select></div>
            <div class="col-lg-5">
                <label class="form-label">Country</label>
                <input id="campaign-country" class="form-control" name="country" list="campaign-country-options"
                    value="{{ old('country', $countries[$defaultCountryCode] ?? 'Pakistan') }}"
                    placeholder="Type a country" autocomplete="off" required>
                <input id="campaign-country-code" type="hidden" name="country_code" value="{{ $defaultCountryCode }}">
                <datalist id="campaign-country-options">
                    @foreach($countries as $code => $name)<option value="{{ $name }}"></option>@endforeach
                </datalist>
            </div>

            <div class="col-lg-4"><label class="form-label">City or region</label><input id="campaign-city" class="form-control" name="city" value="{{ old('city') }}" placeholder="Start typing a city" autocomplete="off" required></div>
            <div class="col-lg-3"><label class="form-label">State / province / region</label><input id="campaign-province" class="form-control" name="province" value="{{ old('province') }}" placeholder="Filled automatically"></div>
            <div class="col-lg-3"><label class="form-label">Business category</label><input class="form-control" name="business_category" value="{{ old('business_category') }}" placeholder="Restaurants" required></div>
            <div class="col-lg-2"><label class="form-label">Search keyword</label><input class="form-control" name="keyword" value="{{ old('keyword') }}" placeholder="family restaurants"></div>

            <input id="campaign-latitude" type="hidden" name="latitude" value="{{ old('latitude') }}">
            <input id="campaign-longitude" type="hidden" name="longitude" value="{{ old('longitude') }}">

            <div class="col-lg-8">
                <div class="location-picker">
                    <div id="campaign-location-map" class="campaign-location-map"></div>
                    <div id="map-unavailable" class="map-unavailable {{ $googleMapsBrowserKey ? 'd-none' : '' }}">
                        Add <code>GOOGLE_MAPS_BROWSER_API_KEY</code> to enable city autocomplete and map selection.
                    </div>
                </div>
                <div class="muted mt-2">Choose an autocomplete result or click the map to set the campaign center.</div>
            </div>
            <div class="col-lg-4">
                <div class="radius-card h-100">
                    <label class="form-label d-flex justify-content-between">
                        Search radius <strong id="radius-label">{{ number_format(old('radius_meters', 10000) / 1000, 1) }} km</strong>
                    </label>
                    <input id="campaign-radius" class="form-range" type="range" name="radius_meters"
                        min="500" max="50000" step="500" value="{{ old('radius_meters', 10000) }}">
                    <div class="d-flex justify-content-between muted"><span>0.5 km</span><span>50 km</span></div>
                    <hr>
                    <p class="muted mb-1">Selected center</p>
                    <strong id="selected-location-label">{{ old('city') ?: 'No map point selected yet' }}</strong>
                </div>
            </div>

            <div class="col-lg-2"><label class="form-label">Minimum rating</label><input class="form-control" type="number" step=".1" min="0" max="5" name="minimum_rating" value="{{ old('minimum_rating', 3.5) }}"></div>
            <div class="col-lg-2"><label class="form-label">Minimum reviews</label><input class="form-control" type="number" name="minimum_reviews" value="{{ old('minimum_reviews', 10) }}"></div>
            <div class="col-lg-2"><label class="form-label">Required leads</label><input class="form-control" type="number" name="required_leads" value="{{ old('required_leads', 50) }}" max="500"></div>
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
                    <td>{{ $campaign->city }}, {{ $campaign->country }}<div class="muted">{{ $campaign->business_category }}</div></td>
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
const campaignCountries = @json($countries);
const countryNameToCode = Object.fromEntries(
    Object.entries(campaignCountries).map(([code, name]) => [name.toLowerCase(), code])
);
const campaignCountryInput = document.getElementById('campaign-country');
const campaignCountryCodeInput = document.getElementById('campaign-country-code');
const syncCampaignCountry = () => {
    const code = countryNameToCode[campaignCountryInput.value.trim().toLowerCase()] || '';
    campaignCountryCodeInput.value = code;
    return code;
};
campaignCountryInput.addEventListener('input', syncCampaignCountry);
document.getElementById('campaign-create-form').addEventListener('submit', (event) => {
    if (syncCampaignCountry()) return;
    event.preventDefault();
    campaignCountryInput.setCustomValidity('Choose a country from the suggestions.');
    campaignCountryInput.reportValidity();
    campaignCountryInput.addEventListener('input', () => campaignCountryInput.setCustomValidity(''), {once: true});
});

window.initCampaignLocationPicker = () => {
    const countryInput = document.getElementById('campaign-country');
    const countryCodeInput = document.getElementById('campaign-country-code');
    const cityInput = document.getElementById('campaign-city');
    const provinceInput = document.getElementById('campaign-province');
    const latitudeInput = document.getElementById('campaign-latitude');
    const longitudeInput = document.getElementById('campaign-longitude');
    const radiusInput = document.getElementById('campaign-radius');
    const radiusLabel = document.getElementById('radius-label');
    const selectedLabel = document.getElementById('selected-location-label');
    const initialPosition = latitudeInput.value && longitudeInput.value
        ? {lat: Number(latitudeInput.value), lng: Number(longitudeInput.value)}
        : {lat: 30.3753, lng: 69.3451};
    const map = new google.maps.Map(document.getElementById('campaign-location-map'), {
        center: initialPosition,
        zoom: latitudeInput.value ? 11 : 5,
        mapTypeControl: false,
        streetViewControl: false,
    });
    const geocoder = new google.maps.Geocoder();
    const marker = new google.maps.Marker({map, position: latitudeInput.value ? initialPosition : null});
    const circle = new google.maps.Circle({
        map,
        center: initialPosition,
        radius: Number(radiusInput.value),
        fillColor: '#1f8a70',
        fillOpacity: 0.12,
        strokeColor: '#16697a',
        strokeOpacity: 0.8,
        strokeWeight: 2,
        visible: Boolean(latitudeInput.value),
    });
    const autocomplete = new google.maps.places.Autocomplete(cityInput, {
        fields: ['address_components', 'geometry', 'name', 'formatted_address'],
        types: ['(cities)'],
        componentRestrictions: {country: countryCodeInput.value.toLowerCase()},
    });

    const component = (components, type) =>
        components.find((item) => item.types.includes(type));

    const setRadius = (meters) => {
        const value = Math.min(50000, Math.max(500, Math.round(meters / 500) * 500));
        radiusInput.value = value;
        radiusLabel.textContent = `${(value / 1000).toFixed(value < 1000 ? 1 : 0)} km`;
        circle.setRadius(value);
    };

    const setCenter = (position, label) => {
        latitudeInput.value = position.lat().toFixed(7);
        longitudeInput.value = position.lng().toFixed(7);
        marker.setPosition(position);
        circle.setCenter(position);
        circle.setVisible(true);
        selectedLabel.textContent = label || `${latitudeInput.value}, ${longitudeInput.value}`;
    };

    const applyAddress = (components, fallbackName = '') => {
        const locality = component(components, 'locality')
            || component(components, 'postal_town')
            || component(components, 'administrative_area_level_2')
            || component(components, 'administrative_area_level_1');
        const region = component(components, 'administrative_area_level_1');
        const country = component(components, 'country');

        cityInput.value = locality?.long_name || fallbackName;
        provinceInput.value = region?.long_name || '';
        if (country) {
            countryInput.value = country.long_name;
            countryCodeInput.value = country.short_name.toUpperCase();
            autocomplete.setComponentRestrictions({country: country.short_name.toLowerCase()});
        }
    };

    autocomplete.addListener('place_changed', () => {
        const place = autocomplete.getPlace();
        if (!place.geometry?.location) return;

        applyAddress(place.address_components || [], place.name);
        setCenter(place.geometry.location, place.formatted_address || place.name);

        if (place.geometry.viewport) {
            map.fitBounds(place.geometry.viewport);
            const distance = google.maps.geometry.spherical.computeDistanceBetween(
                place.geometry.location,
                place.geometry.viewport.getNorthEast()
            );
            setRadius(distance);
        } else {
            map.setCenter(place.geometry.location);
            map.setZoom(11);
            setRadius(10000);
        }
    });

    map.addListener('click', ({latLng}) => {
        setCenter(latLng);
        const zoomRadius = 40000000 / Math.pow(2, map.getZoom() || 11);
        setRadius(zoomRadius);
        geocoder.geocode({location: latLng}, (results, status) => {
            if (status !== 'OK' || !results[0]) return;
            applyAddress(results[0].address_components || [], results[0].formatted_address);
            selectedLabel.textContent = results[0].formatted_address;
        });
    });

    radiusInput.addEventListener('input', () => setRadius(Number(radiusInput.value)));

    cityInput.addEventListener('input', () => {
        latitudeInput.value = '';
        longitudeInput.value = '';
        circle.setVisible(false);
        marker.setPosition(null);
        selectedLabel.textContent = 'Choose an autocomplete result or click the map';
    });

    countryInput.addEventListener('change', () => {
        const code = syncCampaignCountry();
        if (!code) return;
        autocomplete.setComponentRestrictions({country: code.toLowerCase()});
        geocoder.geocode({address: campaignCountries[code]}, (results, status) => {
            if (status !== 'OK' || !results[0]) return;
            map.fitBounds(results[0].geometry.viewport);
        });
    });

    setRadius(Number(radiusInput.value));
};

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
@if($googleMapsBrowserKey)
<script async src="https://maps.googleapis.com/maps/api/js?key={{ urlencode($googleMapsBrowserKey) }}&libraries=places,geometry&callback=initCampaignLocationPicker"></script>
@endif
@endpush
