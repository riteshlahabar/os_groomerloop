{{--
    The design bundle's own script set, in its own order (spec §14).

    `script.min.js` is the bundle's behaviour file and initialises the mobile menu, the horizontal
    marquees, the counters, the swiper sliders, the lightbox and WOW — so it must load last, after
    every plugin it reaches for. All three templates load the same set; the bundle's three index
    pages do too.
--}}
<script src="{{ asset('frontview-assets/js/bootstrap.bundle.min.js') }}"></script>
<script src="{{ asset('frontview-assets/plugins/swiper/swiper-bundle.min.js') }}"></script>
<script src="{{ asset('frontview-assets/plugins/lightbox/glightbox.min.js') }}"></script>
<script src="{{ asset('frontview-assets/plugins/lightbox/lightbox.js') }}"></script>
<script src="{{ asset('frontview-assets/plugins/wow/js/wow.min.js') }}"></script>
<script src="{{ asset('frontview-assets/js/script.min.js') }}"></script>
