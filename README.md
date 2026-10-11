# PHP SOCKS5 VPN

## Quick start on Mac

```bash
cd /path/to/php_socks5_vpn
php -S 127.0.0.1:8001
```

Open:

`http://127.0.0.1:8001/welcome.php`

The web dashboard uses port 8001 in this example. The SOCKS5 proxy itself remains on `127.0.0.1:1080`.

## Files

- `welcome.php` — welcome page
- `setup.php` — Mac and Windows setup guide
- `index.php` — proxy dashboard
- `socks5_server.php` — SOCKS5 server
- `style.css` — shared setup-page styling

## macOS proxy

Server: `127.0.0.1`
Port: `1080`

## Windows

The guide explains Windows proxy configuration. Windows' built-in manual proxy settings are generally HTTP/HTTPS-oriented, so applications that specifically require SOCKS5 may need a SOCKS5-capable client/configuration.


## Step 1 fixed
The dashboard now recognizes a SOCKS5 server started manually from Terminal and reads live connection details from an absolute stats.json path.
