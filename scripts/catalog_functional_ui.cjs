// Static templates, not runtime instances or evidence that an action works.
const fs=require('fs'),path=require('path'),crypto=require('crypto');
const {parse}=require('@babel/parser');
const root=path.resolve(__dirname,'..'),files=fs.readdirSync(path.join(root,'resources/js/portal')).filter(n=>/\.(jsx|js)$/.test(n)&&!n.includes('.test.'));
const inventory=[];
for(const file of files){
 const relative='resources/js/portal/'+file,source=fs.readFileSync(path.join(root,relative),'utf8');
 const ast=parse(source,{sourceType:'module',plugins:['jsx']});
 const text=n=>n?source.slice(n.start,n.end):'';
 function walk(node,owner='module'){
  if(!node||typeof node!=='object')return;
  if(['FunctionDeclaration','FunctionExpression','ArrowFunctionExpression'].includes(node.type)&&node.id?.name)owner=node.id.name;
  if(node.type==='VariableDeclarator'&&node.id?.name&&['FunctionExpression','ArrowFunctionExpression'].includes(node.init?.type))owner=node.id.name;
  if(node.type==='JSXElement'){
   const tag=node.openingElement.name.name;
   if(['button','a','Link','form','Form','input','select','textarea','details','Action','Route'].includes(tag)){
    const attributes=Object.fromEntries(node.openingElement.attributes.filter(a=>a.type==='JSXAttribute').map(a=>[a.name.name,a.value?.type==='StringLiteral'?a.value.value:text(a.value)]));
    inventory.push({file:relative,component:owner,line:node.loc.start.line,kind:tag,attributes,label:text(node).slice(0,400),status:'NO VERIFICADO'});
   }
  }
  if(node.type==='CallExpression'&&['field','select'].includes(node.callee?.name))inventory.push({file:relative,component:owner,line:node.loc.start.line,kind:'configured-field',definition:text(node),status:'NO VERIFICADO'});
  for(const [key,value] of Object.entries(node))if(!['loc','start','end','extra','comments','tokens'].includes(key)){
   if(Array.isArray(value))for(const child of value)walk(child,owner);else if(value&&typeof value==='object')walk(value,owner);
  }
 }
 walk(ast);
}
const counts={};for(const entry of inventory)counts[entry.kind]=(counts[entry.kind]||0)+1;
const hashes=Object.fromEntries(files.map(n=>['resources/js/portal/'+n,crypto.createHash('sha256').update(fs.readFileSync(path.join(root,'resources/js/portal',n))).digest('hex')]));
process.stdout.write(JSON.stringify({scope:'AST inventory of React templates and configured fields. Conditional and mapped instances need runtime verification. No functional acceptance inferred.',counts,hashes,items:inventory},null,2));
