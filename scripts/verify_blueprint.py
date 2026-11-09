#!/usr/bin/env python3
"""Read-only blueprint package validation. No production code, credentials or DB access."""
from pathlib import Path
import csv
import json
import re
import sys

ROOT=Path(__file__).resolve().parents[1]
problems=[]
def need(path):
    if not (ROOT/path).is_file(): problems.append(f'Missing file: {path}')

for path in ['AGENTS.md','SPEC.md','PLAN.md','TASKS.md','README.md','START_HERE_AR.md','IMPLEMENTATION_INDEX.html',
             'PROJECT_STUDY_V2.html','contracts/schema.sql','contracts/openapi.yaml',
             'contracts/daily_record.schema.json','planning/BACKLOG.csv',
             'planning/IDPS_PROJECT_TRACKER_V1_2.xlsx']:
    need(path)

specdirs=list((ROOT/'specs').glob('[0-9][0-9][0-9]-*'))
for d in specdirs:
    for n in ['spec.md','plan.md','tasks.md']:need(d.relative_to(ROOT)/n)
if len(specdirs)!=9: problems.append(f'Expected 9 spec epics, found {len(specdirs)}')

with (ROOT/'planning/BACKLOG.csv').open(encoding='utf-8-sig',newline='') as f:
    backlog=list(csv.DictReader(f))
ids=[r['id'] for r in backlog]
if len(backlog)!=119: problems.append(f'Expected 119 tickets, found {len(backlog)}')
if len(ids)!=len(set(ids)):problems.append('Duplicate ticket IDs')
for r in backlog:
    if not r['acceptance_criteria'] or not r['title']: problems.append(f"Missing acceptance/title in {r['id']}")
    for dep in re.findall(r'RF-\d{3}',r['depends_on']):
        if dep not in ids:problems.append(f'{r["id"]} references missing {dep}')
    d=ROOT/'specs'/r['epic']/'tasks.md'
    if not d.is_file() or r['id'] not in d.read_text(encoding='utf-8'):
        problems.append(f'{r["id"]} missing in epic tasks.md')

schema=json.loads((ROOT/'contracts/daily_record.schema.json').read_text(encoding='utf-8'))
if schema.get('type')!='object':problems.append('daily schema should be object')
try:
    import yaml
    spec=yaml.safe_load((ROOT/'contracts/openapi.yaml').read_text(encoding='utf-8'))
    if spec.get('openapi')!='3.1.0':problems.append('OpenAPI version changed')
    references=[]
    def find_refs(obj):
        if isinstance(obj,dict):
            for k,v in obj.items():
                if k=='$ref': references.append(v)
                else:find_refs(v)
        elif isinstance(obj,list):
            for item in obj:find_refs(item)
    find_refs(spec)
    for ref in references:
        if not ref.startswith('#/'):
            problems.append('Unexpected external ref '+ref)
            continue
        cur=spec
        for part in ref[2:].split('/'):
            cur=cur.get(part) if isinstance(cur,dict) else None
        if cur is None:problems.append('Dangling OpenAPI ref '+ref)
except ImportError:
    print('WARN: PyYAML absent — skipped parsing OpenAPI')
except Exception as e:
    problems.append('OpenAPI invalid: '+str(e))

# Verify documented relative file links from the local HTML guide.
from html.parser import HTMLParser
class Links(HTMLParser):
    def __init__(self):super().__init__();self.hrefs=[]
    def handle_starttag(self,tag,attrs):
        if tag=='a':
            href=dict(attrs).get('href','')
            if href and not href.startswith('#') and '://' not in href:self.hrefs.append(href)
p=Links();p.feed((ROOT/'IMPLEMENTATION_INDEX.html').read_text(encoding='utf-8'))
for href in p.hrefs:
    if not (ROOT/href).is_file():problems.append(f'Broken HTML link: {href}')


# Current 2021-2023 approved-scope research evidence (not approved facility facts)
monthly=ROOT/'data/curated-provisional/IDPS_monthly_2021_2023_PROVISIONAL.csv'
if not monthly.exists(): problems.append('Missing provisional 2021–2023 CSV')
else:
    with monthly.open(encoding='utf-8-sig',newline='') as f: recs=list(csv.DictReader(f))
    if len(recs)!=36 or len(recs[0])!=35: problems.append('Monthly dataset no longer has 36 rows and 35 columns')
    if len({r['month'] for r in recs})!=36: problems.append('Duplicate months in historical CSV')
    if {r['period_granularity'] for r in recs}!={'month'}: problems.append('Monthly data masquerades as daily')
report=ROOT/'services/ai/research_starter/results/research_report.json'
if not report.exists(): problems.append('Missing AI research report')
else:
    m=json.loads(report.read_text(encoding='utf-8'))
    if m.get('can_deploy') is not False: problems.append('Unsafe AI research can_deploy flag')

if problems:
    print('BLUEPRINT CHECK FAILED:')
    for problem in problems:print('-',problem)
    sys.exit(1)
print(f'BLUEPRINT CHECK PASSED: {len(backlog)} tickets, {len(specdirs)} epic folders, {len(p.hrefs)} linked files, schema/OpenAPI and dependencies checked')
