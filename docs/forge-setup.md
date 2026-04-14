# Forge Setup Guide — Reverb + Pulse

How to deploy this standalone Reverb + Pulse box on Laravel Forge from scratch.

## Architecture

Single Forge server running two sites that share one Reverb daemon:

| Site | Purpose | Forge Integrations |
|---|---|---|
| `ws.lightpost.app` | Hosts the Laravel app, Pulse dashboard, and runs the `reverb:start` daemon | Pulse |
| `ws.ws.lightpost.app` | Dedicated WebSocket entry point — nginx proxies `:443` → `127.0.0.1:8080` | Reverb |

Reverb listens on `127.0.0.1:8080` on the box. External clients connect via `wss://ws.ws.lightpost.app:443`; the `ws.ws` site's nginx handles the upgrade and proxies to the daemon.

**Why two sites?** Forge's Reverb integration is designed around a dedicated site. Adding `ws.ws.lightpost.app` as an alias on the Pulse site leaves the proxy server block unwired and every WS request returns 500.

## Server Provisioning

1. Create Forge server (any provider)
2. Create site `ws.lightpost.app` — deploy this repo
3. Create site `ws.ws.lightpost.app` — same repo (or any minimal Laravel install; the web root won't be hit)
4. Issue LE certs on both sites
5. Enable **Pulse** on `ws.lightpost.app`
6. Enable **Reverb** on `ws.ws.lightpost.app`

## Environment (`ws.lightpost.app` site)

```env
APP_URL=https://ws.lightpost.app

BROADCAST_CONNECTION=reverb
CACHE_STORE=redis
QUEUE_CONNECTION=redis

REVERB_APP_ID=<numeric id>
REVERB_APP_KEY=<hex string — NOT the APP_KEY>
REVERB_APP_SECRET=<hex string>
REVERB_HOST=ws.ws.lightpost.app
REVERB_PORT=443
REVERB_SCHEME=https
REVERB_SERVER_PORT=8080

PULSE_ALLOWED_IPS=<comma-separated public IPv4 list>
```

Generate Reverb credentials with:

```bash
php -r "echo bin2hex(random_bytes(16)), PHP_EOL;"
```

`REVERB_APP_KEY` must be a plain string — **not** a `base64:...` value. That prefix is Laravel's `APP_KEY` format and will not work as a Reverb key.

## Daemons

Configure on the `ws.lightpost.app` site (Forge → Daemons):

| Command | Purpose |
|---|---|
| `php artisan reverb:start` | The WebSocket server |
| `php artisan pulse:check` | Emits `IsolatedBeat` events that drive Pulse recorders (required for Reverb cards to populate) |

Both run as `forge`, directory `/home/forge/ws.lightpost.app/current`.

## Pulse Authorization

Pulse's default gate only allows `local` environment. For production we use an IP allowlist driven by `PULSE_ALLOWED_IPS`.

- Gate is defined in `app/Providers/AppServiceProvider.php`
- IPs are read from `config/pulse.php` (`allowed_ips` key) — **not** `env()` directly, because `php artisan optimize` caches config and `env()` returns null outside config files
- `bootstrap/app.php` calls `$middleware->trustProxies(at: '*')` so `request()->ip()` returns the client IP behind Forge's nginx

To grant access, add your public IPv4 to `PULSE_ALLOWED_IPS` and redeploy (or `php artisan optimize:clear && php artisan optimize`).

## Cache Serializable Classes

`config/cache.php` ships with `'serializable_classes' => false` in Laravel 11+. That breaks Pulse, which caches Collections — they come back as `__PHP_Incomplete_Class` and every card explodes.

Our config allows only the classes Pulse actually caches:

```php
'serializable_classes' => [
    Illuminate\Support\Collection::class,
    Carbon\Carbon::class,
    Carbon\CarbonImmutable::class,
    stdClass::class,
],
```

If new Pulse cards throw incomplete-class errors for a different type, add it here. Setting to `null` is permissive but loses gadget-chain protection.

## Pulse Reverb Cards

Wired up in two places:

- `config/pulse.php` — registers `ReverbConnections` and `ReverbMessages` recorders
- `resources/views/vendor/pulse/dashboard.blade.php` — includes `<livewire:reverb.connections />` and `<livewire:reverb.messages />`

Recorders fire on `IsolatedBeat`, which is why `pulse:check` must run as a daemon.

## Private Channel Auth

Auth lives on the **broadcasting app**, not this Reverb box.

- This box does not run `routes/channels.php` callbacks
- Broadcasting app POSTs to its own `/broadcasting/auth`, signs the token with the shared `REVERB_APP_SECRET`, and the client forwards the token to Reverb
- Every app that broadcasts through this Reverb box must have matching `REVERB_APP_ID`/`KEY`/`SECRET`

## Client App Configuration

On any app server that broadcasts through this Reverb:

```env
BROADCAST_CONNECTION=reverb
REVERB_APP_ID=<same as Reverb box>
REVERB_APP_KEY=<same as Reverb box>
REVERB_APP_SECRET=<same as Reverb box>
REVERB_HOST=ws.ws.lightpost.app
REVERB_PORT=443
REVERB_SCHEME=https

VITE_REVERB_APP_KEY="${REVERB_APP_KEY}"
VITE_REVERB_HOST="${REVERB_HOST}"
VITE_REVERB_PORT="${REVERB_PORT}"
VITE_REVERB_SCHEME="${REVERB_SCHEME}"
```

Rebuild assets (`npm run build`) after any change — Vite bakes values into the compiled bundle.

Common mistake: setting `VITE_REVERB_PORT=8080`. That's the internal port Reverb listens on; clients must use `443` because nginx terminates TLS and proxies internally.

## PECL uv Extension

`pecl install uv` fails because only beta releases exist. Install explicitly:

```bash
sudo apt install libuv1-dev
sudo pecl install channel://pecl.php.net/uv-0.3.0
```

Enable in both CLI and FPM php.ini (`extension=uv.so`). Verify:

```bash
php -m | grep uv
php --ri uv
```

## Verifying the Stack

From your laptop:

```bash
# TLS good?
openssl s_client -connect ws.ws.lightpost.app:443 -servername ws.ws.lightpost.app </dev/null 2>&1 | grep "Verify return"

# WebSocket upgrade succeeds? (expect HTTP/1.1 101)
curl -i -N \
  -H "Connection: Upgrade" -H "Upgrade: websocket" \
  -H "Sec-WebSocket-Key: dGhlIHNhbXBsZSBub25jZQ==" \
  -H "Sec-WebSocket-Version: 13" \
  https://ws.ws.lightpost.app/app/<REVERB_APP_KEY>
```

From the server:

```bash
# Reverb listening?
ss -tlnp | grep 8080

# Live connection count via the Pusher API
php artisan tinker --execute="\$a = app(\Laravel\Reverb\Contracts\ApplicationProvider::class)->all()->first(); dump(app('broadcast')->pusher(\$a->toArray())->get('/connections'));"
```

## Troubleshooting

| Symptom | Cause |
|---|---|
| `403` on `/pulse` | IP not in `PULSE_ALLOWED_IPS`, or proxies not trusted (check `request()->ip()`) |
| Incomplete `Collection` error in Pulse | `cache.serializable_classes` excluding needed class |
| WS URL contains `base64:...` | Client using `APP_KEY` instead of `REVERB_APP_KEY` |
| `NS_ERROR_NET_TIMEOUT` on port 8080 | Client `VITE_REVERB_PORT` set to `8080` instead of `443` |
| `HTTP/2 500` from nginx on `/app/...` | Forge Reverb integration not applied — likely the domain is an alias instead of its own site |
| One `101` per second, then disconnect | App key mismatch, or private channel auth failing on app side — check DevTools → WS → Messages |
