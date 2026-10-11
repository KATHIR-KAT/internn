<?php

if (isset($_GET['action'])) {
    header('Content-Type: application/json');
    $action = $_GET['action'];
    $pidFile = __DIR__ . '/socks5.pid';
    $logFile = __DIR__ . '/socks5.log';
    $statsFile = __DIR__ . '/stats.json';

   
    $isServerRunning = function() use ($pidFile) {
        if (!file_exists($pidFile)) return false;
        $pid = trim(file_get_contents($pidFile));
        if (empty($pid) || !is_numeric($pid)) return false;
       
        $res = shell_exec("ps -p $pid");
        return (strpos($res, $pid) !== false);
    };

    if ($action === 'status') {
        $running = false;
        $pid = null;

  
        if (file_exists($pidFile)) {
            $candidatePid = intval(trim(@file_get_contents($pidFile)));
            if ($candidatePid > 0) {
                if (function_exists('posix_kill')) {
                    $running = @posix_kill($candidatePid, 0);
                }
                if ($running) {
                    $pid = $candidatePid;
                }
            }
        }

        

        if (!$running) {
            $probe = @fsockopen('127.0.0.1', 1080, $errno, $errstr, 0.25);
            if ($probe !== false) {
                fclose($probe);
                $running = true;
            }
        }

        if ($running && file_exists($statsFile)) {
            $stats = json_decode(file_get_contents($statsFile), true);
            if (is_array($stats)) {
                $active_connections = $stats['active_connections'] ?? 0;
                $total_bytes = $stats['total_bytes'] ?? 0;
                $connections = $stats['connections'] ?? [];
            }
        }
        echo json_encode([
            'running' => $running,
            'pid' => (file_exists($pidFile) && is_numeric(trim(file_get_contents($pidFile)))) ? intval(trim(file_get_contents($pidFile))) : null,
            'active_connections' => $active_connections,
            'total_bytes' => $total_bytes,
            'connections' => $connections
        ]);
        exit;
    }

    if ($action === 'start') {
        if ($isServerRunning()) {
            echo json_encode(['success' => false, 'message' => 'Server is already running']);
            exit;
        }
        @unlink($logFile);
        @unlink($statsFile);
        
        
        $cmd = "nohup php " . escapeshellarg(__DIR__ . '/socks5_server.php') . " --port=1080 > " . escapeshellarg($logFile) . " 2>&1 & echo $!";
        $pid = trim(shell_exec($cmd));
        
        if ($pid && is_numeric($pid)) {
            file_put_contents($pidFile, $pid);
            echo json_encode(['success' => true, 'pid' => intval($pid)]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to launch server process']);
        }
        exit;
    }

    if ($action === 'stop') {
        if (file_exists($pidFile)) {
            $pid = trim(file_get_contents($pidFile));
            if (!empty($pid) && is_numeric($pid)) {
                
                shell_exec("kill $pid");
                

                for ($i = 0; $i < 10; $i++) {
                    $res = shell_exec("ps -p $pid");
                    if (strpos($res, $pid) === false) break;
                    usleep(100000);
                }
                
            
                $res = shell_exec("ps -p $pid");
                if (strpos($res, $pid) !== false) {
                    shell_exec("kill -9 $pid");
                }
            }
            @unlink($pidFile);
        }
        @unlink($statsFile);
        echo json_encode(['success' => true]);
        exit;
    }

    if ($action === 'logs') {
        $offset = isset($_GET['offset']) ? intval($_GET['offset']) : 0;
        $logs = '';
        $newOffset = 0;
        
        if (file_exists($logFile)) {
            clearstatcache(true, $logFile);
            $newOffset = filesize($logFile);
            if ($newOffset > $offset) {
                $fp = fopen($logFile, 'rb');
                if ($fp) {
                    fseek($fp, $offset);
                    $logs = fread($fp, $newOffset - $offset);
                    fclose($fp);
                }
            } else {
                $newOffset = $offset;
            }
        }
        echo json_encode([
            'logs' => $logs,
            'offset' => $newOffset
        ]);
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PHP SOCKS5 VPN Dashboard</title>
    <meta name="description" content="A real-time admin web panel to manage, monitor, and audit the lightweight PHP-based SOCKS5 Proxy VPN server.">
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700&family=Fira+Code:wght@400;500&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --bg-color: #0b0f19;
            --card-bg: rgba(22, 28, 45, 0.7);
            --border-color: rgba(255, 255, 255, 0.08);
            --primary: #8b5cf6;
            --primary-glow: rgba(139, 92, 246, 0.4);
            --success: #10b981;
            --success-glow: rgba(16, 185, 129, 0.4);
            --danger: #ef4444;
            --danger-glow: rgba(239, 68, 68, 0.4);
            --text-main: #f3f4f6;
            --text-muted: #9ca3af;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            background-color: var(--bg-color);
            color: var(--text-main);
            font-family: 'Outfit', sans-serif;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            overflow-x: hidden;
            background-image: 
                radial-gradient(circle at 10% 20%, rgba(139, 92, 246, 0.08) 0%, transparent 40%),
                radial-gradient(circle at 90% 80%, rgba(16, 185, 129, 0.06) 0%, transparent 40%);
        }

        header {
            padding: 2.5rem 2rem 1.5rem;
            max-width: 1200px;
            width: 100%;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .logo-section h1 {
            font-size: 1.8rem;
            font-weight: 700;
            background: linear-gradient(135deg, #a78bfa 0%, #34d399 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            letter-spacing: -0.5px;
        }

        .logo-section p {
            font-size: 0.9rem;
            color: var(--text-muted);
            margin-top: 0.2rem;
        }

        .badge-port {
            background: rgba(139, 92, 246, 0.15);
            border: 1px solid rgba(139, 92, 246, 0.3);
            color: #c084fc;
            padding: 0.4rem 0.8rem;
            border-radius: 9999px;
            font-size: 0.85rem;
            font-weight: 600;
            letter-spacing: 0.5px;
        }

        main {
            flex-grow: 1;
            max-width: 1200px;
            width: 100%;
            margin: 0 auto;
            padding: 0 2rem 3rem;
            display: grid;
            grid-template-columns: 1fr;
            gap: 2rem;
        }

        @media (min-width: 900px) {
            main {
                grid-template-columns: 350px 1fr;
            }
        }

        .sidebar {
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
        }

        .card {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 16px;
            padding: 1.5rem;
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            transition: all 0.3s ease;
        }

        .card:hover {
            border-color: rgba(255, 255, 255, 0.15);
            box-shadow: 0 8px 32px 0 rgba(0, 0, 0, 0.3);
        }

        .card-title {
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: var(--text-muted);
            font-weight: 600;
            margin-bottom: 1rem;
        }

        .status-container {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .status-indicator {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background-color: var(--danger);
            box-shadow: 0 0 12px var(--danger-glow);
            transition: all 0.5s ease;
        }

        .status-indicator.running {
            background-color: var(--success);
            box-shadow: 0 0 12px var(--success-glow);
            animation: pulse 2s infinite;
        }

        .status-text {
            font-size: 1.4rem;
            font-weight: 600;
        }

        @keyframes pulse {
            0% {
                transform: scale(0.9);
                opacity: 0.8;
            }
            50% {
                transform: scale(1.1);
                opacity: 1;
                box-shadow: 0 0 18px var(--success-glow);
            }
            100% {
                transform: scale(0.9);
                opacity: 0.8;
            }
        }

        .btn {
            width: 100%;
            padding: 0.9rem;
            border: none;
            border-radius: 12px;
            font-family: 'Outfit', sans-serif;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 0.5rem;
            color: white;
        }

        .btn:active {
            transform: scale(0.97);
        }

        .btn-start {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            box-shadow: 0 4px 14px 0 rgba(16, 185, 129, 0.3);
        }

        .btn-start:hover {
            box-shadow: 0 6px 20px 0 rgba(16, 185, 129, 0.5);
            background: linear-gradient(135deg, #34d399 0%, #059669 100%);
        }

        .btn-stop {
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
            box-shadow: 0 4px 14px 0 rgba(239, 68, 68, 0.3);
        }

        .btn-stop:hover {
            box-shadow: 0 6px 20px 0 rgba(239, 68, 68, 0.5);
            background: linear-gradient(135deg, #f87171 0%, #dc2626 100%);
        }

        .stat-group {
            display: flex;
            flex-direction: column;
            gap: 1.25rem;
        }

        .stat-item {
            border-top: 1px solid var(--border-color);
            padding-top: 1rem;
        }

        .stat-item:first-child {
            border-top: none;
            padding-top: 0;
        }

        .stat-value {
            font-size: 1.8rem;
            font-weight: 700;
            margin-top: 0.25rem;
            font-variant-numeric: tabular-nums;
        }

        .console-container {
            display: flex;
            flex-direction: column;
            height: 550px;
        }

        .console-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid var(--border-color);
            padding-bottom: 1rem;
            margin-bottom: 1rem;
        }

        .console-title {
            font-size: 0.9rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .console-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background-color: var(--text-muted);
        }

        .console-dot.active {
            background-color: var(--success);
            box-shadow: 0 0 8px var(--success-glow);
        }

        .console-actions {
            display: flex;
            gap: 0.5rem;
        }

        .btn-mini {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid var(--border-color);
            color: var(--text-muted);
            padding: 0.3rem 0.6rem;
            border-radius: 6px;
            font-size: 0.75rem;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .btn-mini:hover {
            background: rgba(255, 255, 255, 0.1);
            color: var(--text-main);
        }

        .console-body {
            flex-grow: 1;
            background-color: rgba(5, 7, 13, 0.95);
            border: 1px solid rgba(255, 255, 255, 0.05);
            border-radius: 12px;
            padding: 1.25rem;
            overflow-y: auto;
            font-family: 'Fira Code', monospace;
            font-size: 0.85rem;
            line-height: 1.5;
            display: flex;
            flex-direction: column;
            gap: 0.4rem;
            box-shadow: inset 0 4px 20px rgba(0, 0, 0, 0.8);
        }

        .console-body::-webkit-scrollbar {
            width: 8px;
        }

        .console-body::-webkit-scrollbar-track {
            background: rgba(0, 0, 0, 0.2);
            border-radius: 12px;
        }

        .console-body::-webkit-scrollbar-thumb {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 12px;
        }

        .console-body::-webkit-scrollbar-thumb:hover {
            background: rgba(255, 255, 255, 0.25);
        }

        /* Log line colors parsed from ANSI */
        .log-line {
            white-space: pre-wrap;
            word-break: break-all;
        }
        .text-rose-500 { color: #f43f5e; }
        .text-emerald-400 { color: #34d399; }
        .text-amber-400 { color: #fbbf24; }
        .text-sky-400 { color: #38bdf8; }
        .text-cyan-400 { color: #22d3ee; }
        .text-slate-500 { color: #64748b; }
        .font-semibold { font-weight: 600; }

        .empty-log-message {
            color: var(--text-muted);
            font-style: italic;
            text-align: center;
            margin-top: auto;
            margin-bottom: auto;
        }

        footer {
            padding: 2rem;
            text-align: center;
            font-size: 0.8rem;
            color: var(--text-muted);
            border-top: 1px solid var(--border-color);
            max-width: 1200px;
            width: 100%;
            margin: 0 auto;
        }

        footer a {
            color: var(--primary);
            text-decoration: none;
        }

        footer a:hover {
            text-decoration: underline;
        }
            
        .live-connections-card { grid-column: 1 / -1; }
        .live-count { color: var(--success); font-size: .8rem; font-weight: 600; background: rgba(16,185,129,.1); border: 1px solid rgba(16,185,129,.2); padding: .35rem .65rem; border-radius: 999px; }
        .connections-table-wrap { overflow-x: auto; }
        .connections-table { width: 100%; border-collapse: collapse; min-width: 760px; }
        .connections-table th, .connections-table td { text-align: left; padding: .85rem .75rem; border-bottom: 1px solid var(--border-color); white-space: nowrap; }
        .connections-table th { color: var(--text-muted); font-size: .75rem; text-transform: uppercase; letter-spacing: .7px; }
        .connections-table td { font-family: 'Fira Code', monospace; font-size: .78rem; color: var(--text-main); }
        .connection-client { color: #a78bfa; font-weight: 600; }
        .connection-target { color: #67e8f9; }
        .connection-traffic { color: #86efac; }
        .empty-connections { text-align: center !important; color: var(--text-muted) !important; padding: 2rem !important; }
</style>
</head>
<body>

    <nav style="max-width:1200px;width:100%;margin:0 auto;padding:18px 2rem 0;display:flex;justify-content:flex-end;gap:8px;flex-wrap:wrap;">
    <a href="welcome.php" style="color:#9ca3af;text-decoration:none;padding:8px 12px;border-radius:8px;">Welcome</a>
    <a href="setup.php" style="color:#9ca3af;text-decoration:none;padding:8px 12px;border-radius:8px;">Mac Setup</a>
    <a href="index.php" style="color:#f3f4f6;text-decoration:none;background:rgba(255,255,255,.07);padding:8px 12px;border-radius:8px;">Dashboard</a>
</nav>

    <header>
        <div class="logo-section">
            <h1>PHP SOCKS5 Proxy VPN</h1>
            <p>Admin Control Dashboard</p>
        </div>
        <div class="badge-port">PORT 1080</div>
    </header>

    <main>
        <!-- Sidebar Controls -->
        <section class="sidebar">
            <!-- Server Manager Card -->
            <div class="card" id="control-card">
                <h2 class="card-title">Server Controls</h2>
                <div class="status-container">
                    <div id="status-indicator" class="status-indicator"></div>
                    <span id="status-text" class="status-text">Checking...</span>
                </div>
                
                <button id="toggle-btn" class="btn btn-start" onclick="toggleServer()" disabled>
                    <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" id="btn-icon">
                        <polygon points="5 3 19 12 5 21 5 3" fill="currentColor"></polygon>
                    </svg>
                    <span id="btn-text">Please Wait...</span>
                </button>
            </div>

            <!-- Stats Monitor Card -->
            <div class="card">
                <h2 class="card-title">Proxy Statistics</h2>
                <div class="stat-group">
                    <div class="stat-item">
                        <span style="color: var(--text-muted); font-size: 0.85rem;">Active Connections</span>
                        <div id="active-connections" class="stat-value">0</div>
                    </div>
                    <div class="stat-item">
                        <span style="color: var(--text-muted); font-size: 0.85rem;">Data Transferred</span>
                        <div id="data-transferred" class="stat-value">0 Bytes</div>
                    </div>
                    <div class="stat-item" id="pid-container" style="display: none;">
                        <span style="color: var(--text-muted); font-size: 0.85rem;">System Process PID</span>
                        <div id="server-pid" class="stat-value" style="font-size: 1.2rem; font-family: monospace; margin-top: 0.1rem;">-</div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Live Terminal Log Monitor -->
        <section class="card console-container">
            <div class="console-header">
                <div class="console-title">
                    <div id="console-dot" class="console-dot"></div>
                    <span>Live Audit Logs</span>
                </div>
                <div class="console-actions">
                    <button class="btn-mini" onclick="clearConsole()">Clear Screen</button>
                    <button class="btn-mini" id="scroll-lock-btn" onclick="toggleScrollLock()">Auto-Scroll: ON</button>
                </div>
            </div>
            
            <div id="console-body" class="console-body">
                <div id="empty-message" class="empty-log-message">Console idle. Start the proxy server to stream connection details.</div>
            </div>
        </section>

        <section class="card live-connections-card">
            <div class="console-header">
                <div class="console-title"><span>👥 Live Connections</span></div>
                <span class="live-count" id="live-count">0 active</span>
            </div>
            <div class="connections-table-wrap">
                <table class="connections-table">
                    <thead><tr><th>Client</th><th>Source</th><th>Destination</th><th>Started</th><th>Traffic</th></tr></thead>
                    <tbody id="connections-body"><tr><td colspan="5" class="empty-connections">No active connections</td></tr></tbody>
                </table>
            </div>
        </section>
    </main>

    <footer>
        <p>Pure PHP SOCKS5 Tunnel VPN. Built for secure, lightweight routing.</p>
    </footer>

    <script>
        let serverRunning = false;
        let logOffset = 0;
        let pollTimer = null;
        let logTimer = null;
        let scrollLock = true;

        const statusIndicator = document.getElementById('status-indicator');
        const statusText = document.getElementById('status-text');
        const toggleBtn = document.getElementById('toggle-btn');
        const btnText = document.getElementById('btn-text');
        const btnIcon = document.getElementById('btn-icon');
        const activeConnections = document.getElementById('active-connections');
        const dataTransferred = document.getElementById('data-transferred');
        const serverPid = document.getElementById('server-pid');
        const pidContainer = document.getElementById('pid-container');
        const consoleBody = document.getElementById('console-body');
        const consoleDot = document.getElementById('console-dot');
        const emptyMessage = document.getElementById('empty-message');

        // Parse CLI ANSI escape codes into styled HTML
        function ansiToHtml(text) {
            let escaped = text
                .replace(/&/g, "&amp;")
                .replace(/</g, "&lt;")
                .replace(/>/g, "&gt;");

            return escaped.replace(/\u001b\[([0-9;]+)m/g, (match, code) => {
                switch (code) {
                    case '31': return '<span class="text-rose-500 font-semibold">';
                    case '32': return '<span class="text-emerald-400 font-semibold">';
                    case '33': return '<span class="text-amber-400 font-semibold">';
                    case '34': return '<span class="text-sky-400 font-semibold">';
                    case '36': return '<span class="text-cyan-400 font-semibold">';
                    case '90': return '<span class="text-slate-500">';
                    case '0':
                    case 'reset':
                        return '</span>';
                    default: return '';
                }
            });
        }

        // Format bytes helper
        function formatBytes(bytes) {
            if (bytes === 0) return '0 Bytes';
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
        }

        // Fetch server status and stats
        async function fetchStatus() {
            try {
                const response = await fetch('index.php?action=status');
                const data = await response.json();
                
                updateUIState(data.running, data.pid);
                
                if (data.running) {
                    activeConnections.textContent = data.active_connections;
                    dataTransferred.textContent = formatBytes(data.total_bytes);
                    renderConnections(data.connections || []);
                } else {
                    activeConnections.textContent = '0';
                    dataTransferred.textContent = '0 Bytes';
                    renderConnections([]);
                }
            } catch (error) {
                console.error("Error fetching status:", error);
            }
        }

        function escapeHtml(value) {
            return String(value ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#039;');
        }

        function renderConnections(connections) {
            const body = document.getElementById('connections-body');
            const count = document.getElementById('live-count');
            if (!body || !count) return;
            count.textContent = `${connections.length} active`;
            if (!connections.length) {
                body.innerHTML = '<tr><td colspan="5" class="empty-connections">No active connections</td></tr>';
                return;
            }
            body.innerHTML = connections.map(c => {
                const up = Number(c.bytes_up || 0), down = Number(c.bytes_down || 0);
                return `<tr>
                    <td class="connection-client">#${escapeHtml(c.id)}</td>
                    <td>${escapeHtml(c.client_ip || '-')} : ${escapeHtml(c.client_port || '-')}</td>
                    <td class="connection-target">${escapeHtml(c.target || '-')}</td>
                    <td>${escapeHtml(c.started_at || '-')}</td>
                    <td class="connection-traffic">↑ ${formatBytes(up)} / ↓ ${formatBytes(down)}<br><small>${formatBytes(up+down)} total</small></td>
                </tr>`;
            }).join('');
        }

        // Fetch logs incrementally
        async function fetchLogs() {
            try {
                const response = await fetch(`index.php?action=logs&offset=${logOffset}`);
                const data = await response.json();
                
                if (data.logs && data.logs.trim() !== '') {
                    if (emptyMessage) {
                        emptyMessage.style.display = 'none';
                    }
                    
                    const lines = data.logs.split("\n");
                    lines.forEach(line => {
                        if (line.trim() !== '') {
                            const div = document.createElement('div');
                            div.className = 'log-line';
                            div.innerHTML = ansiToHtml(line);
                            consoleBody.appendChild(div);
                        }
                    });

                    if (scrollLock) {
                        consoleBody.scrollTop = consoleBody.scrollHeight;
                    }
                }
                logOffset = data.offset;
            } catch (error) {
                console.error("Error fetching logs:", error);
            }
        }

        // Update UI components depending on status
        function updateUIState(running, pid) {
            serverRunning = running;
            toggleBtn.disabled = false;

            if (running) {
                statusIndicator.className = 'status-indicator running';
                statusText.textContent = 'Running';
                statusText.style.color = 'var(--success)';
                
                toggleBtn.className = 'btn btn-stop';
                btnText.textContent = 'Stop Proxy Server';
                btnIcon.innerHTML = `<rect x="4" y="4" width="16" height="16" fill="currentColor"></rect>`;
                
                pidContainer.style.display = 'block';
                serverPid.textContent = pid;
                consoleDot.className = 'console-dot active';

                // Trigger faster logs fetching when running
                if (!logTimer) {
                    logTimer = setInterval(fetchLogs, 500);
                }
            } else {
                statusIndicator.className = 'status-indicator';
                statusText.textContent = 'Stopped';
                statusText.style.color = 'var(--danger)';
                
                toggleBtn.className = 'btn btn-start';
                btnText.textContent = 'Start Proxy Server';
                btnIcon.innerHTML = `<polygon points="5 3 19 12 5 21 5 3" fill="currentColor"></polygon>`;
                
                pidContainer.style.display = 'none';
                serverPid.textContent = '-';
                consoleDot.className = 'console-dot';

                if (logTimer) {
                    clearInterval(logTimer);
                    logTimer = null;
                }
            }
        }

        // Click handler to toggle SOCKS5 proxy
        async function toggleServer() {
            toggleBtn.disabled = true;
            btnText.textContent = 'Processing...';
            
            const action = serverRunning ? 'stop' : 'start';
            try {
                const response = await fetch(`index.php?action=${action}`, { method: 'POST' });
                const data = await response.json();
                
                if (action === 'start') {
                    if (data.success) {
                        logOffset = 0;
                        clearConsole();
                        if (emptyMessage) emptyMessage.style.display = 'none';
                        const startNotice = document.createElement('div');
                        startNotice.className = 'log-line text-emerald-400 font-semibold';
                        startNotice.textContent = `[Control] Launching proxy server (PID: ${data.pid})...`;
                        consoleBody.appendChild(startNotice);
                    } else {
                        alert(`Start failed: ${data.message}`);
                    }
                } else {
                    const stopNotice = document.createElement('div');
                    stopNotice.className = 'log-line text-rose-500 font-semibold';
                    stopNotice.textContent = `[Control] Shutdown signal dispatched.`;
                    consoleBody.appendChild(stopNotice);
                }
                
                await fetchStatus();
            } catch (error) {
                console.error("Action error:", error);
                toggleBtn.disabled = false;
            }
        }

        // Clear terminal screen
        function clearConsole() {
            // Keep empty message if server is stopped and screen cleared
            consoleBody.innerHTML = '';
            if (!serverRunning) {
                consoleBody.appendChild(emptyMessage);
                emptyMessage.style.display = 'block';
            }
        }

        // Toggle Auto-Scroll Lock
        function toggleScrollLock() {
            scrollLock = !scrollLock;
            const btn = document.getElementById('scroll-lock-btn');
            btn.textContent = `Auto-Scroll: ${scrollLock ? 'ON' : 'OFF'}`;
            if (scrollLock) {
                consoleBody.scrollTop = consoleBody.scrollHeight;
            }
        }

        // Initialize dashboard
        async function init() {
            await fetchStatus();
            if (serverRunning) {
                await fetchLogs();
            }
            pollTimer = setInterval(fetchStatus, 1500);
        }

        window.onload = init;
    </script>
</body>
</html>
