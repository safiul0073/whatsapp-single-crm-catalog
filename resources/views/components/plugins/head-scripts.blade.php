@php
    $ga4Enabled = (bool) setting('plugin_ga4_enabled', false);
    $ga4Id = trim((string) setting('plugin_ga4_measurement_id', ''));
    $ga4IdJson = json_encode($ga4Id, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);

    $tawkEnabled = (bool) setting('plugin_tawk_enabled', false);
    $tawkProperty = trim((string) setting('plugin_tawk_property_id', ''));
    $tawkWidget = trim((string) setting('plugin_tawk_widget_id', ''));

    if (str_contains($tawkProperty, 'embed.tawk.to')) {
        $tawkPath = trim((string) parse_url($tawkProperty, PHP_URL_PATH), '/');
        $tawkParts = array_values(array_filter(explode('/', $tawkPath)));

        $tawkProperty = $tawkParts[0] ?? '';
        $tawkWidget = $tawkParts[1] ?? $tawkWidget;
    }

    $tawkPropertyJson = json_encode($tawkProperty, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
    $tawkWidgetJson = json_encode($tawkWidget, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
@endphp

@if($ga4Enabled && $ga4Id !== '')
    <script async src="https://www.googletagmanager.com/gtag/js?id={{ urlencode($ga4Id) }}"></script>
    <script>
        'use strict';

        window.dataLayer = window.dataLayer || [];
        function gtag() {
            window.dataLayer.push(arguments);
        }
        gtag('js', new Date());
        gtag('config', <?php echo $ga4IdJson; ?>);
    </script>
@endif

@if($tawkEnabled && $tawkProperty !== '' && $tawkWidget !== '')
    <script>
        'use strict';

        window.Tawk_API = window.Tawk_API || {};
        window.Tawk_LoadStart = new Date();

        (function () {
            'use strict';

            var script = document.createElement('script');
            var firstScript = document.getElementsByTagName('script')[0];

            script.async = true;
            script.src = 'https://embed.tawk.to/' + <?php echo $tawkPropertyJson; ?> + '/' + <?php echo $tawkWidgetJson; ?>;
            script.charset = 'UTF-8';
            script.setAttribute('crossorigin', '*');
            firstScript.parentNode.insertBefore(script, firstScript);
        })();
    </script>
@endif
