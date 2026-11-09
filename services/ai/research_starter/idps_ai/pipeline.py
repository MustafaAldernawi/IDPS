"""IDPS research-only monthly forecasting. Input files are PROVISIONAL."""
from __future__ import annotations
import csv, hashlib, json, math
from pathlib import Path
from statistics import mean
from datetime import date
import numpy as np
import joblib
from sklearn.ensemble import RandomForestRegressor
from sklearn.linear_model import Ridge
from sklearn.pipeline import make_pipeline
from sklearn.preprocessing import StandardScaler
from xgboost import XGBRegressor

PRODUCTS = {
 'lpg': 'product_lpg_actual_t',
 'unprocessed_naphtha': 'product_unprocessed_naphtha_actual_t',
 'gasoline': 'product_gasoline_actual_t',
 'kerosene': 'product_kerosene_actual_t',
 'diesel': 'product_diesel_actual_t',
 'heavy_fuel_oil': 'product_heavy_fuel_oil_actual_t',
}
FEATURE_NAMES=['previous_product','product_prev_3_mean','previous_crude','crude_prev_3_mean','month_sin','month_cos']

def next_month(value):
 y,m=map(int,value.split('-'));return f'{y+(m==12):04d}-{m%12+1:02d}'

def read_months(path, allow_gaps=False):
 with Path(path).open(encoding='utf-8-sig',newline='') as f:
  rd=csv.DictReader(f)
  if not rd.fieldnames:raise ValueError('Empty CSV')
  mandatory={'month','crude_total_actual_t',*PRODUCTS.values()}
  if diff:=mandatory.difference(rd.fieldnames):raise ValueError(f'Missing columns: {diff}')
  rows=list(rd)
 if not rows:raise ValueError('No months')
 seen=set()
 for row in rows:
  month=row['month']
  try:date.fromisoformat(month+'-01')
  except ValueError:raise ValueError(f'Invalid month: {month}') from None
  if month in seen:raise ValueError(f'duplicate month: {month}')
  seen.add(month)
  for col in mandatory-{'month'}:
   try:
    value=float(row[col]); assert math.isfinite(value) and value>=0
   except (TypeError,ValueError,AssertionError):raise ValueError(f'Invalid {col}, month={month}') from None
   row[col]=value
 rows.sort(key=lambda r:r['month'])
 for prev,curr in zip(rows,rows[1:]):
  if not allow_gaps and curr['month']!=next_month(prev['month']):raise ValueError(f"Gap: {prev['month']} to {curr['month']}; do not fabricate missing months")
 return rows

def features(rows,i,product):
 if i<3:raise ValueError('Three months of history are required')
 for j in range(i-3,i):
  following=(rows[j+1]['month'] if j+1<len(rows) else next_month(rows[-1]['month']))
  if following!=next_month(rows[j]['month']):raise ValueError('Missing adjacent monthly history: lag features unavailable')
 col=PRODUCTS[product]
 prev=rows[i-3:i]
 month=int((rows[i]['month'] if i<len(rows) else next_month(rows[-1]['month'])).split('-')[1])
 return np.array([rows[i-1][col],mean(r[col] for r in prev),rows[i-1]['crude_total_actual_t'],mean(r['crude_total_actual_t'] for r in prev),math.sin(2*math.pi*month/12),math.cos(2*math.pi*month/12)])

def xy(rows,indices,product):
 return np.stack([features(rows,i,product) for i in indices]),np.array([rows[i][PRODUCTS[product]] for i in indices])

def algorithm(kind):
 if kind=='ridge':return make_pipeline(StandardScaler(),Ridge(alpha=10))
 if kind=='random_forest':return RandomForestRegressor(n_estimators=100,max_depth=3,min_samples_leaf=3,n_jobs=1,random_state=42)
 if kind=='xgboost':return XGBRegressor(n_estimators=80,max_depth=2,learning_rate=0.05,reg_lambda=12,n_jobs=1,random_state=42,objective='reg:squarederror')
 raise ValueError(kind)

def baseline(rows,i,product,kind):
 col=PRODUCTS[product]
 if kind=='last_month':return rows[i-1][col]
 if kind=='mean3':return mean(r[col] for r in rows[i-3:i])
 if kind=='seasonal12':return rows[i-12][col]
 raise ValueError(kind)

def metrics(y,p):
 a=np.abs(y-p); return {'n':len(y),'MAE_t':round(float(a.mean()),3),'RMSE_t':round(float(np.sqrt(np.mean((y-p)**2))),3),'WAPE_pct':round(float(100*a.sum()/np.abs(y).sum()),3) if np.abs(y).sum() else None}

def research_train(dataset, output):
 """2022 selection only; 2023 untouched holdout; research artifact refit on full history."""
 dataset=Path(dataset);output=Path(output);output.mkdir(parents=True,exist_ok=True)
 rows=read_months(dataset)
 if len(rows)!=36 or rows[0]['month']!='2021-01' or rows[-1]['month']!='2023-12':
  raise ValueError('Initial research protocol expects exactly 2021-01..2023-12; use future retraining policy for later data')
 tr21=list(range(3,12));v22=list(range(12,24));tr22=list(range(3,24));te23=list(range(24,36))
 names=('last_month','mean3','seasonal12','ridge','random_forest','xgboost')
 report={'data_status':'PROVISIONAL','can_deploy':False,'feature_schema':'monthly_past_actuals_v1','dataset_sha256':hashlib.sha256(dataset.read_bytes()).hexdigest(),'validation':'2022','test':'2023','products':{}}
 test_details=[]
 for product,col in PRODUCTS.items():
  val={}; test={}; predictions={}
  for kind in names:
   yv=np.array([rows[i][col] for i in v22])
   if kind in ('last_month','mean3','seasonal12'):
    pv=np.array([baseline(rows,i,product,kind) for i in v22]); pt=np.array([baseline(rows,i,product,kind) for i in te23])
   else:
    xtr,ytr=xy(rows,tr21,product);xv,_=xy(rows,v22,product)
    pv=np.maximum(algorithm(kind).fit(xtr,ytr).predict(xv),0)
    xtr,ytr=xy(rows,tr22,product);xt,_=xy(rows,te23,product)
    pt=np.maximum(algorithm(kind).fit(xtr,ytr).predict(xt),0)
   val[kind]=metrics(yv,pv)
   test[kind]=metrics(np.array([rows[i][col] for i in te23]),pt)
   predictions[kind]=pt
  champion=min(names,key=lambda k:(val[k]['WAPE_pct'],val[k]['MAE_t']))
  saved={}
  for kind in ('ridge','random_forest','xgboost'):
   x,y=xy(rows,list(range(3,36)),product)
   path=output/f'{product}_{kind}_RESEARCH_ONLY.joblib'
   joblib.dump(algorithm(kind).fit(x,y),path)
   saved[kind]={'file':path.name,'sha256':hashlib.sha256(path.read_bytes()).hexdigest()}
  report['products'][product]={'chosen_by_2022':champion,'validation':val,'test':test,'chosen_holdout':test[champion],'trained_experimental_model_files':saved,'approval':'BLOCKED'}
  for j,i in enumerate(te23):
   test_details.append({'month':rows[i]['month'],'product':product,'actual_t':rows[i][col], 'chosen_method':champion,'chosen_prediction_t':round(float(predictions[champion][j]),3),'xgboost_prediction_t':round(float(predictions['xgboost'][j]),3)})
 (output/'research_report.json').write_text(json.dumps(report,ensure_ascii=False,indent=2),encoding='utf-8')
 with (output/'holdout_2023_predictions.csv').open('w',encoding='utf-8-sig',newline='') as f:
  w=csv.DictWriter(f,fieldnames=test_details[0].keys());w.writeheader();w.writerows(test_details)
 return report

def stage_new(current,new_file,staging):
 """Only stage, not approve, merge, or silently retrain."""
 current=read_months(current);new=read_months(new_file)
 if new[0]['month'] <= current[-1]['month']:
  raise ValueError('New rows overlap existing history; revisions require controlled review workflow')
 gap_detected=new[0]['month']!=next_month(current[-1]['month'])
 staging=Path(staging);staging.mkdir(parents=True,exist_ok=True)
 content=Path(new_file).read_bytes(); sha=hashlib.sha256(content).hexdigest()
 destination=staging/(sha[:16]+'.csv')
 if not destination.exists():destination.write_bytes(content)
 return {'new_rows':len(new),'from':new[0]['month'],'through':new[-1]['month'],'sha256':sha,'staged_at':str(destination),'approved':False,'retraining_started':False,'gap_before_new_data':gap_detected,'note':('Accepted for staging: earlier calendar months are missing; training must use contiguous eligible windows, never synthetic filler.' if gap_detected else 'Contiguous new months staged; approval is still required.')}

def retrain_approved(approved_dataset,output):
 """Generalized monthly retraining; blocked unless export is marked approved.

 This flag MUST come from authenticated Laravel approval workflow; standalone CSV
 is only a transport format, not proof of authorization.
 """
 approved_dataset=Path(approved_dataset);output=Path(output)
 with approved_dataset.open(encoding='utf-8-sig',newline='') as f:
  reader=csv.DictReader(f)
  if not reader.fieldnames or 'review_status' not in reader.fieldnames:
   raise ValueError('Approved data export requires review_status column from authenticated workflow')
  approvals=[r['review_status'].strip().lower() for r in reader]
 if not approvals or any(s!='approved' for s in approvals):
  raise ValueError('Some rows are not approved; retraining must not use provisional measurements')
 rows=read_months(approved_dataset,allow_gaps=True)
 eligible=[]
 for i in range(3,len(rows)):
  try:features(rows,i,'diesel')
  except ValueError:continue
  eligible.append(i)
 if len(eligible)<33:raise ValueError('Not enough eligible months with 3 contiguous previous months for train/validation/test')
 train_indices=eligible[:-24]
 validate_indices=eligible[-24:-12]
 test_indices=eligible[-12:]
 has_gap=any(rows[i]['month']!=next_month(rows[i-1]['month']) for i in range(1,len(rows)))
 kinds=('last_month','mean3','ridge','random_forest','xgboost') if has_gap else ('last_month','mean3','seasonal12','ridge','random_forest','xgboost')
 run={'dataset_sha256':hashlib.sha256(approved_dataset.read_bytes()).hexdigest(),
      'monthly_rows':len(rows),'eligible_feature_rows':len(eligible),'history_has_gaps':has_gap,'approved_export_required':True,'auto_deployed':False,
      'validation_period':[rows[validate_indices[0]]['month'],rows[validate_indices[-1]]['month']],
      'holdout_period':[rows[test_indices[0]]['month'],rows[test_indices[-1]]['month']],
      'promotion_requires_human_review':True,'products':{}}
 output.mkdir(parents=True,exist_ok=True)
 for product,col in PRODUCTS.items():
  val_actual=np.array([rows[i][col] for i in validate_indices])
  test_actual=np.array([rows[i][col] for i in test_indices])
  val={};test={}
  for kind in kinds:
   if kind in ('last_month','mean3','seasonal12'):
    val_pred=np.array([baseline(rows,i,product,kind) for i in validate_indices]);test_pred=np.array([baseline(rows,i,product,kind) for i in test_indices])
   else:
    xtr,ytr=xy(rows,train_indices,product);xval,_=xy(rows,validate_indices,product)
    val_pred=np.maximum(0,algorithm(kind).fit(xtr,ytr).predict(xval))
    xt,yt=xy(rows,train_indices+validate_indices,product);xe,_=xy(rows,test_indices,product)
    test_pred=np.maximum(0,algorithm(kind).fit(xt,yt).predict(xe))
   val[kind]=metrics(val_actual,val_pred);test[kind]=metrics(test_actual,test_pred)
  candidate=min(kinds,key=lambda k:(val[k]['WAPE_pct'],val[k]['MAE_t']))
  artifact=None
  if candidate in ('ridge','random_forest','xgboost'):
   xx,yy=xy(rows,eligible,product)
   trained=algorithm(candidate).fit(xx,yy)
   path=output/f'{product}_{candidate}_CANDIDATE.joblib';joblib.dump(trained,path)
   artifact={'file':path.name,'sha256':hashlib.sha256(path.read_bytes()).hexdigest()}
  run['products'][product]={'proposed_candidate':candidate,'validation':val,'holdout':test,'candidate_artifact':artifact,'promotion_status':'AWAITING_HUMAN_REVIEW'}
 (output/'candidate_report.json').write_text(json.dumps(run,ensure_ascii=False,indent=2),encoding='utf-8')
 return run
