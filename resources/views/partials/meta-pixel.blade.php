@php
    $metaPixelId = \App\Models\Setting::get('meta_pixel_id', '');
@endphp
@if($metaPixelId)
{{-- Meta Pixel --}}
<script>
  !function(f,b,e,v,n,t,s)
  {if(f.fbq)return;n=f.fbq=function(){n.callMethod?
  n.callMethod.apply(n,arguments):n.queue.push(arguments)};
  if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';
  n.queue=[];t=b.createElement(e);t.async=!0;
  t.src=v;s=b.getElementsByTagName(e)[0];
  s.parentNode.insertBefore(t,s)}(window,document,'script',
  'https://connect.facebook.net/en_US/fbevents.js');
  fbq('init', @js($metaPixelId));
  fbq('track', 'PageView');
  @if(session('meta_purchase'))
    @php
        $mp = session('meta_purchase');
        $eventId = $mp['event_id'] ?? null;
        unset($mp['event_id']);
    @endphp
    fbq('track', 'Purchase', @js($mp), @js(['eventID' => $eventId]));
  @endif
</script>
<noscript><img height="1" width="1" style="display:none"
  src="https://www.facebook.com/tr?id={{ $metaPixelId }}&ev=PageView&noscript=1"/></noscript>
{{-- End Meta Pixel --}}
@endif
