"""Offline bounded CSV/XLSX parser. Outputs staging facts, never approvals.

Workbook adapter v1 verifies expected row labels/month headers before using cells.
Only actual/target monthly sheets are read; formulas use cached values, never execute.
"""
import csv
import json
import re
import sys
import zipfile
from decimal import Decimal, InvalidOperation
from pathlib import Path

PRODUCTS = ['lpg','unprocessed_naphtha','gasoline','kerosene','diesel','heavy_fuel_oil']
FIELDS = {f'product_{p}_{measure}_t': ('product',p.upper(),measure) for p in PRODUCTS for measure in ['actual','target']}
FIELDS.update({f'crude_{p}_{measure}_t':('feed',p.upper(),measure) for p in ['sharara','hamada','total'] for measure in ['actual','target']})
FIELDS.update({f'product_total_{m}_t':('reported_product_total','TOTAL',m) for m in ['actual','target']})
FIELDS.update({key:('fuel',item,'actual') for key,item in [('fuel_gas_actual_t','FUEL_GAS'),('lpg_used_actual_t','LPG'),('diesel_used_actual_t','DIESEL'),('hfo_used_actual_t','HEAVY_FUEL_OIL'),('total_actual_t','REPORTED_TOTAL')]})
FIELDS.update({key:('loss',item,'actual') for key,item in [('loss_reported_actual_t','REPORTED_LOSS'),('flare_gas_reported_actual_t','FLARE'),('unaccounted_and_flare_reported_actual_t','UNACCOUNTED_AND_FLARE')]})

def quantity(value):
    if value is None or str(value).strip()=='': return None
    text=str(value).strip().translate(str.maketrans('٠١٢٣٤٥٦٧٨٩٫٬','0123456789.,')).replace(',','')
    try: number=Decimal(text)
    except InvalidOperation: raise ValueError('invalid decimal') from None
    if not number.is_finite() or number<0 or number>=Decimal('100000000000000'): raise ValueError('invalid quantity range')
    return format(number,'f')

def fact(period,field,value,locator,flags=None):
    if not re.fullmatch(r'\d{4}-(0[1-9]|1[0-2])',period): raise ValueError('invalid monthly period')
    metric,item,measure=FIELDS[field]
    warnings=list(flags or [])
    original=value
    if value is not None:
        value=format(Decimal(value).quantize(Decimal('.000001')),'.6f')
        if Decimal(original)!=Decimal(value): warnings.append('source_precision_rounded_to_6_original_retained')
    if metric in ['loss','reported_product_total'] or item=='REPORTED_TOTAL': warnings.append('semantic_scope_requires_review')
    if value is None: warnings.append('missing_value')
    if metric=='loss' and value=='0.000000': warnings.append('zero_meter_status_unknown')
    return dict(period_month=period,metric_code=metric,item_code=item,actual_or_target=measure,report_status='actual',quantity=value,original_quantity=original,unit_code='t',locator=locator,qa_flags=warnings)

def parse(path,year=None):
    path=Path(path)
    if path.stat().st_size>10*1024*1024: raise ValueError('file too large')
    output=[]
    if path.suffix.lower()=='.csv':
        with path.open(encoding='utf-8-sig',newline='') as stream:
            reader=csv.DictReader(stream); seen=set()
            if not reader.fieldnames or 'month' not in reader.fieldnames: raise ValueError('month column required')
            for line,row in enumerate(reader,2):
                if line>10002: raise ValueError('too many rows')
                if row.get('period_granularity','month') not in ['month','monthly']: raise ValueError('monthly granularity required')
                if row.get('period_status','actual')!='actual': raise ValueError('annual/cumulative/semi-actual requires separate contract')
                period=row['month']
                if period in seen: raise ValueError('duplicate month')
                seen.add(period)
                for field in reader.fieldnames:
                    if field in FIELDS: output.append(fact(period,field,quantity(row[field]),{'row':line,'column':field},[row['qa_flags']] if row.get('qa_flags') else []))
    elif path.suffix.lower()=='.xlsx':
        import openpyxl
        with zipfile.ZipFile(path) as archive:
            if sum(entry.file_size for entry in archive.infolist())>40*1024*1024: raise ValueError('expanded workbook too large')
            if any('vbaProject' in entry.filename for entry in archive.infolist()): raise ValueError('macro workbook rejected')
        if year is None: raise ValueError('explicit workbook year required')
        if not 2000<=int(year)<=2100: raise ValueError('invalid year')
        book=openpyxl.load_workbook(path,read_only=True,data_only=True,keep_links=False)
        mapping={5:('crude_sharara','الشرارة'),6:('crude_hamada','الحمادة'),11:('crude_total','المجموع'),15:('product_lpg','غاز النفط المسال'),16:('product_unprocessed_naphtha','نافثا غير معالجة'),17:('product_gasoline','جازولين'),18:('product_kerosene','كيروسين'),19:('product_diesel','زيت ديزل'),20:('product_heavy_fuel_oil','زيت وقود ثقيل'),21:('product_total','مجموع المنتجات')}
        try:
            for sheet,measure in [('الفعلي','actual'),('المستهدف','target')]:
                if sheet not in book.sheetnames: raise ValueError('unknown workbook mapping')
                rows=list(book[sheet].iter_rows(max_row=42,max_col=14,values_only=True))
                if list(rows[3][2:14])!=list(range(1,13)): raise ValueError('monthly header mapping changed')
                for row,(prefix,label) in mapping.items():
                    if str(rows[row-1][1]).strip()!=label: raise ValueError('worksheet label mapping changed')
                    for month in range(1,13):
                        output.append(fact(f'{int(year):04d}-{month:02d}',f'{prefix}_{measure}_t',quantity(rows[row-1][month+1]),{'sheet':sheet,'row':row,'column':month+2,'cell':f'{openpyxl.utils.get_column_letter(month+2)}{row}'}))
            rows=list(book['الفعلي'].iter_rows(max_row=42,max_col=14,values_only=True))
            fuel={25:('fuel_gas_actual_t','الوقود الغازي'),26:('lpg_used_actual_t','غاز نفط مسال'),27:('diesel_used_actual_t','زيت الديزل'),28:('hfo_used_actual_t','زيت الوقود الثقيل'),29:('total_actual_t','مجموع المستهلك'),36:('flare_gas_reported_actual_t','غاز الشعلة *'),37:('unaccounted_and_flare_reported_actual_t',None),41:('loss_reported_actual_t','الفاقد')}
            for row,(field,label) in fuel.items():
                if label and str(rows[row-1][1]).strip()!=label: raise ValueError('fuel/loss label mapping changed')
                for month in range(1,13): output.append(fact(f'{int(year):04d}-{month:02d}',field,quantity(rows[row-1][month+1]),{'sheet':'الفعلي','row':row,'column':month+2,'cell':f'{openpyxl.utils.get_column_letter(month+2)}{row}'}))
        finally: book.close()
    else: raise ValueError('CSV/XLSX only')
    if not output: raise ValueError('no mapped facts')
    grouped={}
    for item in output: grouped.setdefault((item['period_month'],item['actual_or_target']),[]).append(item)
    for items in grouped.values():
        for metric,total_metric,components in [('feed','feed',['SHARARA','HAMADA']),('product','reported_product_total',[p.upper() for p in PRODUCTS])]:
            total=next((x for x in items if x['metric_code']==total_metric and x['item_code']=='TOTAL'),None)
            values=[x for x in items if x['metric_code']==metric and x['item_code'] in components]
            if total and total['quantity'] is not None and len(values)==len(components) and all(x['quantity'] is not None for x in values):
                if abs(sum(Decimal(x['quantity']) for x in values)-Decimal(total['quantity']))>Decimal('.000001'):
                    for x in [total]+values: x['qa_flags'].append('component_total_mismatch')
    return output

if __name__=='__main__':
    try:
        print(json.dumps(parse(sys.argv[1],int(sys.argv[2]) if len(sys.argv)>2 else None),ensure_ascii=True,separators=(',',':')))
    except (ValueError,KeyError,zipfile.BadZipFile) as error:
        print(json.dumps({'error':str(error)}));sys.exit(2)
