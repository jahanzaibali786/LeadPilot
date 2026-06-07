@extends('layouts.app')
@php($editing = isset($campaign))
@section('title', $editing ? 'Edit campaign' : 'Create campaign')
@section('eyebrow', 'Choose a market and search area')

@push('head')
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
@endpush

@section('content')
<section class="panel">
    <div class="panel-head">
        <div><h2>{{ $editing ? 'Edit campaign details' : 'Campaign details' }}</h2><span class="muted">OpenStreetMap location picker; Google Places is used only when the campaign runs.</span></div>
        <a class="btn btn-sm btn-outline-secondary" href="{{ route('campaigns.index') }}"><i class="bi bi-arrow-left"></i> Campaign list</a>
    </div>
    @php($defaultCountryCode = old('country_code', $campaign->country_code ?? 'PK'))
    <form id="campaign-form" class="panel-body" method="post" action="{{ $editing ? route('campaigns.update', $campaign) : route('campaigns.store') }}">
        @csrf
        @if($editing) @method('PUT') @endif
        <div class="row g-3">
            <div class="col-lg-5"><label class="form-label">Campaign title</label><input class="form-control" name="title" value="{{ old('title', $campaign->title ?? '') }}" placeholder="Restaurants without websites in Toronto" required></div>
            <div class="col-lg-3"><label class="form-label">Service</label><select class="form-select" name="service_id" required><option value="">Select service</option>@foreach($services as $service)<option value="{{ $service->id }}" @selected(old('service_id', $campaign->service_id ?? '') == $service->id)>{{ $service->service_name }}</option>@endforeach</select></div>
            <div class="col-lg-4"><label class="form-label">Country</label><select id="campaign-country-code" class="form-select" name="country_code" required>@foreach($countries as $code => $name)<option value="{{ $code }}" @selected($defaultCountryCode === $code)>{{ $name }}</option>@endforeach</select><input id="campaign-country" type="hidden" name="country" value="{{ $countries[$defaultCountryCode] ?? 'Pakistan' }}"></div>
            <div class="col-lg-5 position-relative">
                <label class="form-label">City or region</label>
                <div class="input-group"><input id="campaign-city-search" class="form-control" value="{{ old('city', $campaign->city ?? '') }}" placeholder="Search a city or region" autocomplete="off"><button id="location-search-button" class="btn btn-outline-secondary" type="button"><i class="bi bi-search"></i></button></div>
                <input id="campaign-city" type="hidden" name="city" value="{{ old('city', $campaign->city ?? '') }}" required><div id="location-search-results" class="location-search-results d-none"></div>
            </div>
            <div class="col-lg-3"><label class="form-label">State / province / region</label><input id="campaign-province" class="form-control" name="province" value="{{ old('province', $campaign->province ?? '') }}" placeholder="Filled from selected place"></div>
            <div class="col-lg-2"><label class="form-label">Business category</label><input class="form-control" name="business_category" value="{{ old('business_category', $campaign->business_category ?? '') }}" placeholder="Restaurants" required></div>
            <div class="col-lg-2"><label class="form-label">Search keyword</label><input class="form-control" name="keyword" value="{{ old('keyword', $campaign->keyword ?? '') }}" placeholder="family restaurants"></div>
            <input id="campaign-latitude" type="hidden" name="latitude" value="{{ old('latitude', $campaign->latitude ?? '') }}"><input id="campaign-longitude" type="hidden" name="longitude" value="{{ old('longitude', $campaign->longitude ?? '') }}">
            <div class="col-lg-8"><div class="location-picker"><div id="campaign-location-map" class="campaign-location-map"></div></div><div class="muted mt-2">Search for a place or click the map. The selected place name and full address appear beside the map.</div></div>
            <div class="col-lg-4"><div class="radius-card h-100">
                <label class="form-label d-flex justify-content-between">Search radius <strong id="radius-label">{{ number_format(old('radius_meters', $campaign->radius_meters ?? 10000) / 1000, 1) }} km</strong></label>
                <input id="campaign-radius" class="form-range" type="range" name="radius_meters" min="500" max="50000" step="500" value="{{ old('radius_meters', $campaign->radius_meters ?? 10000) }}"><div class="d-flex justify-content-between muted"><span>0.5 km</span><span>50 km</span></div><hr>
                <p class="muted mb-1">Selected place</p><strong id="selected-location-label">{{ old('city', $campaign->city ?? '') ?: 'No place selected yet' }}</strong><p id="selected-location-address" class="small text-secondary mt-2 mb-2">{{ old('province', $campaign->province ?? '') }}</p><div id="selected-location-coordinates" class="muted">@if(old('latitude', $campaign->latitude ?? '') && old('longitude', $campaign->longitude ?? '')){{ old('latitude', $campaign->latitude ?? '') }}, {{ old('longitude', $campaign->longitude ?? '') }}@endif</div>
            </div></div>
            <div class="col-lg-2"><label class="form-label">Minimum rating</label><input class="form-control" type="number" step=".1" min="0" max="5" name="minimum_rating" value="{{ old('minimum_rating', $campaign->minimum_rating ?? 3.5) }}"></div>
            <div class="col-lg-2"><label class="form-label">Minimum reviews</label><input class="form-control" type="number" name="minimum_reviews" value="{{ old('minimum_reviews', $campaign->minimum_reviews ?? 10) }}"></div>
            <div class="col-lg-2"><label class="form-label">Required leads</label><input class="form-control" type="number" name="required_leads" value="{{ old('required_leads', $campaign->required_leads ?? 50) }}" max="500"></div>
            <div class="col-lg-6 d-flex align-items-end gap-4"><label class="small"><input type="checkbox" name="only_without_website" value="1" @checked(old('only_without_website', $campaign->only_without_website ?? true))> Only without website</label><label class="small"><input type="checkbox" name="only_with_phone" value="1" @checked(old('only_with_phone', $campaign->only_with_phone ?? true))> Only with phone</label></div>
            <div class="col-12 text-end"><button class="btn btn-brand px-4">{{ $editing ? 'Update campaign' : 'Create campaign' }}</button></div>
        </div>
    </form>
</section>
@endsection

@push('scripts')
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
const countries = @json($countries);
const countrySelect = document.getElementById('campaign-country-code');
const countryInput = document.getElementById('campaign-country');
const citySearch = document.getElementById('campaign-city-search');
const cityInput = document.getElementById('campaign-city');
const provinceInput = document.getElementById('campaign-province');
const latitudeInput = document.getElementById('campaign-latitude');
const longitudeInput = document.getElementById('campaign-longitude');
const radiusInput = document.getElementById('campaign-radius');
const radiusLabel = document.getElementById('radius-label');
const selectedLabel = document.getElementById('selected-location-label');
const selectedAddress = document.getElementById('selected-location-address');
const selectedCoordinates = document.getElementById('selected-location-coordinates');
const resultsBox = document.getElementById('location-search-results');
const initialPosition = latitudeInput.value && longitudeInput.value ? [Number(latitudeInput.value), Number(longitudeInput.value)] : [30.3753, 69.3451];
const map = L.map('campaign-location-map').setView(initialPosition, latitudeInput.value ? 11 : 5);
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {maxZoom: 19, attribution: '&copy; OpenStreetMap contributors'}).addTo(map);
let marker = latitudeInput.value ? L.marker(initialPosition).addTo(map) : null;
let circle = latitudeInput.value ? L.circle(initialPosition, {radius: Number(radiusInput.value), color: '#16697a', fillColor: '#1f8a70', fillOpacity: .12}).addTo(map) : null;
let searchTimer;
const addressValue = (address, keys) => keys.map((key) => address[key]).find(Boolean) || '';
const setRadius = () => {const meters = Number(radiusInput.value); radiusLabel.textContent = meters < 1000 ? `${(meters / 1000).toFixed(1)} km` : `${(meters / 1000).toFixed(0)} km`; if (circle) circle.setRadius(meters);};
const focusPlace = (place, maximumZoom = 13) => {
    if (Array.isArray(place.boundingbox) && place.boundingbox.length === 4) {
        const [south, north, west, east] = place.boundingbox.map(Number);
        map.fitBounds([[south, west], [north, east]], {padding: [28, 28], maxZoom: maximumZoom});
        return;
    }
    map.setView([Number(place.lat), Number(place.lon)], maximumZoom);
};
const setSelectedPlace = (place, shouldMove = true) => {
    const lat = Number(place.lat); const lon = Number(place.lon); const address = place.address || {};
    const city = addressValue(address, ['city','town','village','municipality','county','state_district','state']) || place.name || place.display_name.split(',')[0];
    const province = addressValue(address, ['state','state_district','region','province','county']); const countryCode = (address.country_code || countrySelect.value).toUpperCase();
    cityInput.value = city; citySearch.value = city; provinceInput.value = province; latitudeInput.value = lat.toFixed(7); longitudeInput.value = lon.toFixed(7);
    if (countries[countryCode]) {countrySelect.value = countryCode; countryInput.value = countries[countryCode];}
    selectedLabel.textContent = city; selectedAddress.textContent = place.display_name || [city, province, countryInput.value].filter(Boolean).join(', '); selectedCoordinates.textContent = `${latitudeInput.value}, ${longitudeInput.value}`;
    if (!marker) marker = L.marker([lat, lon]).addTo(map); else marker.setLatLng([lat, lon]);
    if (!circle) circle = L.circle([lat, lon], {color:'#16697a',fillColor:'#1f8a70',fillOpacity:.12}).addTo(map); circle.setLatLng([lat, lon]); setRadius(); if (shouldMove) focusPlace(place); resultsBox.classList.add('d-none');
};
const nominatim = async (path, params) => {const url = new URL(`https://nominatim.openstreetmap.org/${path}`); Object.entries(params).forEach(([key,value]) => value && url.searchParams.set(key,value)); const response = await fetch(url,{headers:{'Accept':'application/json'}}); if (!response.ok) throw new Error('Location service is temporarily unavailable.'); return response.json();};
const searchLocations = async () => {
    const query = citySearch.value.trim(); if (query.length < 2) {resultsBox.classList.add('d-none'); return;}
    resultsBox.innerHTML = '<div class="location-search-status">Searching places...</div>'; resultsBox.classList.remove('d-none');
    try {const places = await nominatim('search',{q:query,countrycodes:countrySelect.value.toLowerCase(),format:'jsonv2',addressdetails:'1',limit:'6'}); resultsBox.innerHTML = ''; if (!places.length) {resultsBox.innerHTML='<div class="location-search-status">No matching places found.</div>'; return;} places.forEach((place) => {const button=document.createElement('button'); button.type='button'; button.className='location-search-result'; const title=document.createElement('strong'); title.textContent=place.name || place.display_name.split(',')[0]; const detail=document.createElement('span'); detail.textContent=place.display_name; button.append(title,detail); button.addEventListener('click',()=>setSelectedPlace(place)); resultsBox.appendChild(button);});} catch(error) {resultsBox.innerHTML=`<div class="location-search-status text-danger">${error.message}</div>`;}
};
citySearch.addEventListener('input',()=>{cityInput.value=''; clearTimeout(searchTimer); searchTimer=setTimeout(searchLocations,450);});
citySearch.addEventListener('keydown',(event)=>{if(event.key==='Enter'){event.preventDefault();searchLocations();}}); document.getElementById('location-search-button').addEventListener('click',searchLocations);
countrySelect.addEventListener('change', async () => {
    const countryCode = countrySelect.value;
    const countryName = countries[countryCode] || '';
    countryInput.value = countryName;
    citySearch.value = '';
    cityInput.value = '';
    provinceInput.value = '';
    latitudeInput.value = '';
    longitudeInput.value = '';
    selectedLabel.textContent = `Loading ${countryName}...`;
    selectedAddress.textContent = 'Choose a city, region, or point on the map.';
    selectedCoordinates.textContent = '';
    resultsBox.classList.add('d-none');
    if (marker) {map.removeLayer(marker); marker = null;}
    if (circle) {map.removeLayer(circle); circle = null;}

    try {
        const places = await nominatim('search', {q: countryName, countrycodes: countryCode.toLowerCase(), format: 'jsonv2', addressdetails: '1', featuretype: 'country', limit: '1'});
        if (!places.length) throw new Error('Country boundary was not found.');
        focusPlace(places[0], 6);
        selectedLabel.textContent = countryName;
    } catch (error) {
        selectedLabel.textContent = countryName;
        selectedAddress.textContent = error.message;
    }
});
map.on('click',async({latlng})=>{selectedLabel.textContent='Finding place name...';selectedAddress.textContent='';try{const place=await nominatim('reverse',{lat:latlng.lat,lon:latlng.lng,format:'jsonv2',addressdetails:'1',zoom:'14'});setSelectedPlace(place,false);}catch(error){setSelectedPlace({lat:latlng.lat,lon:latlng.lng,name:'Selected map point',display_name:'Place name unavailable',address:{}},false);}});
radiusInput.addEventListener('input',setRadius);
document.getElementById('campaign-form').addEventListener('submit',(event)=>{countryInput.value=countries[countrySelect.value]||'';if(cityInput.value&&latitudeInput.value&&longitudeInput.value)return;event.preventDefault();citySearch.setCustomValidity('Select a place from the results or click the map.');citySearch.reportValidity();citySearch.addEventListener('input',()=>citySearch.setCustomValidity(''),{once:true});}); setRadius();
</script>
@endpush
