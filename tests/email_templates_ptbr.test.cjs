const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');

const ROOT = path.resolve(__dirname, '..');
const CATALOG = path.join(ROOT, 'application/services/email_templates_ptbr');
const read = (file) => fs.readFileSync(path.join(ROOT, file), 'utf8');

function rowsFromSeed(sql) {
  const rows = [];
  const insertRegex = /INSERT INTO [\x60]tblemailtemplates[\x60][^\n]*VALUES\n/g;
  let match;
  while ((match = insertRegex.exec(sql))) {
    let cursor = match.index + match[0].length;
    const whitespace = () => {
      while (/\s/.test(sql[cursor] || '')) cursor++;
    };
    const nextValue = () => {
      whitespace();
      if (sql[cursor] !== "'") {
        const start = cursor;
        while (cursor < sql.length && !/[,)]/.test(sql[cursor])) cursor++;
        return sql.slice(start, cursor).trim();
      }
      cursor++;
      let value = '';
      while (cursor < sql.length) {
        const char = sql[cursor++];
        if (char === '\\') {
          const following = sql[cursor++];
          value += ({ r: '\r', n: '\n', t: '\t', 0: '\0', b: '\b', Z: '\x1a' })[following] ?? following;
          continue;
        }
        if (char === "'") {
          if (sql[cursor] === "'") { value += "'"; cursor++; continue; }
          return value;
        }
        value += char;
      }
      throw new Error('Cadeia SQL não finalizada.');
    };
    while (true) {
      whitespace();
      if (sql[cursor] !== '(') break;
      cursor++;
      const columns = [];
      for (let i = 0; i < 12; i++) {
        columns.push(nextValue());
        whitespace();
        if (i !== 11) assert.equal(sql[cursor++], ',');
      }
      assert.equal(sql[cursor++], ')');
      rows.push({
        id: Number(columns[0]), slug: columns[2], language: columns[3],
        name: columns[4], subject: columns[5], message: columns[6],
      });
      whitespace();
      if (sql[cursor] !== ',') break;
      cursor++;
    }
  }
  return rows;
}

function normalize(node) {
  return node.replace(/\{[^{}]+\}/g, '%s')
    .replace(/&nbsp;|&#160;/gi, ' ')
    .replace(/\s+/g, ' ').trim();
}

function renderPreservingHtml(html, phrases) {
  const pieces = html.split(/(<[^>]*>)/g);
  const result = pieces.map((segment) => {
    if (!segment || segment.startsWith('<')) return segment;
    const key = normalize(segment);
    if (!key || !/[\p{L}]/u.test(key.replaceAll('%s', ''))) return segment;
    assert.ok(Object.hasOwn(phrases, key), 'Tradução ausente: ' + key);
    const vars = segment.match(/\{[^{}]+\}/g) || [];
    const target = phrases[key];
    assert.equal((target.match(/%s/g) || []).length, vars.length, 'Campos inconsistentes: ' + key);
    let index = 0;
    const translated = target.replace(/%s/g, () => vars[index++]);
    return (segment.match(/^\s*/u)?.[0] || '') + translated + (segment.match(/\s*$/u)?.[0] || '');
  }).join('');

  assert.deepEqual(result.match(/<[^>]*>/g) || [], html.match(/<[^>]*>/g) || []);
  assert.deepEqual(result.match(/\{[^{}]+\}/g) || [], html.match(/\{[^{}]+\}/g) || []);
  return result;
}

const models = rowsFromSeed(read('install/database.sql'));
const names = JSON.parse(fs.readFileSync(path.join(CATALOG, 'templates.json'), 'utf8'));
const dictionaries = ['phrases_01.json', 'phrases_02.json', 'phrases_03.json']
  .map((file) => JSON.parse(fs.readFileSync(path.join(CATALOG, file), 'utf8')));
const phrases = Object.assign({}, ...dictionaries);

test('os 82 modelos originais têm nomes e assuntos PT-BR sem perder campos', () => {
  assert.equal(models.length, 82);
  assert.equal(new Set(models.map((entry) => entry.slug)).size, 82);
  assert.equal(Object.keys(names).length, 82);
  for (const item of models) {
    assert.equal(item.language, 'english');
    assert.ok(names[item.slug]?.name, 'Nome não traduzido: ' + item.slug);
    assert.ok(names[item.slug]?.subject, 'Assunto não traduzido: ' + item.slug);
    assert.notEqual(names[item.slug].name, item.name);
    assert.deepEqual(
      (names[item.slug].subject.match(/\{[^{}]+\}/g) || []).sort(),
      (item.subject.match(/\{[^{}]+\}/g) || []).sort(),
      'Marcadores do assunto: ' + item.slug
    );
  }
});

test('cada nó de texto dos corpos HTML é traduzido; layout e merge fields ficam intactos', () => {
  assert.equal(Object.keys(phrases).length, 242);
  for (const item of models) {
    const translated = renderPreservingHtml(item.message, phrases);
    assert.notEqual(translated, item.message, 'Modelo ficou sem tradução: ' + item.slug);
    assert.ok(translated.length > 30, 'Modelo vazio: ' + item.slug);
    assert.doesNotMatch(translated, /\b(?:Kind Regards|Best Regards|Dear \{|You can view the|We have prepared the)\b/i,
      'Resto inglês em ' + item.slug);
  }
});

test('dicionário não contém campos ausentes, frases repetidas ou tradução em branco', () => {
  const seen = new Set();
  for (const group of dictionaries) {
    for (const [key, translated] of Object.entries(group)) {
      assert.ok(!seen.has(key), 'Frase duplicada: ' + key);
      seen.add(key);
      assert.ok(typeof translated === 'string' && translated.trim() !== '');
      assert.equal((translated.match(/%s/g) || []).length, (key.match(/%s/g) || []).length);
    }
  }
});

test('a migração apenas insere ausentes ou atualiza defaults intactos', () => {
  const migration = read('application/migrations/365_version_365.php');
  const engine = read('application/services/EmailTemplatesPtBr.php');
  assert.match(migration, /class Migration_Version_365 extends CI_Migration/);
  assert.match(migration, /EmailTemplatesPtBr::translateMessage/);
  assert.match(migration, /\$current === \$original/);
  assert.match(migration, /\$changes\[\$field\]/);
  assert.doesNotMatch(migration, /\bTRUNCATE\b|->delete\(/);
  assert.match(engine, /if \(\$oldTags\[0\] !== \$newTags\[0\] \|\| \$oldVariables\[0\] !== \$newVariables\[0\]\)/);
});
