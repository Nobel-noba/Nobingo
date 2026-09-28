import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

declare global {
    interface Window {
        Pusher: typeof Pusher;
        Echo: any;
    }
}

window.Pusher = Pusher;

let echoInstance: any = null;

try {
    const key = import.meta.env.VITE_REVERB_APP_KEY;
    const host = import.meta.env.VITE_REVERB_HOST || window.location.hostname;
    const port = import.meta.env.VITE_REVERB_PORT || 8080;
    const scheme = import.meta.env.VITE_REVERB_SCHEME || 'http';

    echoInstance = new Echo({
        broadcaster: 'reverb',
        key: key || 'nobingo-reverb-key',
        wsHost: host,
        wsPort: port,
        wssPort: port,
        forceTLS: scheme === 'https',
        enabledTransports: ['ws', 'wss'],
    });

    window.Echo = echoInstance;
} catch (e) {
    console.warn('Echo initialization deferred:', e);
}

export const echo = echoInstance;
export default echoInstance;
