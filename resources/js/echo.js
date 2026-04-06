import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

// Fallback auf window.location.* damit WS immer durch denselben nginx-Host geht
// (Build-Zeit-Env überschreibt nur wenn VITE_REVERB_HOST explizit gesetzt)
const _reverbHost   = import.meta.env.VITE_REVERB_HOST   ?? window.location.hostname;
const _reverbPort   = import.meta.env.VITE_REVERB_PORT   ?? window.location.port;
const _reverbScheme = import.meta.env.VITE_REVERB_SCHEME ?? window.location.protocol.replace(':', '');
const _tls          = _reverbScheme === 'https';

// Resolve CSRF token only if meta tag exists
const csrf = document.head.querySelector('meta[name="csrf-token"]')?.content;

window.Echo = new Echo({
    broadcaster: 'reverb',
    key: import.meta.env.VITE_REVERB_APP_KEY,
    wsHost: _reverbHost,
    wsPort: Number(_reverbPort) || (_tls ? 443 : 80),
    wssPort: Number(_reverbPort) || 443,
    forceTLS: _tls,
    enabledTransports: ['ws', 'wss'],
    auth: csrf ? { headers: { 'X-CSRF-TOKEN': csrf } } : {},
});
