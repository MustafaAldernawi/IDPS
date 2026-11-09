import csv
from pathlib import Path
import pytest
from idps_ai.pipeline import read_months, features, stage_new, PRODUCTS
DATA=Path(__file__).resolve().parents[1]/'data'/'monthly_2021_2023_PROVISIONAL.csv'

def test_monthly_count():
 rows=read_months(DATA)
 assert len(rows)==36 and rows[0]['month']=='2021-01' and rows[-1]['month']=='2023-12'
 assert len(PRODUCTS)==6

def test_no_current_month_actual_in_features():
 rows=read_months(DATA)
 old=features(rows,24,'diesel').copy()
 rows[24]['product_diesel_actual_t']=999999
 rows[24]['crude_total_actual_t']=999999
 assert list(old)==list(features(rows,24,'diesel'))

def test_no_duplicated_months(tmp_path):
 with DATA.open(encoding='utf-8-sig',newline='') as f:
  reader=csv.DictReader(f); original=list(reader); headers=reader.fieldnames
 path=tmp_path/'bad.csv'
 with path.open('w',encoding='utf-8-sig',newline='') as f:
  writer=csv.DictWriter(f,fieldnames=headers);writer.writeheader();writer.writerows(original+[original[-1]])
 with pytest.raises(ValueError,match='duplicate'):read_months(path)

def test_no_fabricated_gaps(tmp_path):
 with DATA.open(encoding='utf-8-sig',newline='') as f:
  reader=csv.DictReader(f);original=list(reader);headers=reader.fieldnames
 path=tmp_path/'gap.csv'
 with path.open('w',encoding='utf-8-sig',newline='') as f:
  writer=csv.DictWriter(f,fieldnames=headers);writer.writeheader();writer.writerows(original[:13]+original[14:])
 with pytest.raises(ValueError,match='Gap'):read_months(path)

def test_new_data_staged_only(tmp_path):
 with DATA.open(encoding='utf-8-sig',newline='') as f:
  reader=csv.DictReader(f);original=list(reader);headers=reader.fieldnames
 r=original[-1].copy();r['month']='2024-01'
 path=tmp_path/'new.csv'
 with path.open('w',encoding='utf-8-sig',newline='') as f:
  writer=csv.DictWriter(f,fieldnames=headers);writer.writeheader();writer.writerow(r)
 result=stage_new(DATA,path,tmp_path/'stage')
 assert not result['approved'] and not result['retraining_started']

def test_retrain_rejects_unapproved_dataset(tmp_path):
 from idps_ai.pipeline import retrain_approved
 with pytest.raises(ValueError,match='review_status'):
  retrain_approved(DATA,tmp_path/'out')

def test_current_daily_entries_can_be_staged_despite_historical_gap(tmp_path):
 with DATA.open(encoding='utf-8-sig',newline='') as f:
  reader=csv.DictReader(f);original=list(reader);headers=reader.fieldnames
 row=original[-1].copy();row['month']='2026-11'
 path=tmp_path/'current.csv'
 with path.open('w',encoding='utf-8-sig',newline='') as f:
  writer=csv.DictWriter(f,fieldnames=headers);writer.writeheader();writer.writerow(row)
 result=stage_new(DATA,path,tmp_path/'stage')
 assert result['gap_before_new_data']
 assert not result['approved']
