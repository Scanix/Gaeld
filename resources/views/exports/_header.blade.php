{{--
    Swiss two-column document header — shared across all export PDFs.

    Available from parent scope : $organization  (Organization model)
    Passed via @include data   : $docTitle       (translated label)
                               : $docPeriod      (formatted period string, optional)
                               : $docRef         (reference number, optional)
--}}
<div class="doc-header">
    <div class="issuer-block">
        @php
            $logoFullPath = $organization->logo_path ? storage_path('app/'.$organization->logo_path) : null;
        @endphp
        @if($logoFullPath && file_exists($logoFullPath))
            <div class="doc-logo"><img src="{{ $logoFullPath }}" alt="Logo"></div>
        @else
            <div class="doc-logo-placeholder">Logo</div>
        @endif
        <span class="org-name">{{ $organization->legal_name ?? $organization->name }}</span>
        @if($organization->address)
            <br>{{ $organization->address }}
        @endif
        @if($organization->postal_code || $organization->city)
            <br>{{ implode(' ', array_filter([$organization->postal_code ?? null, $organization->city ?? null])) }}
        @endif
        @if(!empty($organization->vat_number))
            <br><span class="org-detail">MWST-Nr.&nbsp;{{ $organization->vat_number }}</span>
        @endif
    </div>

    <div class="doc-meta-block">
        <div class="doc-type-label">{{ $docTitle }}</div>
        @if(!empty($docPeriod))
            <div class="doc-period-label">{{ $docPeriod }}</div>
        @endif
        @if(!empty($docRef))
            <div class="doc-ref-label">Réf.&nbsp;{{ $docRef }}</div>
        @endif
    </div>
</div>
