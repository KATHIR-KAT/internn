<?php $page = 'setup'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>PHP SOCKS5 VPN — Mac Setup</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;700&family=Fira+Code:wght@400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="style.css">
</head>
<body>
<nav>
  <div class="brand">PHP <span>SOCKS5 VPN</span></div>
  <div class="navlinks">
    <a href="welcome.php">Welcome</a>
    <a class="active" href="setup.php">Mac Setup</a>
    <a href="index.php">Dashboard</a>
  </div>
</nav>

<main class="container">
<section class="hero">
  <span class="badge">DESKTOP SETUP GUIDE</span>
  <h1>Configure your <span class="gradient">device</span></h1>
  <p class="lead">Choose your operating system below. The SOCKS5 proxy runs locally and uses port <code>1080</code>.</p>
</section>

<div class="platform-tabs" role="tablist">
  <button class="platform-tab active" id="macTab" onclick="showPlatform('mac')"> Mac</button>
  <button class="platform-tab" id="windowsTab" onclick="showPlatform('windows')">▣ Windows</button>
</div>

<section id="macGuide" class="platform-guide">
  <div class="card">
    <h2> macOS Setup</h2>
    <p>Follow these steps to run the dashboard and configure the built-in macOS SOCKS proxy.</p>
  </div>

  <div class="card" style="margin-top:18px">
    <div class="step"><div class="num">1</div><div><h2>Check PHP</h2><p>Open Terminal and verify PHP is installed.</p><pre class="command">php -v</pre></div></div>
    <div class="step"><div class="num">2</div><div><h2>Open the project</h2><p>Move into the folder containing <code>index.php</code>.</p><pre class="command">cd /path/to/php_socks5_vpn</pre></div></div>
    <div class="step"><div class="num">3</div><div><h2>Start the web dashboard</h2><p>If port 8000 is already busy, use another web port such as 8001.</p><pre class="command">php -S 127.0.0.1:8001</pre><p>Open <code>http://127.0.0.1:8001/welcome.php</code>.</p></div></div>
    <div class="step"><div class="num">4</div><div><h2>Start the SOCKS5 server</h2><p>Open the Dashboard and click <strong>Start Proxy Server</strong>. The proxy listens on <code>127.0.0.1:1080</code>.</p></div></div>
  </div>

  <div class="card" style="margin-top:18px">
    <h2>macOS Network Settings</h2>
    <div class="step"><div class="num">1</div><div><p>Open <strong>System Settings → Network</strong>.</p></div></div>
    <div class="step"><div class="num">2</div><div><p>Select your active connection, such as <strong>Wi-Fi</strong>, then click <strong>Details…</strong>.</p></div></div>
    <div class="step"><div class="num">3</div><div><p>Open <strong>Proxies</strong> and enable <strong>SOCKS Proxy</strong>.</p></div></div>
    <div class="step"><div class="num">4</div><div><p>Enter these values:</p>
      <table><tr><th>Setting</th><th>Value</th></tr><tr><td>SOCKS Proxy Server</td><td><code>127.0.0.1</code></td></tr><tr><td>Port</td><td><code>1080</code></td></tr></table>
    </div></div>
    <div class="step"><div class="num">5</div><div><p>Click <strong>OK → Apply</strong>.</p></div></div>
  </div>

  <div class="card" style="margin-top:18px">
    <h2>Test the connection</h2>
    <pre class="command">curl --socks5 127.0.0.1:1080 https://example.com -I</pre>
    <p>Watch the Dashboard's Live Audit Logs while testing.</p>
  </div>
</section>

<section id="windowsGuide" class="platform-guide" style="display:none">
  <div class="card">
    <h2>▣ Windows Setup</h2>
    <p>Use the same PHP SOCKS5 server and configure Windows to use the local proxy.</p>
  </div>

  <div class="card" style="margin-top:18px">
    <div class="step"><div class="num">1</div><div><h2>Check PHP</h2><p>Open PowerShell or Command Prompt.</p><pre class="command">php -v</pre></div></div>
    <div class="step"><div class="num">2</div><div><h2>Open the project</h2><p>Change to your project folder.</p><pre class="command">cd C:\path\to\php_socks5_vpn</pre></div></div>
    <div class="step"><div class="num">3</div><div><h2>Start the web dashboard</h2><p>Use an available web port. Here we use 8001.</p><pre class="command">php -S 127.0.0.1:8001</pre><p>Open <code>http://127.0.0.1:8001/welcome.php</code>.</p></div></div>
    <div class="step"><div class="num">4</div><div><h2>Start the SOCKS5 server</h2><p>From the Dashboard click <strong>Start Proxy Server</strong>. The proxy uses <code>127.0.0.1:1080</code>.</p></div></div>
  </div>

  <div class="card" style="margin-top:18px">
    <h2>Windows Proxy Settings</h2>
    <div class="step"><div class="num">1</div><div><p>Open <strong>Settings → Network & internet → Proxy</strong>.</p></div></div>
    <div class="step"><div class="num">2</div><div><p>Under <strong>Manual proxy setup</strong>, enable <strong>Use a proxy server</strong>.</p></div></div>
    <div class="step"><div class="num">3</div><div><p>Enter:</p>
      <table><tr><th>Setting</th><th>Value</th></tr><tr><td>Address</td><td><code>127.0.0.1</code></td></tr><tr><td>Port</td><td><code>1080</code></td></tr></table>
    </div></div>
    <div class="step"><div class="num">4</div><div><p>Save the settings. If your Windows version does not provide a SOCKS-specific field, note that its built-in proxy setting may be HTTP/HTTPS rather than SOCKS5; use a SOCKS5-capable application or client when required.</p></div></div>
  </div>

  <div class="card" style="margin-top:18px">
    <h2>Test from PowerShell</h2>
    <pre class="command">curl.exe --socks5 127.0.0.1:1080 https://example.com -I</pre>
    <p>Check the Dashboard's Live Audit Logs for the connection.</p>
  </div>
</section>

<div class="warn" style="margin-top:18px">
<strong>Important:</strong> The current server binds to <code>127.0.0.1</code>, so it is intended for the same computer. It is not exposed to other devices on your network.
</div>

<div class="actions">
  <a class="btn primary" href="index.php">Open Dashboard →</a>
  <a class="btn secondary" href="welcome.php">Back to Welcome</a>
</div>
</main>
<footer>PHP SOCKS5 Tunnel VPN • Mac Setup</footer>

<script>
function showPlatform(platform){
  const mac = document.getElementById('macGuide');
  const win = document.getElementById('windowsGuide');
  const macTab = document.getElementById('macTab');
  const winTab = document.getElementById('windowsTab');
  const isMac = platform === 'mac';
  mac.style.display = isMac ? 'block' : 'none';
  win.style.display = isMac ? 'none' : 'block';
  macTab.classList.toggle('active', isMac);
  winTab.classList.toggle('active', !isMac);
  history.replaceState(null, '', isMac ? 'setup.php#mac' : 'setup.php#windows');
}
if(location.hash === '#windows') showPlatform('windows');
</script>

</body>
</html>
