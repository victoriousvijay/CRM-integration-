{{--
    Platform attribution. Appears under every screen, including a tenant's own
    white-labelled pages: the tenant's brand is what the page is about, this
    says whose product it runs on.
--}}
<div class="text-center text-secondary py-4">
    <a href="{{ config('app.url') }}" class="d-inline-flex align-items-center gap-2 text-decoration-none text-secondary">
        <img src="{{ \App\Support\Brand::platformMark() }}" alt="" style="height:20px;width:auto;">
        <span>{{ __('Powered by :platform', ['platform' => \App\Support\Brand::platformName()]) }}</span>
    </a>
    <div class="small mt-1">
        &copy; {{ date('Y') }} {{ \App\Support\Brand::platformName() }}. {{ __('All rights reserved.') }}
    </div>
</div>
