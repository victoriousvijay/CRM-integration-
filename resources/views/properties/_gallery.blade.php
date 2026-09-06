{{--
    Read-only photo gallery for a property: the cover image large, the rest as
    a strip of thumbnails that swap into it.

    @param \App\Models\Property $property
--}}
@if($property->images->isNotEmpty())
@php $galleryId = 'gallery-'.$property->id; @endphp
<div class="card mb-3">
    <img id="{{ $galleryId }}-main" src="{{ $property->images->first()->url() }}"
         alt="{{ $property->address }}" class="card-img-top"
         style="max-height:420px;object-fit:cover;width:100%;">

    @if($property->images->count() > 1)
        <div class="card-body p-2 d-flex gap-2 flex-wrap">
            @foreach($property->images as $image)
                <img src="{{ $image->url() }}" alt="{{ $image->filename }}"
                     class="rounded" style="height:64px;width:84px;object-fit:cover;cursor:pointer;"
                     onclick="document.getElementById('{{ $galleryId }}-main').src = this.src;">
            @endforeach
        </div>
    @endif
</div>
@endif
