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
