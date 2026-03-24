{{-- Shared head for public-facing views --}}
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
@vite(['resources/css/app.css', 'resources/js/app.js'])
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
    body { font-family: 'Inter', system-ui, -apple-system, sans-serif; }
    .mp-iframe { border: 1px solid #d1d5db; border-radius: 0.5rem; padding: 0.625rem 0.75rem; height: 42px; width: 100%; transition: all .2s; }
    .mp-iframe:focus-within { border-color: #6366f1; box-shadow: 0 0 0 3px rgba(99,102,241,.15); }
    .tab-btn.active { border-color: #4f46e5; color: #4338ca; background: #eef2ff; }
    .tab-panel { display: none; }
    .tab-panel.active { display: block; }
    .confetti-piece { position: fixed; width: 10px; height: 10px; top: -10px; animation: confettiFall 3s ease-in-out forwards; pointer-events: none; z-index: 9999; }
    @keyframes confettiFall { 0% { top: -10px; opacity: 1; } 100% { top: 110vh; opacity: 0; transform: rotate(720deg); } }
</style>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
