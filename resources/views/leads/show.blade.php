@extends('layouts.app')
@section('title', $lead->business_name)
@section('eyebrow', 'Lead workspace - '.$lead->city)

@section('content')
<div class="d-flex flex-wrap gap-2 mb-4">
    <span id="lead-quality-badge" class="badge quality-{{$lead->lead_quality}} fs-6">{{$lead->lead_quality}} - {{$lead->lead_score}}</span>
    <span class="badge {{$lead->has_website ? 'bg-success-subtle text-success' : ($lead->website_status === 'Social Profile Only' ? 'bg-info-subtle text-info-emphasis' : 'bg-danger-subtle text-danger')}} fs-6">{{$lead->website_status}}</span>
    <span class="badge badge-soft fs-6">{{$lead->board_status}}</span>
    @if($lead->google_maps_url)<a class="btn btn-sm btn-outline-secondary ms-auto" target="_blank" href="{{$lead->google_maps_url}}"><i class="bi bi-geo-alt"></i> Open Maps</a>@endif
    <form id="ai-pitch-form" method="post" action="{{route('leads.ai', $lead)}}">
        @csrf
        <button id="ai-pitch-button" class="btn btn-sm btn-brand"><i class="bi bi-stars"></i> <span>Generate AI pitch</span></button>
    </form>
</div>

<div id="ai-pitch-progress" class="ai-pitch-progress mb-4" hidden>
    <div class="d-flex align-items-center gap-3">
        <span class="ai-pitch-spinner"><i class="bi bi-stars"></i></span>
        <div class="flex-grow-1">
            <div class="d-flex justify-content-between gap-3"><strong id="ai-pitch-stage">Preparing lead context...</strong><span id="ai-pitch-elapsed">0s</span></div>
            <div class="progress mt-2"><div class="progress-bar progress-bar-striped progress-bar-animated w-100"></div></div>
            <div id="ai-pitch-live-text" class="small muted mt-2">Reading business details and selecting the best outreach angle.</div>
        </div>
    </div>
</div>
<div id="ai-pitch-alert" class="alert" hidden></div>

<div class="detail-grid">
<div>
    <section class="panel mb-4">
        <div class="panel-head"><h2>Business opportunity</h2></div>
        <div class="panel-body"><div class="row g-4">
            <div class="col-md-6"><p class="muted mb-1">Category</p><strong>{{$lead->business_category}}</strong></div>
            <div class="col-md-6"><p class="muted mb-1">Location</p><strong>{{$lead->city}}, {{$lead->province}}</strong></div>
            <div class="col-md-6"><p class="muted mb-1">Phone / WhatsApp</p><strong>{{$lead->formatted_phone ?: 'Not listed'}}</strong></div>
            <div class="col-md-6"><p class="muted mb-1">Rating & reviews</p><strong>{{$lead->rating ?: '-'}} - {{$lead->total_reviews}} reviews</strong></div>
            <div class="col-12"><p class="muted mb-1">Address</p><strong>{{$lead->address ?: 'Not available'}}</strong></div>
            <div class="col-12"><p class="muted mb-2">Website and social profiles</p><div class="d-flex flex-wrap gap-2">
                @if($lead->website)<a class="btn btn-sm btn-outline-secondary" target="_blank" rel="noopener" href="{{$lead->website}}"><i class="bi bi-globe2"></i> Website</a>@endif
                @if($lead->facebook_url)<a class="btn btn-sm btn-outline-primary" target="_blank" rel="noopener" href="{{$lead->facebook_url}}"><i class="bi bi-facebook"></i> Facebook</a>@endif
                @if($lead->instagram_url)<a class="btn btn-sm btn-outline-danger" target="_blank" rel="noopener" href="{{$lead->instagram_url}}"><i class="bi bi-instagram"></i> Instagram</a>@endif
                @if($lead->linkedin_url)<a class="btn btn-sm btn-outline-primary" target="_blank" rel="noopener" href="{{$lead->linkedin_url}}"><i class="bi bi-linkedin"></i> LinkedIn</a>@endif
                @unless($lead->website || $lead->facebook_url || $lead->instagram_url || $lead->linkedin_url)<span class="muted">No website or social profiles provided.</span>@endunless
            </div></div>
            <div class="col-12"><p class="muted mb-1">Why this lead matches</p><p id="lead-match-reason" class="mb-0">{{$lead->match_reason ?: 'Run AI analysis for a richer qualification explanation.'}}</p></div>
            <div class="col-12"><p class="muted mb-1">Suggested offer</p><p id="lead-suggested-offer" class="mb-0">{{$lead->suggested_offer ?: 'Generate an AI pitch for a tailored offer.'}}</p></div>
            <div class="col-12"><p class="muted mb-1">Pitch angle</p><p id="lead-suggested-pitch" class="mb-0">{{$lead->suggested_pitch ?: 'The personalized pitch angle will appear here.'}}</p></div>
        </div></div>
    </section>

    <section class="panel mb-4">
        <div class="panel-head"><h2>Outreach kit</h2></div>
        <div class="panel-body">
            <h3 class="h6">WhatsApp - English</h3>
            <div id="lead-whatsapp-english" class="message-box mb-2">{{$lead->whatsapp_message_english ?: 'Generate an AI pitch to create a personalized message.'}}</div>
            <button type="button" class="btn btn-sm btn-outline-secondary mb-4 ai-copy-button" data-copy-target="lead-whatsapp-english"><i class="bi bi-copy"></i> Copy message</button>

            <h3 class="h6">WhatsApp - Localized</h3>
            <div id="lead-whatsapp-localized" class="message-box mb-2">{{$lead->whatsapp_message_roman_urdu ?: 'A localized message will appear after AI analysis.'}}</div>
            <button type="button" class="btn btn-sm btn-outline-secondary mb-4 ai-copy-button" data-copy-target="lead-whatsapp-localized"><i class="bi bi-copy"></i> Copy message</button>

            <h3 class="h6">Email</h3>
            <div class="message-box mb-4"><strong id="lead-email-subject">{{$lead->email_subject ?: 'Email subject'}}</strong><br><span id="lead-email-body">{{$lead->email_body ?: 'Email copy will appear after AI analysis.'}}</span></div>

            <h3 class="h6">Call script</h3>
            <div id="lead-call-script" class="message-box">{{$lead->call_script ?: 'A concise call script will appear after AI analysis.'}}</div>
        </div>
    </section>

    <section class="panel"><div class="panel-head"><h2>Activity timeline</h2></div><div class="panel-body timeline">@forelse($lead->activities as $activity)<div class="timeline-item"><strong class="small">{{$activity->description}}</strong><div class="muted">{{$activity->created_at->diffForHumans()}}</div></div>@empty<p class="text-secondary">No activity recorded yet.</p>@endforelse</div></section>
</div>

<div>
    <section class="panel mb-4"><div class="panel-head"><h2>Move lead</h2></div><div class="panel-body"><select id="statusSelect" class="form-select">@foreach(\App\Models\Lead::STATUSES as $status)<option @selected($lead->board_status===$status)>{{$status}}</option>@endforeach</select><button id="statusButton" class="btn btn-brand w-100 mt-2">Update status</button></div></section>
    <section class="panel mb-4"><div class="panel-head"><h2>Add note</h2></div><form class="panel-body" method="post" action="{{route('leads.notes.store',$lead)}}">@csrf<textarea class="form-control mb-3" name="note" rows="3" required placeholder="Conversation context, objection or next step"></textarea><button class="btn btn-outline-secondary w-100">Save note</button></form><div class="px-3 pb-3">@foreach($lead->leadNotes->take(4) as $note)<div class="border-top py-3 small">{{$note->note}}<div class="muted mt-1">{{$note->created_at->diffForHumans()}}</div></div>@endforeach</div></section>
    <section class="panel"><div class="panel-head"><h2>Schedule follow-up</h2></div><form class="panel-body" method="post" action="{{route('leads.followups.store',$lead)}}">@csrf<input class="form-control mb-2" type="date" name="follow_up_date" required><input class="form-control mb-2" type="time" name="follow_up_time"><select class="form-select mb-2" name="method">@foreach(['WhatsApp','Call','Email','Visit','Other'] as $method)<option>{{$method}}</option>@endforeach</select><textarea class="form-control mb-3" name="note" rows="2" required placeholder="Purpose of follow-up"></textarea><button class="btn btn-brand w-100">Schedule</button></form></section>
</div>
</div>
@endsection

@push('scripts')
<script>
document.getElementById('statusButton').onclick = async () => {
    const response = await fetch('{{route('leads.status', $lead)}}', {
        method: 'PATCH',
        headers: {'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content, 'Accept': 'application/json'},
        body: JSON.stringify({status: document.getElementById('statusSelect').value})
    });
    if (response.ok) location.reload(); else alert('Could not update status.');
};

(() => {
    const form = document.getElementById('ai-pitch-form');
    const button = document.getElementById('ai-pitch-button');
    const progress = document.getElementById('ai-pitch-progress');
    const alertBox = document.getElementById('ai-pitch-alert');
    const stage = document.getElementById('ai-pitch-stage');
    const liveText = document.getElementById('ai-pitch-live-text');
    const elapsed = document.getElementById('ai-pitch-elapsed');
    const stages = [
        ['Analyzing business opportunity...', 'Reviewing category, location, rating, reviews, and online presence.'],
        ['Finding the strongest sales angle...', 'Matching this lead with a relevant service and offer.'],
        ['Writing personalized outreach...', 'Creating WhatsApp, email, and call copy for this business.'],
        ['Polishing the pitch...', 'Checking clarity, tone, localization, and lead quality.'],
        ['Waiting for the AI provider...', 'Free models can take a little longer during busy periods.']
    ];
    let timer;
    let startedAt;

    const setText = (id, value) => {
        const element = document.getElementById(id);
        if (element && value !== null && value !== undefined) element.textContent = value;
    };

    const typeText = (id, value) => {
        const element = document.getElementById(id);
        if (!element || !value) return;
        element.textContent = '';
        const text = String(value);
        let index = 0;
        const chunk = Math.max(1, Math.ceil(text.length / 45));
        const typing = setInterval(() => {
            index = Math.min(text.length, index + chunk);
            element.textContent = text.slice(0, index);
            if (index >= text.length) clearInterval(typing);
        }, 18);
    };

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        if (button.disabled) return;

        button.disabled = true;
        button.querySelector('span').textContent = 'Generating...';
        progress.hidden = false;
        alertBox.hidden = true;
        startedAt = Date.now();
        let stageIndex = 0;
        stage.textContent = stages[0][0];
        liveText.textContent = stages[0][1];
        timer = setInterval(() => {
            const seconds = Math.floor((Date.now() - startedAt) / 1000);
            elapsed.textContent = `${seconds}s`;
            const nextIndex = Math.min(stages.length - 1, Math.floor(seconds / 7));
            if (nextIndex !== stageIndex) {
                stageIndex = nextIndex;
                stage.textContent = stages[stageIndex][0];
                liveText.textContent = stages[stageIndex][1];
            }
        }, 500);

        try {
            const response = await fetch(form.action, {
                method: 'POST',
                headers: {'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content},
                body: new FormData(form)
            });
            const data = await response.json();
            if (!response.ok) throw new Error(data.message || 'Pitch generation failed.');

            const lead = data.lead;
            const badge = document.getElementById('lead-quality-badge');
            badge.className = `badge quality-${lead.lead_quality} fs-6`;
            badge.textContent = `${lead.lead_quality} - ${lead.lead_score}`;
            setText('lead-match-reason', lead.match_reason);
            setText('lead-suggested-offer', lead.suggested_offer);
            typeText('lead-suggested-pitch', lead.suggested_pitch);
            typeText('lead-whatsapp-english', lead.whatsapp_message_english);
            typeText('lead-whatsapp-localized', lead.whatsapp_message_roman_urdu);
            setText('lead-email-subject', lead.email_subject);
            typeText('lead-email-body', lead.email_body);
            typeText('lead-call-script', lead.call_script);

            alertBox.className = 'alert alert-success';
            alertBox.textContent = data.message;
            alertBox.hidden = false;
        } catch (error) {
            alertBox.className = 'alert alert-danger';
            alertBox.textContent = error.message || 'Pitch generation failed.';
            alertBox.hidden = false;
        } finally {
            clearInterval(timer);
            progress.hidden = true;
            button.disabled = false;
            button.querySelector('span').textContent = 'Regenerate AI pitch';
        }
    });

    document.querySelectorAll('.ai-copy-button').forEach((copyButton) => {
        copyButton.addEventListener('click', async () => {
            const target = document.getElementById(copyButton.dataset.copyTarget);
            await navigator.clipboard.writeText(target.textContent.trim());
            const original = copyButton.innerHTML;
            copyButton.innerHTML = '<i class="bi bi-check-lg"></i> Copied';
            setTimeout(() => copyButton.innerHTML = original, 1200);
        });
    });
})();
</script>
@endpush
