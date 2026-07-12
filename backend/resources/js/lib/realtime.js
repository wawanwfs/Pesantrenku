export async function subscribeToNotifications({ userId, tenantId, onChange, connection = {} }) {
  const appKey = connection.key || import.meta.env.VITE_REVERB_APP_KEY;
  if (!appKey || !userId) return () => {};

  const [{ default: Echo }, { default: Pusher }] = await Promise.all([
    import('laravel-echo'),
    import('pusher-js'),
  ]);
  window.Pusher = Pusher;

  const scheme = connection.scheme || import.meta.env.VITE_REVERB_SCHEME || window.location.protocol.replace(':', '');
  const host = connection.host || import.meta.env.VITE_REVERB_HOST || window.location.hostname;
  const port = Number(connection.port || import.meta.env.VITE_REVERB_PORT || (scheme === 'https' ? 443 : 80));
  const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
  const echo = new Echo({
    broadcaster: 'reverb',
    key: appKey,
    wsHost: host,
    wsPort: port,
    wssPort: port,
    forceTLS: scheme === 'https',
    enabledTransports: ['ws', 'wss'],
    authEndpoint: '/broadcasting/auth',
    auth: { headers: csrf ? { 'X-CSRF-TOKEN': csrf } : {} },
  });
  const tenant = tenantId || 'platform';
  const channelName = `tenant.${tenant}.user.${userId}`;
  window.__pesantenkuRealtime = { state: 'connecting', channel: channelName };
  echo.connector.pusher.connection.bind('state_change', ({ current }) => {
    window.__pesantenkuRealtime = { state: current, channel: channelName };
  });
  echo.private(channelName).listen('.notifications.changed', onChange);

  return () => {
    echo.leave(channelName);
    echo.disconnect();
    window.__pesantenkuRealtime = { state: 'disconnected', channel: channelName };
  };
}
