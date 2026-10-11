<?php
$page = 'welcome';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>PHP SOCKS5 VPN — Welcome</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;700&family=Fira+Code:wght@400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="style.css">
</head>
<body>
<nav>
  <div class="brand">PHP <span>SOCKS5 VPN</span></div>
  <div class="navlinks">
    <a class="active" href="welcome.php">Welcome</a>
    <a href="setup.php">Mac Setup</a>
    <a href="index.php">Dashboard</a>
  </div>
</nav>
<main class="container">
<section class="hero">
  <span class="badge">PURE PHP • SOCKS5 • MACOS</span>
  <h1>Welcome to <span class="gradient">PHP SOCKS5 VPN</span></h1>
  <p class="lead">A lightweight SOCKS5 proxy with a browser dashboard for starting, stopping and monitoring the local proxy server.</p>
  <div class="actions">
    <a class="btn primary" href="setup.php">Start Mac Setup →</a>
    <a class="btn secondary" href="index.php">Open Dashboard</a>
  </div>
</section>

<div class="grid">
  <div class="card"><h2> Lightweight</h2><p>Runs directly with PHP and uses non-blocking sockets with <code>stream_select()</code>.</p></div>
  <div class="card"><h2> SOCKS5</h2><p>Accepts SOCKS5 CONNECT requests and supports IPv4, IPv6 and domain-name destinations.</p></div>
  <div class="card"><h2> Live Monitoring</h2><p>The dashboard displays server status, active connections, transferred data and live logs.</p></div>
  <div class="card"><h2> macOS Ready</h2><p>Use the setup guide to configure the macOS SOCKS proxy as <code>127.0.0.1:1080</code>.</p></div>
</div>

<div class="card" style="margin-top:18px">
  <h2>How it works</h2>
  <p>The web dashboard controls the PHP SOCKS5 process. The proxy listens locally on port <code>1080</code>, and macOS applications configured to use that SOCKS proxy send their traffic through it.</p>
</div>
</main>
<footer>PHP SOCKS5 Tunnel VPN • Local Mac setup</footer>
</body>
</html>
