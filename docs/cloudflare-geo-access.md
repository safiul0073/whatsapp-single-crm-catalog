# Cloudflare geo-block (BD / IN / PK) + secure admin access — Free plan

Zone: `fashionfiest.com`. Everything below is configured in the Cloudflare dashboard; no app code depends on it.

## WAF custom rules (Security → WAF → Custom rules) — order matters

### 1. Skip — trusted callbacks
Action: **Skip → All remaining custom rules** (also tick "Log matching requests" off on Free).
```
(http.host eq "app.fashionfiest.com" and (
  starts_with(http.request.uri.path, "/webhooks/")
  or starts_with(http.request.uri.path, "/api/oauth2-callback")
  or http.request.uri.path contains "/callback"
))
```
Covers payment gateways (`/webhooks/payments/{gateway}`, `/webhooks/{gateway}`), Meta/WhatsApp, channel webhooks, OAuth callbacks. BD gateways/couriers call from BD IPs, so this must run before the block.

### 2. Skip — storefront server (Next.js SSR/BFF)
Action: **Skip → All remaining custom rules**
```
(ip.src in {STOREFRONT_SERVER_IPV4 STOREFRONT_SERVER_IPV6})
```

### 3. Block — BD/IN/PK (enable ONLY after admin access is verified)
Action: **Block**
```
(ip.src.country in {"BD" "IN" "PK"} and not (
  http.host eq "app.fashionfiest.com" and (
    starts_with(http.request.uri.path, "/admin")
    or starts_with(http.request.uri.path, "/dashboard")
    or starts_with(http.request.uri.path, "/login")
    or starts_with(http.request.uri.path, "/logout")
    or starts_with(http.request.uri.path, "/forgot-password")
    or starts_with(http.request.uri.path, "/reset-password")
    or starts_with(http.request.uri.path, "/email/")
    or starts_with(http.request.uri.path, "/two-factor")
    or starts_with(http.request.uri.path, "/sanctum/")
    or starts_with(http.request.uri.path, "/broadcasting/")
    or starts_with(http.request.uri.path, "/build/")
    or starts_with(http.request.uri.path, "/vendor/")
    or starts_with(http.request.uri.path, "/storage/")
    or starts_with(http.request.uri.path, "/api/v1/auth/")
    or starts_with(http.request.uri.path, "/api/v1/mobile/workspaces")
    or starts_with(http.request.uri.path, "/api/v1/mobile/devices")
  )
))
```
Blocks: the public storefront hosts (`fashionfiest.com`, `www`, tenant hosts proxied on this zone), and the customer APIs on `app.` (`/api/storefront/*`, `/api/commerce/*`, `/api/v1/mobile/storefront/*`, `/shop`, public pages).
Allows from BD: admin + user panels, auth, owner mobile endpoints, panel assets.

### 4. Block — internal API from anywhere but the storefront server
Action: **Block**
```
(http.host eq "app.fashionfiest.com" and starts_with(http.request.uri.path, "/internal/"))
```
(Storefront server is already skipped by rule 2.)

## Cloudflare Access (Zero Trust Free, ≤ 50 users)
- Login methods: One-time PIN (email) and/or Google.
- Application: Self-hosted, domain `app.fashionfiest.com`, paths `admin` and `dashboard` (each covers sub-paths). Session 24h.
- Policy "Owners & team": Action Allow, Include → Emails: owner + team list.
- Do **not** put `/api/*`, `/webhooks/*`, `/internal/*` behind Access (mobile app, gateways and storefront server cannot do the Access login).
- Laravel `2fa` middleware + roles/policies remain as second/third factors.

## Origin protection
- SSL/TLS → **Full (strict)** with a Cloudflare Origin Certificate on the server.
- SSL/TLS → Origin Server → **Authenticated Origin Pulls** on, and require the client cert at the web server; or allow only Cloudflare IP ranges (https://www.cloudflare.com/ips/) at the firewall / web server.
- All DNS records pointing at the origin must be **proxied** (orange cloud). Mail/FTP must not share the origin IP. If the IP was ever public, change it.
- `bootstrap/app.php` trusts `X-Forwarded-For` from any proxy (`at: '*'`). This is only safe while the origin accepts traffic from Cloudflare alone — keep the origin lock in place.

## Rollout
1. Create rules 1, 2, 4 enabled; rule 3 **disabled**.
2. Configure Access; BD owner logs into `/admin` and `/dashboard` (Access → password → 2FA). ✅ gate.
3. Enable rule 3 as **Managed Challenge**, verify from BD + US/CA VPN, then switch to **Block**.

## Test checklist
| From | Request | Expected |
|---|---|---|
| BD | `https://fashionfiest.com/` | Cloudflare 403 block |
| BD | `https://app.fashionfiest.com/admin` | Access login → app login → 2FA |
| BD | `https://app.fashionfiest.com/api/storefront/v1/catalog/...` | 403 |
| BD (gateway) | `POST /webhooks/payments/{gateway}` | App response (not CF 403) |
| US / CA VPN | Storefront browse + checkout | Works |
| Anywhere | `curl --resolve app.fashionfiest.com:443:ORIGIN_IP https://app.fashionfiest.com/` | Refused / TLS failure |

Security → Events shows blocked requests by country.

## Rollback
Disable rule 3 (instant). Access app can be deleted or its policy widened.
