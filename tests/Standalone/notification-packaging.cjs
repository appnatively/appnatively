const fs=require('node:fs');
const path=require('node:path');
const root=path.resolve(__dirname,'../..');
const {buildFiles,buildIgnoreFiles}=require(path.join(root,'dev-tools/utils.js'));
for(const file of ['uninstall.php','resources/js','readme.txt','LICENSE','app','config','vendor']) {
 if(!buildFiles.some(entry=>entry.source===file))throw Error('Missing build source '+file);
 if(!fs.existsSync(path.join(root,file)))throw Error('Missing source '+file);
}
if(buildFiles.some(entry=>entry.source==='tests'))throw Error('Tests must not ship');
if(!buildIgnoreFiles.some(entry=>entry.endsWith('/**/tests')))throw Error('Dependency test folders must be excluded');
const version=fs.readFileSync(path.join(root,'appnatively.php'),'utf8').match(/Version:\s+([^\s]+)/)?.[1];
const stable=fs.readFileSync(path.join(root,'readme.txt'),'utf8').match(/Stable tag:\s+([^\s]+)/)?.[1];
if(!version || version!==stable)throw Error('Plugin and readme versions must agree');
if(buildIgnoreFiles.some(entry=>/\/(?:LICENSE|license\.txt|\*\.md)$/.test(entry)))throw Error('Dependency license notices must ship');
console.log('PASS: release inputs include uninstall, bridge and licenses; tests excluded; versions agree');
