#!/usr/bin/env node
/**
 * Bolin EXU-230NX CORS Proxy
 * Forwards requests to the camera, injecting the Cookie auth header
 * (browsers cannot set Cookie headers directly on cross-origin requests)
 *
 * Usage: node proxy.js
 * Then open index.html, enable "Use Proxy" and set proxy URL to http://localhost:8765
 */

const http = require('http');

const PROXY_PORT = 8765;

const server = http.createServer((req, res) => {
  // Always send CORS headers
  res.setHeader('Access-Control-Allow-Origin', '*');
  res.setHeader('Access-Control-Allow-Methods', 'GET, POST, OPTIONS');
  res.setHeader('Access-Control-Allow-Headers',
    'Content-Type, X-Cam-Host, X-Cam-Port, X-Cam-Token, X-Cam-Username');

  if (req.method === 'OPTIONS') {
    res.writeHead(204);
    res.end();
    return;
  }

  const targetHost = req.headers['x-cam-host'];
  const targetPort = parseInt(req.headers['x-cam-port'] || '80', 10);
  const token      = req.headers['x-cam-token'];
  const username   = req.headers['x-cam-username'] || 'admin';

  if (!targetHost) {
    res.writeHead(400);
    res.end(JSON.stringify({ error: 'Missing X-Cam-Host header' }));
    return;
  }

  let body = '';
  req.on('data', chunk => { body += chunk; });
  req.on('end', () => {
    const bodyBuf = Buffer.from(body, 'utf8');
    const fwdHeaders = {
      'Content-Type': 'application/json',
      'Content-Length': bodyBuf.length,
    };
    if (token) {
      fwdHeaders['Cookie'] = `Username=${username};Token=${token}`;
    }

    const options = {
      hostname: targetHost,
      port: targetPort,
      path: req.url,
      method: req.method,
      headers: fwdHeaders,
    };

    const proxyReq = http.request(options, (proxyRes) => {
      const out = {
        'Content-Type': proxyRes.headers['content-type'] || 'application/json',
        'Access-Control-Allow-Origin': '*',
      };
      res.writeHead(proxyRes.statusCode, out);
      proxyRes.pipe(res);
    });

    proxyReq.on('error', (e) => {
      console.error('Proxy error:', e.message);
      if (!res.headersSent) {
        res.writeHead(502, { 'Access-Control-Allow-Origin': '*' });
        res.end(JSON.stringify({ error: e.message }));
      }
    });

    proxyReq.write(bodyBuf);
    proxyReq.end();
  });
});

server.listen(PROXY_PORT, '127.0.0.1', () => {
  console.log(`\nBolin PTZ CORS Proxy listening on http://localhost:${PROXY_PORT}`);
  console.log('In the web controller: enable "Use Proxy" and set URL to http://localhost:8765\n');
});
