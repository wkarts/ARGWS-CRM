const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');

const moduleRoot = path.join(__dirname, '..', 'modules', 'custom_links');

test('client iframe uses its escaped destination directly', () => {
  const view = fs.readFileSync(path.join(moduleRoot, 'views', 'client_iframe.php'), 'utf8');

  assert.match(view, /src="<\?php echo html_escape\(\$href\); \?>"/);
  assert.match(view, /referrerpolicy="no-referrer"/);
  assert.doesNotMatch(view, /custom_links_proxy/);
});

test('legacy proxy route returns 404 without loading or fetching a URL', () => {
  const controller = fs.readFileSync(path.join(moduleRoot, 'controllers', 'Custom_links_proxy.php'), 'utf8');

  assert.match(controller, /show_404\(\);/);
  assert.doesNotMatch(controller, /curl_(?:init|exec|setopt)|CURLOPT_|file_get_contents|fopen\s*\(|readfile\s*\(|stream_socket_client|header_remove|echo\s+\$response/i);
  assert.doesNotMatch(controller, /Custom_links_model|load\s*\(/);
});

test('the insecure proxy archive is not shipped', () => {
  assert.equal(fs.existsSync(path.join(moduleRoot, 'controllers', 'Custom_links_proxy.zip')), false);
});
