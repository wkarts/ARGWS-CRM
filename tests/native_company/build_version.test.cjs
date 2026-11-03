'use strict';
const test=require('node:test'),assert=require('node:assert/strict'),fs=require('node:fs'),os=require('node:os'),path=require('node:path'),version=require('../../scripts/native-build-version.cjs');
test('production version uses canonical files, not the migration comment',()=>{
 const root=fs.mkdtempSync(path.join(os.tmpdir(),'crm-build-version-'));
 try{fs.mkdirSync(path.join(root,'application/config'),{recursive:true});fs.writeFileSync(path.join(root,'VERSION'),'4.7.4\n');fs.writeFileSync(path.join(root,'application/config/constants.php'),"define('ARGWS_VERSION', '4.7.4');\n");const config=path.join(root,'application/config/migration.php');fs.writeFileSync(config,"<?php\n$config['migration_version'] = 474; // arbitrary non-version comment\n");assert.equal(version(root),'4.7.4');
 fs.writeFileSync(config,"<?php\n$config['migration_version'] = 473;\n");assert.throws(()=>version(root),/mismatch/);
 fs.writeFileSync(config,"<?php\n$config['migration_version'] = 474;\n$config['migration_version'] = 474;\n");assert.throws(()=>version(root),/mismatch/);
 }finally{fs.rmSync(root,{recursive:true,force:true});}
});
