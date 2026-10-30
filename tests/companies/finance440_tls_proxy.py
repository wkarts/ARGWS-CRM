#!/usr/bin/env python3
"""Disposable HTTPS protocol sink for Asaas browser fixtures; never forwards traffic."""
import argparse
import http.server
import ipaddress
import json
import os
import pathlib
import re
import ssl
import subprocess
import sys
import threading


def certificates(directory):
    directory.mkdir(parents=True, exist_ok=True)
    ca = directory / 'ca.pem'
    key = directory / 'server.key'
    cert = directory / 'server.pem'
    if not all(p.exists() for p in (ca, key, cert)):
        ext = directory / 'server.ext'
        ext.write_text('basicConstraints=critical,CA:FALSE\nkeyUsage=critical,digitalSignature,keyEncipherment\nextendedKeyUsage=serverAuth\nsubjectAltName=DNS:api-sandbox.asaas.com\n')
        commands = [
            ['openssl', 'req', '-x509', '-newkey', 'rsa:2048', '-nodes', '-days', '2', '-sha256', '-keyout', str(directory / 'ca.key'), '-out', str(ca), '-subj', '/CN=Disposable finance440 test CA', '-addext', 'basicConstraints=critical,CA:TRUE', '-addext', 'keyUsage=critical,keyCertSign,cRLSign'],
            ['openssl', 'req', '-new', '-newkey', 'rsa:2048', '-nodes', '-sha256', '-keyout', str(key), '-out', str(directory / 'server.csr'), '-subj', '/CN=api-sandbox.asaas.com'],
            ['openssl', 'x509', '-req', '-in', str(directory / 'server.csr'), '-CA', str(ca), '-CAkey', str(directory / 'ca.key'), '-CAcreateserial', '-days', '2', '-sha256', '-out', str(cert), '-extfile', str(ext)],
        ]
        for command in commands:
            subprocess.run(command, check=True, stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
        for secret in (directory / 'ca.key', key):
            secret.chmod(0o600)
    return ca, cert, key


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--cert-dir', type=pathlib.Path, required=True)
    parser.add_argument('--prepare-only', action='store_true')
    parser.add_argument('--ready-file', type=pathlib.Path)
    parser.add_argument('--events', type=pathlib.Path)
    source = parser.add_mutually_exclusive_group()
    source.add_argument('--state-file', type=pathlib.Path)
    source.add_argument('--state-command', help='JSON array containing a local read-only fixture command')
    parser.add_argument('--cwd', type=pathlib.Path)
    parser.add_argument('--bind', default='127.0.0.1', help='Loopback or an RFC1918 address of the disposable test network')
    args = parser.parse_args()
    try:
        address = ipaddress.IPv4Address(args.bind)
    except ipaddress.AddressValueError:
        parser.error('--bind must be an IPv4 loopback or RFC1918 address')
    if not address.is_loopback and not any(address in ipaddress.IPv4Network(network) for network in ('10.0.0.0/8', '172.16.0.0/12', '192.168.0.0/16')):
        parser.error('--bind must be an IPv4 loopback or RFC1918 address')
    ca, cert, key = certificates(args.cert_dir.resolve())
    if args.prepare_only:
        print(json.dumps({'ca': str(ca), 'certificate': str(cert)}), flush=True)
        return
    if not args.state_file and not args.state_command:
        parser.error('a synthetic state source is required')
    command = json.loads(args.state_command) if args.state_command else None
    if command is not None and (not isinstance(command, list) or not command or not all(isinstance(v, str) for v in command)):
        parser.error('--state-command must be a nonempty JSON string array')
    tls = ssl.SSLContext(ssl.PROTOCOL_TLS_SERVER)
    tls.minimum_version = ssl.TLSVersion.TLSv1_2
    tls.load_cert_chain(cert, key)
    lock = threading.Lock()

    def event(value):
        if args.events:
            with lock, args.events.open('a') as output:
                output.write(json.dumps(value, sort_keys=True) + '\n')

    def state():
        if command:
            completed = subprocess.run(command, cwd=args.cwd, env=os.environ, text=True, stdout=subprocess.PIPE, stderr=subprocess.PIPE, timeout=30)
            if completed.returncode:
                event({'kind': 'state_error', 'exit': completed.returncode})
                raise RuntimeError('Synthetic read-only state command failed')
            return json.loads(completed.stdout)
        return json.loads(args.state_file.read_text())

    class Provider(http.server.BaseHTTPRequestHandler):
        protocol_version = 'HTTP/1.1'

        def log_message(self, *unused):
            pass

        def respond(self, code, payload):
            body = json.dumps(payload, separators=(',', ':'), ensure_ascii=False).encode()
            self.send_response(code)
            self.send_header('Content-Type', 'application/json')
            self.send_header('Content-Length', str(len(body)))
            self.send_header('Connection', 'close')
            self.end_headers()
            self.wfile.write(body)
            self.close_connection = True

        def do_GET(self):
            if self.headers.get('Host') not in ('api-sandbox.asaas.com', 'api-sandbox.asaas.com:443'):
                self.respond(421, {'error': 'synthetic_host_denied'})
                return
            token = self.headers.get('access_token', '')
            if not re.fullmatch(r'synthetic-refund-browser-[ab]', token):
                self.respond(403, {'error': 'synthetic_identity_denied'})
                return
            try:
                account = state()['accounts'].get(token)
                if not account:
                    self.respond(403, {'error': 'synthetic_account_denied'})
                    return
                if self.path == '/v3/wallets/':
                    body = {'data': [{'id': account['wallet']}], 'hasMore': False}
                elif re.fullmatch(r'/v3/payments/pay_refund_browser_[ab]_[1-9][0-9]*', self.path):
                    body = account['payments'].get(self.path.rsplit('/', 1)[1])
                    if body is None:
                        self.respond(404, {'error': 'synthetic_payment_denied'})
                        return
                else:
                    self.respond(403, {'error': 'synthetic_path_denied'})
                    return
                self.respond(200, body)
                event({'kind': 'synthetic_get', 'path': self.path, 'letter': token[-1], 'status': 200, 'tls_version': self.connection.version()})
            except Exception as error:
                event({'kind': 'state_unconfirmed', 'error_type': type(error).__name__})
                self.respond(503, {'error': 'synthetic_state_unconfirmed'})

        def deny(self):
            event({'kind': 'method_denied', 'method': self.command})
            self.respond(405, {'error': 'synthetic_read_only'})

        do_POST = do_PUT = do_PATCH = do_DELETE = do_HEAD = do_OPTIONS = deny

    class Proxy(http.server.BaseHTTPRequestHandler):
        protocol_version = 'HTTP/1.1'

        def log_message(self, *unused):
            pass

        def do_CONNECT(self):
            if self.path != 'api-sandbox.asaas.com:443':
                event({'kind': 'connect_denied'})
                self.send_error(403, 'Only the synthetic sandbox host is accepted')
                self.close_connection = True
                return
            self.send_response(200, 'Connection established')
            self.end_headers()
            self.wfile.flush()
            self.close_connection = True
            try:
                connection = tls.wrap_socket(self.connection, server_side=True)
                try:
                    Provider(connection, self.client_address, self.server)
                finally:
                    connection.close()
            except (ssl.SSLError, ConnectionError, OSError) as error:
                event({'kind': 'tls_rejected', 'error_type': type(error).__name__})

        def do_GET(self):
            self.send_error(403, 'HTTPS CONNECT is required')

        do_POST = do_PUT = do_PATCH = do_DELETE = do_HEAD = do_OPTIONS = do_GET

    server = http.server.ThreadingHTTPServer((str(address), 0), Proxy)
    server.daemon_threads = True
    ready = {'proxy': 'http://' + str(address) + ':' + str(server.server_port), 'ca': str(ca), 'certificate': str(cert), 'host': 'api-sandbox.asaas.com', 'forwarding': False}
    if args.ready_file:
        args.ready_file.write_text(json.dumps(ready))
    print(json.dumps(ready), flush=True)
    try:
        server.serve_forever()
    finally:
        server.server_close()


if __name__ == '__main__':
    main()
