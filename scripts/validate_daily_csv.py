#!/usr/bin/env python3
"""Simple local CSV data exchange checker. Not a replacement for production ETL.

Usage:
  python scripts/validate_daily_csv.py data/samples/synthetic_daily_example.csv

Does not train ML; does not interpret physical production validity.
"""
import csv
import datetime as dt
import json
import math
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
SCHEMA = json.loads((ROOT / 'contracts/daily_record.schema.json').read_text(encoding='utf-8'))
REQUIRED = set(SCHEMA['required'])
VALID_PRODUCTS = set(SCHEMA['properties']['product_code']['enum'])
VALID_STATUS = {'approved','pending','missing'}
VALID_ORIGIN = {'observed_approved','synthetic_demo'}
NUMERIC = {'crude_feed_lag1_t','product_production_lag1_t','product_production_lag7_t',
           'product_rolling7_t','known_planned_shutdown_hours','recent_unplanned_downtime_hours',
           'known_approved_target_t','product_production_t'}

def read_date(value: str) -> bool:
    try: dt.date.fromisoformat(value)
    except ValueError: return False
    return True

def read_timestamp(value: str) -> bool:
    try:
        obj = dt.datetime.fromisoformat(value.replace('Z', '+00:00'))
        return obj.tzinfo is not None
    except ValueError: return False

def main(path: Path) -> int:
    if not path.exists():
        print(f'ERROR: file not found: {path}')
        return 2
    errors=[]; warnings=[]; keys=set(); count=0
    with path.open('r', encoding='utf-8-sig', newline='') as f:
        reader=csv.DictReader(f)
        headers=reader.fieldnames or []
        if len(headers)!=len(set(headers)):
            errors.append('Duplicate header names')
        missing=REQUIRED-set(headers)
        unknown=set(headers)-set(SCHEMA['properties'])
        if missing: errors.append('Missing required headers: '+', '.join(sorted(missing)))
        if unknown: warnings.append('Unknown headers: '+', '.join(sorted(unknown)))
        for i, row in enumerate(reader, start=2):
            count+=1
            def err(message):errors.append(f'row {i}: {message}')
            if any(not (row.get(k) or '').strip() for k in REQUIRED if k not in ('product_production_t',)):
                err('one or more required fields blank')
            if not read_date(row.get('business_date','')):err('invalid business_date')
            if not read_timestamp(row.get('as_of','')):err('invalid or timezone-naive as_of')
            if row.get('product_code') not in VALID_PRODUCTS:err('unknown product_code')
            if row.get('label_status') not in VALID_STATUS:err('invalid label_status')
            if row.get('data_origin') not in VALID_ORIGIN:err('invalid data_origin')
            if row.get('facility_timezone')!='Africa/Tripoli':err('unexpected facility_timezone')
            if row.get('label_status')=='approved' and not (row.get('product_production_t') or '').strip():
                err('approved label is missing')
            for k in NUMERIC:
                val=(row.get(k) or '').strip()
                if not val:continue
                try:
                    num=float(val)
                    if not math.isfinite(num) or num<0:err(f'{k} must be finite and >=0')
                    if k=='known_planned_shutdown_hours' and num>24:err('planned_shutdown_hours >24')
                except ValueError:err(f'{k} must be numeric or blank')
            key=(row.get('business_date'),row.get('unit_code'),row.get('product_code'))
            if key in keys:err('duplicate date+unit+product row')
            keys.add(key)
            if row.get('data_origin')=='synthetic_demo':
                warnings.append('Synthetic demonstration records were found; do not use as real refinery training data')
    if count==0:warnings.append('Header-only template: no training records available')
    warnings=list(dict.fromkeys(warnings))
    for message in errors: print('ERROR:',message)
    for message in warnings: print('WARNING:',message)
    print(f'Validated rows={count}; errors={len(errors)}; warnings={len(warnings)}')
    return 1 if errors else 0

if __name__=='__main__':
    if len(sys.argv)!=2:
        print('Usage: python scripts/validate_daily_csv.py <csv_path>')
        raise SystemExit(2)
    raise SystemExit(main(Path(sys.argv[1])))
