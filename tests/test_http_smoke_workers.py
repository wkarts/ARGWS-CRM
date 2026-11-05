"""The real PHP CLI test server must serve a request while connections are idle."""
import os
import pathlib
import signal
import socket
import subprocess
import tempfile
import time
import unittest
import urllib.request


class ConcurrentSmokeServer(unittest.TestCase):
    def test_idle_browser_connections_do_not_block_health_request(self):
        with tempfile.TemporaryDirectory() as directory:
            pathlib.Path(directory, 'index.php').write_text('<?php echo "synthetic-ready";')
            with socket.socket() as allocation:
                allocation.bind(('127.0.0.1', 0))
                port = allocation.getsockname()[1]
            process = subprocess.Popen(
                [os.environ.get('PHP_BINARY', 'php'), '-S', f'127.0.0.1:{port}', '-t', directory],
                env={**os.environ, 'PHP_CLI_SERVER_WORKERS': '4'},
                stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL, start_new_session=True,
            )
            held = []
            try:
                deadline = time.monotonic() + 5
                while True:
                    try:
                        held.append(socket.create_connection(('127.0.0.1', port), timeout=.2))
                        break
                    except OSError:
                        if time.monotonic() > deadline:
                            self.fail('PHP server did not start')
                        time.sleep(.02)
                held.append(socket.create_connection(('127.0.0.1', port), timeout=.2))
                # These sockets deliberately send no request, reproducing browser preconnections.
                with urllib.request.urlopen(f'http://127.0.0.1:{port}/', timeout=2) as reply:
                    self.assertEqual(reply.status, 200)
                    self.assertEqual(reply.read(), b'synthetic-ready')
            finally:
                for connection in held:
                    connection.close()
                os.killpg(process.pid, signal.SIGTERM)
                process.wait(timeout=5)


if __name__ == '__main__':
    unittest.main()
