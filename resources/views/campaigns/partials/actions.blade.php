<div class="d-flex gap-2">
    @if($isActive)
        <a class="btn btn-sm btn-outline-info" href="{{ route('campaigns.show', $campaign) }}" title="View live progress"><i class="bi bi-activity"></i></a>
    @else
        <form class="ajax-campaign-run" method="post" action="{{ route('campaigns.run', $campaign) }}" data-show-url="{{ route('campaigns.show', $campaign) }}">@csrf<button class="btn btn-sm btn-brand" title="Run campaign"><i class="bi bi-play-fill"></i></button></form>
    @endif
    @unless($isActive)<a class="btn btn-sm btn-light" href="{{ route('campaigns.edit', $campaign) }}" title="Edit campaign"><i class="bi bi-pencil"></i></a>@endunless
    <a class="btn btn-sm btn-light" href="{{ route('campaigns.show', $campaign) }}" title="Open campaign"><i class="bi bi-arrow-right"></i></a>
</div>
