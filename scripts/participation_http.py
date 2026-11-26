"""Recorrido de lectura HTTP con CSRF real; no modifica permisos ni calificaciones."""
import os
from pathlib import Path
import urllib.request,urllib.parse,http.cookiejar,re,json,io
from pypdf import PdfReader
from openpyxl import load_workbook
root=Path(__file__).resolve().parents[1]
(root/'.local'/'salidas').mkdir(parents=True,exist_ok=True)
base=os.environ.get('FICCT_BASE_URL','http://127.0.0.1:8099')  # F-09: elegir otra instancia sin editar el script
client=urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
def request(path,data=None):
    result=client.open(urllib.request.Request(base+path,data=urllib.parse.urlencode(data).encode() if data is not None else None),timeout=30)
    assert result.status==200
    return result.read()
def login(email):
    page=request('/login').decode('utf-8');token=re.search(r'name="_token" value="([^"]+)"',page).group(1)
    line=next(x for x in (root/'.local/ACCESOS_DEMO.txt').read_text(encoding='utf-8').splitlines() if x.startswith(email+' ' ) or x.startswith(email+'|'))
    _,password=[x.strip() for x in line.split('|',1)]
    return request('/login',{'_token':token,'email':email,'password':password}).decode('utf-8')
page=login('admin@ficct.test')
users=request('/usuarios').decode('utf-8')
assert all(x in users for x in ['Editar perfiles','Justificación de revocación','Nuevo vencimiento','Historial administrativo'])
token=re.search(r'name="_token" value="([^"]+)"',users).group(1)
request('/salir',{'_token':token})
login('autoridad@ficct.test')
assert 'Solicitudes y decisiones' in request('/rectificaciones').decode('utf-8')
assert 'Versiones de calificaciones' in request('/postulaciones/2/rectificaciones').decode('utf-8')
assert 'Versiones inmutables del expediente' in request('/postulaciones/2/procesos').decode('utf-8')
assert 'Adjudicaciones y manifestaciones' in request('/adjudicaciones').decode('utf-8')
pdf=request('/convocatorias/4/reporte/pdf');xlsx=request('/convocatorias/4/reporte/xlsx')
(root/'.local/salidas/reporte-demo-03.pdf').write_bytes(pdf);(root/'.local/salidas/reporte-demo-03.xlsx').write_bytes(xlsx)
reader=PdfReader(io.BytesIO(pdf));text=' '.join(' '.join(p.extract_text() for p in reader.pages).split())
assert 'Revisión' in text and 'Publicación pendiente' in text and '68.10' in text
sheet=load_workbook(io.BytesIO(xlsx)).active
assert sheet['J3'].value=='Revisión' and sheet['K3'].value=='Publicación pendiente' and sheet['K4'].value=='No'
assert sheet['J4'].data_type=='s'
assert sheet.max_column==13 and sheet['L3'].value=='Participación' and sheet['M3'].value=='Vacantes retenidas'
report={'result':'PASS','csrf_session_login_logout':True,'admin_profiles_and_temporal_permissions_ui':True,'authority_scoped_queue_and_versions_ui':True,'pdf_pages':len(reader.pages),'xlsx_columns':sheet.max_column,'xlsx_string_cells':True,'synthetic_applications':sheet.max_row-3,'note':'Lecturas de la DEMO existente; ninguna decisión nueva sobre calificaciones o permisos.'}
(root/'.local/salidas/participation-http.json').write_text(json.dumps(report,ensure_ascii=False,indent=2),encoding='utf-8')
print(json.dumps(report,ensure_ascii=False,indent=2))
