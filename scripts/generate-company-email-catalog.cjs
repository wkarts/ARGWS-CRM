// Builds shipped defaults only from the public installer seed. Never reads a running database.
const fs=require('node:fs');const path=require('node:path');const assert=require('node:assert/strict');
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
      rows.push({type:columns[1],slug:columns[2],language:columns[3],name:columns[4],subject:columns[5],message:columns[6],fromname:columns[7],fromemail:columns[8]==='NULL'?null:columns[8],plaintext:Number(columns[9]),active:Number(columns[10]),order:Number(columns[11])});
      whitespace();
      if (sql[cursor] !== ',') break;
      cursor++;
    }
  }
  return rows;
}

function catalog(sql){const rows=rowsFromSeed(sql).filter(row=>row.language==='english');assert.ok(rows.length>0);const seen=new Set();for(const row of rows){assert.ok(!seen.has(row.slug));seen.add(row.slug);}return rows;}
module.exports={catalog};
if(require.main===module){const root=path.resolve(__dirname,'..');const data=catalog(fs.readFileSync(path.join(root,'install/database.sql'),'utf8'));fs.writeFileSync(path.join(root,'application/services/email_templates_ptbr/company_defaults.json'),JSON.stringify(data,null,2)+'\n');console.log(data.length+' shipped English defaults extracted; PT-BR uses existing validated translation catalog.');}
