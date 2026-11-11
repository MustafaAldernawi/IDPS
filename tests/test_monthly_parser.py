import importlib.util
from pathlib import Path
import pytest

spec = importlib.util.spec_from_file_location('monthly_parser', Path(__file__).parents[1] / 'scripts/parse_monthly.py')
parser = importlib.util.module_from_spec(spec)
spec.loader.exec_module(parser)

def test_monthly_parser_rejects_daily_and_preserves_null_zero(tmp_path):
    p=tmp_path/'input.csv'
    p.write_text('month,period_granularity,product_diesel_actual_t\n2026-01,day,0\n')
    with pytest.raises(ValueError,match='monthly'):
        parser.parse(p)
    p.write_text('month,period_granularity,product_diesel_actual_t,product_lpg_actual_t\n2026-01,month,0,\n')
    rows=parser.parse(p)
    assert rows[0]['quantity']=='0.000000'
    assert rows[1]['quantity'] is None

def test_monthly_parser_rejects_duplicate_period_and_nonfinite(tmp_path):
    p=tmp_path/'input.csv'
    p.write_text('month,product_diesel_actual_t\n2026-01,NaN\n')
    with pytest.raises(ValueError): parser.parse(p)
    p.write_text('month,product_diesel_actual_t\n2026-01,1\n2026-01,2\n')
    with pytest.raises(ValueError,match='duplicate'): parser.parse(p)

def test_unknown_header_requires_approved_mapping_and_keeps_original_locator(tmp_path):
    p=tmp_path/'input.csv'
    p.write_text('month,period_granularity,زيت ديزل\n2026-01,month,12.25\n',encoding='utf-8')
    assert parser.unknown_headers(p)==['زيت ديزل']
    with pytest.raises(ValueError,match='unmapped'):
        parser.parse(p)
    result=parser.parse(p,alias_map={'زيت ديزل':'product_diesel_actual_t'})
    assert result[0]['item_code']=='DIESEL'
    assert result[0]['quantity']=='12.250000'
    assert result[0]['locator']['column']=='زيت ديزل'
    with pytest.raises(ValueError,match='unmapped'):
        parser.parse(p,alias_map={'زيت ديزل':'unrecognized_field'})

def test_flat_xlsx_requires_explicit_sheet_and_preserves_cell_lineage(tmp_path):
    import openpyxl
    workbook=openpyxl.Workbook(); sheet=workbook.active; sheet.title='المصدر'
    sheet.append(['month','زيت ديزل']); sheet.append(['2026-01',12.25])
    path=tmp_path/'synthetic.xlsx'; workbook.save(path)
    assert parser.unknown_headers(path,flat_sheet='المصدر')==['زيت ديزل']
    with pytest.raises(ValueError,match='unmapped'):
        parser.parse(path,flat_sheet='المصدر')
    result=parser.parse(path,flat_sheet='المصدر',alias_map={'زيت ديزل':{'field':'product_diesel_actual_t','report_status':'actual','mapping_id':'approved-version'}})
    assert result[0]['quantity']=='12.250000'
    assert result[0]['locator']=={'sheet':'المصدر','row':2,'column':'زيت ديزل','cell':'B2'}
    assert result[0]['mapping_version_id']=='approved-version'
