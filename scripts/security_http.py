"""Pruebas HTTP contra la DEMO local real, con CSRF y sesión habilitados."""
from pathlib import Path
import urllib.request,urllib.error,http.cookiejar,urllib.parse,re,json
root=Path(__file__).resolve().parents[1]
base='http://127.0.0.1:8099'
cookies=http.cookiejar.CookieJar();client=urllib.request.build_opener(urllib.request.HTTPCookieProcessor(cookies))
checks=[]
def request(path,data=None):
 try:
  response=client.open(urllib.request.Request(base+path,data=urllib.parse.urlencode(data).encode() if data is not None else None),timeout=15)
  return response.status,response.read().decode('utf-8',errors='replace'),response.headers
 except urllib.error.HTTPError as e:return e.code,e.read().decode('utf-8',errors='replace'),e.headers
def check(name,ok):
 checks.append({'test':name,'result':'PASS' if ok else 'FAIL'});assert ok,name
status,text,headers=request('/login');check('Login disponible con sesión real',status==200)
check('Cabeceras nosniff, frame DENY, CSP y no-store',headers.get('X-Content-Type-Options')=='nosniff' and headers.get('X-Frame-Options')=='DENY' and "script-src 'none'" in headers.get('Content-Security-Policy','') and 'no-store' in headers.get('Cache-Control',''))
status,_,_=request('/login',{'email':'estudiante@ficct.test','password':'sin-token'});check('POST sin CSRF rechazado por servidor real',status==419)
status,text,_=request('/login');token=re.search(r'name="_token" value="([^"]+)"',text).group(1)
line=next(x for x in (root/'.local/ACCESOS_DEMO.txt').read_text(encoding='utf-8').splitlines() if x.startswith('estudiante@ficct.test'))
email,password=[x.strip() for x in line.split('|',1)]
status,text,_=request('/login',{'_token':token,'email':email,'password':password});check('Acceso con CSRF válido',status==200 and 'Andrea Ejemplo' in text)
status,_,_=request('/usuarios');check('Postulante sin acceso a administración',status==403)
status,_,_=request('/.env');check('Archivo de entorno no expuesto por servidor público',status==404)
status,_,_=request('/ofertas/13/resultados');check('Postulante sin acceso al consolidado de autoridad',status==403)
(root/'evidence/security-http.json').write_text(json.dumps({'target':base,'checks':checks,'result':'PASS'},ensure_ascii=False,indent=2),encoding='utf-8')
print(json.dumps(checks,ensure_ascii=False,indent=2))
