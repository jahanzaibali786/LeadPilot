@extends('layouts.app')
@section('title', 'My profile')
@section('eyebrow', 'Account and security')

@section('content')
<form method="post" action="{{ route('profile.update') }}" enctype="multipart/form-data">
    @csrf
    @method('put')
    <div class="row g-4">
        <div class="col-lg-4">
            <section class="panel h-100">
                <div class="panel-body text-center py-5">
                    <div id="profile-photo-preview" class="profile-avatar mx-auto mb-3" data-initial="{{ strtoupper(substr($user->name, 0, 1)) }}">
                        @if($user->profile_photo_path)<img src="{{ asset('storage/'.$user->profile_photo_path) }}" alt="{{ $user->name }}">@else{{ strtoupper(substr($user->name, 0, 1)) }}@endif
                    </div>
                    <label class="btn btn-sm btn-outline-secondary mb-2" for="profile-photo-input"><i class="bi bi-camera"></i> Choose picture</label>
                    <input id="profile-photo-input" class="visually-hidden" type="file" name="profile_photo" accept="image/jpeg,image/png,image/webp">
                    <div id="profile-photo-name" class="muted mb-3">JPG, PNG or WebP, up to 2 MB.</div>
                    @if($user->profile_photo_path)<label class="small text-danger d-block mb-3"><input id="remove-profile-photo" type="checkbox" name="remove_profile_photo" value="1"> Remove current picture</label>@endif
                    <h2 class="h5 mb-1">{{ $user->name }}</h2>
                    <p class="muted mb-3">{{ $user->email }}</p>
                    <span class="badge badge-soft">{{ $user->getRoleNames()->first() ?: 'User' }}</span>
                </div>
            </section>
        </div>
        <div class="col-lg-8">
            <section class="panel">
                <div class="panel-head"><div><h2>Profile details</h2><span class="muted">Update your account information and password.</span></div></div>
                <div class="panel-body">
                    <div class="row g-3">
                        <div class="col-md-6"><label class="form-label">Full name</label><input class="form-control" name="name" value="{{ old('name', $user->name) }}" required></div>
                        <div class="col-md-6"><label class="form-label">Email address</label><input class="form-control" type="email" name="email" value="{{ old('email', $user->email) }}" required></div>
                        <div class="col-12"><hr><h3 class="h6 mb-0">Change password</h3><span class="muted">Leave these fields blank to keep your current password.</span></div>
                        <div class="col-md-4"><label class="form-label">Current password</label><input class="form-control" type="password" name="current_password" autocomplete="current-password"></div>
                        <div class="col-md-4"><label class="form-label">New password</label><input class="form-control" type="password" name="password" autocomplete="new-password"></div>
                        <div class="col-md-4"><label class="form-label">Confirm password</label><input class="form-control" type="password" name="password_confirmation" autocomplete="new-password"></div>
                        <div class="col-12 text-end"><button class="btn btn-brand px-4">Save profile</button></div>
                    </div>
                </div>
            </section>
        </div>
    </div>
</form>
@endsection

@push('scripts')
<script>
(() => {
    const input = document.getElementById('profile-photo-input');
    const preview = document.getElementById('profile-photo-preview');
    const name = document.getElementById('profile-photo-name');
    const remove = document.getElementById('remove-profile-photo');
    let objectUrl;

    input?.addEventListener('change', () => {
        const file = input.files?.[0];
        if (!file) return;
        if (objectUrl) URL.revokeObjectURL(objectUrl);
        objectUrl = URL.createObjectURL(file);
        preview.innerHTML = '';
        const image = document.createElement('img');
        image.src = objectUrl;
        image.alt = 'Selected profile picture preview';
        preview.appendChild(image);
        name.textContent = file.name;
        if (remove) remove.checked = false;
    });

    remove?.addEventListener('change', () => {
        if (!remove.checked) return;
        input.value = '';
        preview.replaceChildren(document.createTextNode(preview.dataset.initial));
        name.textContent = 'Picture will be removed after saving.';
    });
})();
</script>
@endpush