#!/usr/bin/env python3
"""Static safety checks only; does NOT validate DDL against an actual MySQL server."""
from pathlib import Path
import re, sys
root=Path(__file__).resolve().parents[1]
s=(root/'contracts/schema.sql').read_text(encoding='utf-8')
tables=re.findall(r'CREATE TABLE\s+(\w+)\s*\(',s)
fks=re.findall(r'CONSTRAINT\s+(\w+)\s+FOREIGN KEY',s)
indexes=re.findall(r'CREATE (?:UNIQUE )?INDEX\s+(\w+)',s)
flags=[]
if len(tables)!=44:flags.append(f'Expected 44 tables, found {len(tables)}')
if len(tables)!=len(set(tables)):flags.append('Duplicate table name')
if len(fks)!=len(set(fks)):flags.append('Duplicate FK name')
if any(len(k)>64 for k in fks+indexes):flags.append('Index/FK name > MySQL 64-char limit')
for label,regex in [('pg extension',r'CREATE\s+EXTENSION'), ('pg UUID',r'gen_random_uuid\('), ('pg timestamp',r'\btimestamptz\b'), ('pg JSONB',r'\bjsonb\b'), ('pg unique index',r'CREATE\s+UNIQUE\s+INDEX[^;]+\s+WHERE\s+'), ('postgres conflict',r'ON\s+CONFLICT\s+DO')]:
 if re.search(regex,s,re.I):flags.append('Dialect leak: '+label)
if "DB_CONNECTION=mysql" not in (root/'.env.example').read_text():flags.append('Wrong DB_CONNECTION')
if 'uq_one_active_model_per_scope' not in s:flags.append('Missing active model uniqueness gate')
if 'engine_type' not in s:flags.append('Missing statistical-vs-ML labeling')
if flags:
 print('STATIC MYSQL REFERENCE CHECK FAILED:', *flags,sep='\n- ');sys.exit(1)
print(f'STATIC MYSQL REFERENCE CHECK PASSED: {len(tables)} tables, {len(fks)} explicit FKs, {len(indexes)} top-level indexes; live MySQL execution NOT performed')
