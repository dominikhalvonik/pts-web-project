"""Local verification helper: render owned source offline; performs no browser navigation/network.
Transforms ES module imports into local module closures. HTTP API is an explicit mock.
Not evidence of a PHP/MariaDB integration run.
"""
import json,re
from pathlib import Path
from types import SimpleNamespace
ROOT=Path(__file__).resolve().parents[1]
JS=ROOT/'public/assets/js'

def bundle():
    seen=set();out=['(()=>{const modules={};']
    def visit(path):
        path=path.resolve();name=path.relative_to(JS).as_posix()
        if name in seen:return
        seen.add(name);source=path.read_text()
        imports=re.findall(r'^import (.+?) from [\'"](.+?)[\'"];\s*$',source,re.M)
        for spec,url in imports:visit((path.parent/url))
        for spec,url in imports:
            dep=(path.parent/url).resolve().relative_to(JS).as_posix()
            replacement=f'const {spec} = modules[{json.dumps(dep)}]'+(';' if spec.startswith('{') else '.default;')
            source=re.sub(r'^import '+re.escape(spec)+r' from [\'"]'+re.escape(url)+r'[\'"];\s*$',lambda m:replacement,source,flags=re.M)
        exports=re.findall(r'^export (?:async )?(?:function|class|const|let) (\w+)',source,re.M)
        source=re.sub(r'^export (?=(?:async )?(?:function|class|const|let) )','',source,flags=re.M)
        default='export default' in source
        source=source.replace('export default','const __default =')
        source=source.replace("new URL('./', window.location.href)","new URL('./', 'http://testlab.test/')").replace("new URL('./', location.href)","new URL('./', 'http://testlab.test/')")
        if name=='main.js': source=source.replace('createApp({','window.__testApp = createApp({',1).replace("}).mount('#app');","}); window.__testApp.mount('#app');")
        out.append(f'modules[{json.dumps(name)}]=(()=>{{\n{source}\nreturn {{'+','.join(exports+(['default:__default'] if default else []))+'};})();')
    visit(JS/'main.js');out.append('})();');return '\n'.join(out)
BUNDLE=bundle()
HTML=re.sub(r'<\?php.*?\?>','',(ROOT/'public/index.php').read_text(),flags=re.S)
HTML=re.sub(r'<script\b.*?</script>','',HTML,flags=re.S)
HTML=re.sub(r'<link\b[^>]*>','',HTML)

def configure(context,mock):
    def call(source,args):
        reply={}
        req=SimpleNamespace(url=args['url'],method=args.get('method','GET'),post_data=args.get('body'),post_data_json=json.loads(args['body']) if args.get('body') else None)
        route=SimpleNamespace(request=req,fulfill=lambda **kw:reply.update(kw))
        mock.intercept(route)
        return reply
    context.expose_binding('__mockBridge',call)

def boot(page,url):
    path=url.split('#',1)[1] if '#' in url else '/catalog'
    page.evaluate("() => { if(window.__testApp) window.__testApp.unmount(); }")
    page.set_content(HTML)
    page.add_style_tag(content=(ROOT/'public/assets/css/app.css').read_text())
    page.add_script_tag(content=(ROOT/'public/assets/vendor/vue.global.prod.js').read_text())
    page.evaluate('''() => {
      function storage(kind) {
        const data = window[kind] || (window[kind] = {});
        return {getItem:key=>data[key]??null, setItem:(key,value)=>{data[key]=String(value)},removeItem:key=>{delete data[key]},clear:()=>{for(const key in data)delete data[key]}};
      }
      Object.defineProperty(window,'localStorage',{configurable:true,value:storage('__localData')});
      Object.defineProperty(window,'sessionStorage',{configurable:true,value:storage('__sessionData')});
      window.fetch=async(url,options={})=>{
        const r=await window.__mockBridge({url:String(url),method:options.method||'GET',body:options.body||null});
        return new Response(r.body,{status:r.status,headers:{'Content-Type':r.content_type}});
      };
    }''')
    page.evaluate('(path)=>{location.hash=path}',path)
    page.add_script_tag(content=BUNDLE)
