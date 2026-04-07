# Bolin EXU-230NX PTZ Controller

A web-based PTZ controller for the Bolin EXU-230NX camera, built for deployment on a LAMP stack.

## Features

- Pan / Tilt / Zoom / Focus controls with hold-to-move
- 16 configurable presets with snapshot thumbnails
- Drag-and-drop preset reordering
- Wiper control (single, continuous, off)
- SRT stream enable / disable
- Preset-only view mode
- Credentials cached in localStorage with auto-connect on page load

## Requirements

- Apache with PHP (cURL extension required)
- Camera accessible from the web server over HTTP

## Installation

```bash
git clone https://github.com/chrissabato/bolin-control.git /var/www/html/ptz
cd /var/www/html/ptz
sudo bash setup.sh
```

`setup.sh` creates the `snapshots/` directory and sets ownership to the web server user (`apache` on RHEL, `www-data` on Debian/Ubuntu).

## Files

| File | Description |
|---|---|
| `index.html` | Single-file app — all HTML, CSS, and JavaScript |
| `proxy.php` | PHP cURL proxy — forwards API calls to the camera and injects auth cookies |
| `snapshot.php` | Fetches a JPEG snapshot from the camera and saves it to `snapshots/` |
| `setup.sh` | Post-clone setup script for directory permissions |
| `snapshots/` | Server-side storage for preset thumbnail images (not tracked in git) |

## Camera API

The Bolin EXU-230NX uses a JSON API over HTTP:

- **Endpoint:** `POST http://[camera]/apiv2/[resource]`
- **Auth:** Login returns a token sent as `Cookie: Username=X;Token=Y` — injected by `proxy.php` since browsers cannot set Cookie headers directly
- **Password hashing:** `MD5(toUpperCase(SHA256(password)) + Salt)`

The wiper and snapshot use Pelco-compatible CGI endpoints:

```
GET /cgi-bin/set.cgi?pelco.controller.snglwiper=1   # single wipe
GET /cgi-bin/set.cgi?pelco.controller.contwiper=1   # continuous wipe
GET /cgi-bin/set.cgi?pelco.controller.wiper=0       # wiper off
GET /cgi-bin/set.cgi?pelco.controller.snapshot=1    # capture snapshot (returns JPEG)
```

These use HTTP Basic auth and are proxied through `proxy.php`.

## Usage

Open `http://[server]/ptz` in a browser, enter the camera IP and credentials, and click **Connect**. Credentials are saved in localStorage and used to auto-connect on subsequent page loads.

To reset saved credentials, click **Disconnect**.
