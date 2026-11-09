"""Local research runner for IDPS. Not a production API."""
import argparse, json
from pathlib import Path
from idps_ai.pipeline import research_train,stage_new,retrain_approved
ROOT=Path(__file__).parent
BASE=ROOT/'data'/'monthly_2021_2023_PROVISIONAL.csv'

def main():
 p=argparse.ArgumentParser(description='IDPS research-only monthly ML trainer')
 sub=p.add_subparsers(dest='command',required=True)
 t=sub.add_parser('train');t.add_argument('--dataset',type=Path,default=BASE);t.add_argument('--out',type=Path,default=ROOT/'results')
 r=sub.add_parser('retrain-approved');r.add_argument('--dataset',type=Path,required=True);r.add_argument('--out',type=Path,default=ROOT/'results'/'candidate_retrain')
 s=sub.add_parser('stage');s.add_argument('--current',type=Path,default=BASE);s.add_argument('--new',type=Path,required=True)
 a=p.parse_args()
 if a.command=='train':
  r=research_train(a.dataset,a.out)
  print('Research experiments completed. Production activation BLOCKED (provisional dataset).')
  for product,v in r['products'].items():print(f"{product:22} {v['chosen_by_2022']:18} holdout WAPE: {v['chosen_holdout']['WAPE_pct']:.2f}%")
 elif a.command=='retrain-approved':
  r=retrain_approved(a.dataset,a.out)
  print(f"Candidate retraining complete. Holdout: {r['holdout_period']}. Promotion requires engineer approval.")
 else:print(json.dumps(stage_new(a.current,a.new,ROOT/'data'/'staging'),ensure_ascii=False,indent=2))
if __name__=='__main__':main()
