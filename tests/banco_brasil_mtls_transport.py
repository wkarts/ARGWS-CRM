#!/usr/bin/env python3
"""Real local TLS handshake with synthetic CA/client; no BB network or deployed ingress."""
from pathlib import Path
import concurrent.futures
import json
import os
import shutil
import socket
import ssl
import subprocess
import tempfile

ROOT = Path(__file__).resolve().parents[1]
PHP = os.environ.get('BB_TEST_PHP') or shutil.which('php')
if not PHP:
    raise SystemExit('A PHP binary with OpenSSL is required')

def openssl(*args):
    subprocess.run(['openssl', *map(str, args)], check=True, stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)

with tempfile.TemporaryDirectory(prefix='bb-synthetic-mtls-') as temporary:
    directory = Path(temporary)
    ca = directory / 'ca.pem'
    ca_key = directory / 'ca.key'
    openssl('req', '-x509', '-newkey', 'rsa:2048', '-nodes', '-keyout', ca_key, '-out', ca, '-days', '1', '-subj', '/CN=SYNTHETIC TEST CA', '-addext', 'basicConstraints=critical,CA:TRUE')

    def certificate(name, usage, san=None):
        key, request, cert, extension = (directory / (name + suffix) for suffix in ['.key', '.csr', '.pem', '.ext'])
        openssl('req', '-new', '-newkey', 'rsa:2048', '-nodes', '-keyout', key, '-out', request, '-subj', '/CN=' + name)
        extension.write_text('basicConstraints=critical,CA:FALSE\nkeyUsage=digitalSignature,keyEncipherment\nextendedKeyUsage=' + usage + '\n' + ('subjectAltName=' + san + '\n' if san else ''))
        openssl('x509', '-req', '-in', request, '-CA', ca, '-CAkey', ca_key, '-CAcreateserial', '-out', cert, '-days', '1', '-extfile', extension)
        return cert, key

    server_cert, server_key = certificate('localhost', 'serverAuth', 'DNS:localhost')
    client_cert, client_key = certificate('synthetic-bank-client', 'clientAuth')
    wrong_cert, wrong_key = directory / 'wrong.pem', directory / 'wrong.key'
    openssl('req', '-x509', '-newkey', 'rsa:2048', '-nodes', '-keyout', wrong_key, '-out', wrong_cert, '-days', '1', '-subj', '/CN=SYNTHETIC UNTRUSTED CLIENT')
    listener_context = ssl.SSLContext(ssl.PROTOCOL_TLS_SERVER)
    listener_context.minimum_version = ssl.TLSVersion.TLSv1_2
    listener_context.load_cert_chain(server_cert, server_key)
    listener_context.load_verify_locations(cafile=ca)
    listener_context.verify_mode = ssl.CERT_REQUIRED

    def exchange(client, headers=b''):
        with socket.socket() as listener:
            listener.bind(('127.0.0.1', 0))
            listener.listen(1)
            listener.settimeout(5)
            port = listener.getsockname()[1]

            def serve():
                try:
                    connection, _ = listener.accept()
                    with connection:
                        with listener_context.wrap_socket(connection, server_side=True) as verified:
                            peer = ssl.DER_cert_to_PEM_cert(verified.getpeercert(binary_form=True))
                            verified.recv(4096)
                            # Native TLS terminator supplies server variables. Request headers are never copied.
                            native = {'HTTPS': 'on', 'SSL_CLIENT_VERIFY': 'SUCCESS', 'SSL_CLIENT_CERT': peer}
                            code = "require $argv[1];$s=json_decode(stream_get_contents(STDIN),true,8,JSON_THROW_ON_ERROR);echo BancoBrasil\\MtlsEvidence::fromNativeTls($s,true)->fingerprint;"
                            result = subprocess.run([PHP, '-r', code, str(ROOT / 'modules/banco_brasil/libraries/BancoBrasil/MtlsEvidence.php')], input=json.dumps(native), text=True, capture_output=True, check=True)
                            verified.sendall(result.stdout.encode())
                            return result.stdout
                except ssl.SSLError:
                    return 'tls_denied'

            with concurrent.futures.ThreadPoolExecutor(max_workers=1) as executor:
                server_result = executor.submit(serve)
                context = ssl.create_default_context(cafile=ca)
                if client:
                    context.load_cert_chain(*client)
                try:
                    with socket.create_connection(('127.0.0.1', port), timeout=5) as raw:
                        with context.wrap_socket(raw, server_hostname='localhost') as tls:
                            tls.sendall(b'POST /banco_brasil/notifications HTTP/1.1\r\n' + headers + b'\r\n')
                            response = tls.recv(4096).decode()
                except (ssl.SSLError, ConnectionResetError):
                    response = 'tls_denied'
                return response, server_result.result(timeout=10)

    response, observed = exchange((client_cert, client_key))
    expected = subprocess.run(['openssl', 'x509', '-in', str(client_cert), '-noout', '-fingerprint', '-sha256'], check=True, capture_output=True, text=True).stdout.split('=', 1)[1].strip().replace(':', '').lower()
    assert response == observed == expected, 'Valid synthetic client TLS certificate must reach canonical PHP evidence'
    response, observed = exchange(None, b'SSL_CLIENT_VERIFY: SUCCESS\r\nSSL_CLIENT_CERT: invented\r\n')
    assert observed == 'tls_denied', 'Forged headers without client certificate must fail TLS handshake'
    response, observed = exchange((wrong_cert, wrong_key))
    assert observed == 'tls_denied', 'A client outside the configured CA must fail TLS handshake'
    print('BB local mTLS transport: 3 actual synthetic TLS cases passed; no deployed ingress or BB homologation.')
