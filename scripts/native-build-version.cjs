'use strict';
const fs=require('node:fs'),path=require('node:path');
module.exports=function nativeBuildVersion(root){
 const version=fs.readFileSync(path.join(root,'VERSION'),'utf8').trim();
 const constants=fs.readFileSync(path.join(root,'application/config/constants.php'),'utf8');
 const releases=[...constants.matchAll(/^define\('ARGWS_VERSION', '([0-9]+\.[0-9]+\.[0-9]+)'\);/gm)];
 const argws=releases.length===1?releases[0][1]:null;
 const source=fs.readFileSync(path.join(root,'application/config/migration.php'),'utf8');
 const matches=[...source.matchAll(/^\$config\['migration_version'\]\s*=\s*([0-9]+);/gm)];
 if(!/^[0-9]\.[0-9]\.[0-9]$/.test(version)||version!==argws||matches.length!==1||version.replaceAll('.','')!==matches[0][1])throw Error('native_build_version_mismatch');
 return version;
};
