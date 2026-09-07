{{--
    Launch animation for the installed app.

    Android's own launch screen is just the manifest icon on background_color —
    it cannot be animated, and it is already on screen before any of our code
    runs. So this takes over the moment the first page paints: the mark drops
    in, the wordmark types itself out a letter at a time, then the whole thing
    lifts away. Because the native screen and this one share a black ground and
    the same mark, the hand-off reads as one continuous animation.

    Only on a launch of the installed app. This is a server-rendered app, so in
    a browser tab every click is a fresh page load — replaying the animation on
    each one would be far worse than no animation at all. The gate is therefore
    two conditions: running standalone, and not already shown this app session.
--}}
<div id="valt-splash" hidden aria-hidden="true">
    <div class="valt-splash__stage">
        <img class="valt-splash__mark" src="{{ asset('images/platform-mark.png') }}" alt="" width="160" height="119">
        <div class="valt-splash__word">
            @foreach (['V', 'a', 'l', 't'] as $i => $letter)
                <span class="valt-splash__letter" style="animation-delay: {{ 420 + $i * 85 }}ms">{{ $letter }}</span>
            @endforeach
            @foreach (['C', 'R', 'M'] as $i => $letter)
                <span class="valt-splash__letter valt-splash__letter--accent" style="animation-delay: {{ 760 + $i * 85 }}ms">{{ $letter }}</span>
            @endforeach
        </div>
        <div class="valt-splash__tagline">{{ __('Built for real estate growth') }}</div>
    </div>
</div>
<style>
#valt-splash {
    position: fixed;
    inset: 0;
    z-index: 100000;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #000;
    /* The overlay animates itself out. If the script that removes the element
       never runs, the page is still usable — it ends invisible and click-through. */
    animation: valt-splash-out 420ms ease-in 2050ms both;
}
#valt-splash[hidden] { display: none; }
.valt-splash__stage {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.75rem;
    padding: 2rem;
}
.valt-splash__mark {
    width: min(160px, 34vw);
    height: auto;
    animation: valt-splash-mark 620ms cubic-bezier(0.16, 1, 0.3, 1) both;
}
.valt-splash__word {
    display: flex;
    font-family: ui-sans-serif, system-ui, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
    font-size: min(2.75rem, 11vw);
    font-weight: 800;
    letter-spacing: -0.02em;
    line-height: 1;
}
.valt-splash__letter {
    color: #123a7a;
    animation: valt-splash-letter 320ms cubic-bezier(0.16, 1, 0.3, 1) both;
}
.valt-splash__letter--accent { color: #1a8fe3; }
.valt-splash__tagline {
    font-family: ui-sans-serif, system-ui, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
    font-size: 0.6875rem;
    font-weight: 600;
    letter-spacing: 0.26em;
    text-transform: uppercase;
    color: #2b5da8;
    animation: valt-splash-tagline 420ms ease-out 1080ms both;
}
@keyframes valt-splash-mark {
    from { opacity: 0; transform: translateY(-14px) scale(0.86); }
    to   { opacity: 1; transform: none; }
}
@keyframes valt-splash-letter {
    from { opacity: 0; transform: translateY(10px); }
    to   { opacity: 1; transform: none; }
}
@keyframes valt-splash-tagline {
    from { opacity: 0; letter-spacing: 0.12em; }
    to   { opacity: 0.9; letter-spacing: 0.26em; }
}
@keyframes valt-splash-out {
    from { opacity: 1; }
    to   { opacity: 0; visibility: hidden; pointer-events: none; }
}
/* Someone who has asked for less motion still gets the brand, just held still. */
@media (prefers-reduced-motion: reduce) {
    #valt-splash,
    .valt-splash__mark,
    .valt-splash__letter,
    .valt-splash__tagline {
        animation-duration: 1ms;
        animation-delay: 0ms;
    }
    #valt-splash { animation-delay: 700ms; animation-duration: 200ms; }
}
</style>
<script>
(function () {
    var splash = document.getElementById('valt-splash');
    if (!splash) return;

    var standalone = window.matchMedia('(display-mode: standalone)').matches
        || window.matchMedia('(display-mode: fullscreen)').matches
        || window.navigator.standalone === true;

    var alreadyPlayed;
    try {
        // sessionStorage spans navigations within one app session and clears
        // when the app is closed — exactly "once per launch".
        alreadyPlayed = sessionStorage.getItem('valt-splash-played') === '1';
        if (standalone) sessionStorage.setItem('valt-splash-played', '1');
    } catch (e) {
        // Storage can throw outright when site data is blocked. Rather than
        // replay on every navigation, stay out of the way.
        alreadyPlayed = true;
    }

    if (!standalone || alreadyPlayed) {
        splash.remove();
        return;
    }

    splash.hidden = false;
    splash.addEventListener('animationend', function (event) {
        if (event.animationName === 'valt-splash-out') splash.remove();
    });
})();
</script>
