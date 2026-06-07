@if($lead->has_website && $lead->website)
    <a class="website-link" href="{{ $lead->website }}" target="_blank" rel="noopener noreferrer" title="{{ $lead->website }}">
        <i class="bi bi-box-arrow-up-right"></i> Visit website
    </a>
@elseif($lead->website_status === 'Social Profile Only')
    <span class="badge bg-info-subtle text-info-emphasis">Social profile only</span>
@else
    <span class="badge bg-danger-subtle text-danger">No website</span>
@endif
