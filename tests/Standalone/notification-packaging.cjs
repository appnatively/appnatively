const fs=require('node:fs');
const path=require('node:path');
const root=path.resolve(__dirname,'../..');
const {buildFiles,buildIgnoreFiles}=require(path.join(root,'dev-tools/utils.js'));
for(const file of ['uninstall.php','resources/js','readme.txt','app','config','vendor']) {
 if(!buildFiles.some(entry=>entry.source===file))throw Error('Missing build source '+file);
 if(!fs.existsSync(path.join(root,file)))throw Error('Missing source '+file);
}
if(buildFiles.some(entry=>entry.source==='tests'))throw Error('Tests must not ship');
if(!buildIgnoreFiles.some(entry=>entry.endsWith('/**/tests')))throw Error('Dependency test folders must be excluded');
if(!fs.readFileSync(path.join(root,'appnatively.php'),'utf8').includes('Version:           0.0.10'))throw Error('Missing version bump');
if(!fs.readFileSync(path.join(root,'readme.txt'),'utf8').includes('Stable tag: 0.0.10'))throw Error('Readme version mismatch');
console.log('PASS: release inputs include uninstall and bridge; tests excluded; versions agree');
