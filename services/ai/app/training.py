"""Monthly, point-in-time model comparisons. Never activates a candidate."""
from __future__ import annotations

import calendar
import hashlib
import importlib.metadata
import json
import math
import os
import tempfile
from datetime import datetime, timezone
from decimal import Decimal
from pathlib import Path

import numpy as np
from sklearn.ensemble import RandomForestRegressor
from sklearn.linear_model import Ridge
from sklearn.pipeline import make_pipeline
from sklearn.preprocessing import StandardScaler
from xgboost import XGBRegressor

FEATURE_SCHEMA = "monthly_previous_product_mean3_calendar_v1"
FEATURES = ["lag_1_t", "mean_previous_3_t", "month_sin", "month_cos"]
METHODS = ["last_month", "mean3", "seasonal12", "ridge", "random_forest", "xgboost"]


def canonical_bytes(value):
    return json.dumps(value, sort_keys=True, separators=(",", ":"), ensure_ascii=False, allow_nan=False).encode("utf-8")


def canonical_sha(value):
    return hashlib.sha256(canonical_bytes(value)).hexdigest()


def write_once(path, content):
    """Publish a fully written file atomically; concurrent retries cannot truncate it."""
    path = Path(path)
    path.parent.mkdir(parents=True, exist_ok=True)
    temporary = None
    try:
        with tempfile.NamedTemporaryFile(dir=path.parent, prefix=".idps-", delete=False) as output:
            temporary = Path(output.name)
            output.write(content); output.flush(); os.fsync(output.fileno())
        try:
            os.link(temporary, path)
        except FileExistsError:
            if path.read_bytes() != content:
                raise ValueError("immutable_artifact_conflict") from None
    finally:
        if temporary is not None:
            temporary.unlink(missing_ok=True)


def timestamp(value):
    result = datetime.fromisoformat(value.replace("Z", "+00:00"))
    if result.tzinfo is None or result.utcoffset().total_seconds() != 0:
        raise ValueError("timestamps_must_be_utc")
    return result


def month_number(value):
    parsed = datetime.strptime(value, "%Y-%m")
    if parsed.strftime("%Y-%m") != value:
        raise ValueError("invalid_month")
    return parsed.year * 12 + parsed.month - 1


def cutoff(period):
    return datetime.strptime(period, "%Y-%m").replace(tzinfo=timezone.utc)


def known(row, as_of, research=False):
    if timestamp(row["available_at"]) >= as_of:
        return False
    if research and row["status"] == "provisional":
        return True
    return row["status"] == "approved" and row["approved_at"] is not None and timestamp(row["approved_at"]) < as_of


def build_examples(rows, research=False):
    """No target-month quantities enter features; late revisions are excluded."""
    by_month = {month_number(row["period_month"]): row for row in rows}
    result = {}
    for i, row in enumerate(rows):
        month = month_number(row["period_month"])
        previous = [by_month.get(month - lag) for lag in [1, 2, 3]]
        as_of = cutoff(row["period_month"])
        if any(prior is None or not known(prior, as_of, research) for prior in previous):
            continue
        values = [float(Decimal(prior["quantity"])) for prior in previous]
        m = int(row["period_month"][-2:])
        seasonal = by_month.get(month - 12)
        result[i] = {"period_month": row["period_month"], "features": [values[0], sum(values) / 3, math.sin(2 * math.pi * m / 12), math.cos(2 * math.pi * m / 12)], "actual": float(Decimal(row["quantity"])), "seasonal12": float(Decimal(seasonal["quantity"])) if seasonal and known(seasonal, as_of, research) else None}
    return result


def evaluate_metrics(actual, predictions):
    actual = np.array(actual, dtype=float); predictions = np.array(predictions, dtype=float)
    error = np.abs(actual - predictions)
    denominator = float(np.abs(actual).sum())
    return {"n": len(actual), "MAE_t": float(error.mean()), "RMSE_t": float(np.sqrt(np.mean((actual - predictions) ** 2))), "WAPE_pct": float(error.sum() * 100 / denominator) if denominator else None, "negative_prediction_count": int((predictions < 0).sum())}


def estimator(method, seed):
    if method == "ridge":
        return make_pipeline(StandardScaler(), Ridge(alpha=10))
    if method == "random_forest":
        return RandomForestRegressor(n_estimators=50, max_depth=3, min_samples_leaf=3, random_state=seed, n_jobs=1)
    return XGBRegressor(n_estimators=50, max_depth=2, learning_rate=.05, reg_lambda=12, random_state=seed, n_jobs=1, objective="reg:squarederror")


def fit(method, examples, indices, seed):
    model = estimator(method, seed)
    model.fit(np.array([examples[i]["features"] for i in indices]), np.array([examples[i]["actual"] for i in indices]))
    return model


def predict_set(method, model, examples, indices):
    if method == "last_month":
        return [examples[i]["features"][0] for i in indices]
    if method == "mean3":
        return [examples[i]["features"][1] for i in indices]
    if method == "seasonal12":
        return [examples[i]["seasonal12"] for i in indices]
    return model.predict(np.array([examples[i]["features"] for i in indices])).tolist()


def serialize_model(method, model):
    """Data-only JSON; no pickle/joblib deserialization at the service boundary."""
    if method in ("last_month", "mean3", "seasonal12"):
        return {"method": method}
    if method == "ridge":
        scale, ridge = model.steps[0][1], model.steps[1][1]
        return {"method": method, "mean": scale.mean_.tolist(), "scale": scale.scale_.tolist(), "coefficients": ridge.coef_.tolist(), "intercept": float(ridge.intercept_)}
    if method == "random_forest":
        trees = []
        for tree_model in model.estimators_:
            tree = tree_model.tree_
            trees.append({"left": tree.children_left.tolist(), "right": tree.children_right.tolist(), "feature": tree.feature.tolist(), "threshold": tree.threshold.tolist(), "value": tree.value[:, 0, 0].tolist()})
        return {"method": method, "trees": trees, "feature_importances": model.feature_importances_.tolist()}
    return {"method": method, "xgboost_json": model.get_booster().save_raw(raw_format="json").decode("utf-8"), "feature_importances": model.feature_importances_.tolist()}


def predict_json(model, feature, seasonal):
    method = model["method"]
    if method == "last_month": return feature[0]
    if method == "mean3": return feature[1]
    if method == "seasonal12":
        if seasonal is None: raise ValueError("seasonal_history_unavailable")
        return seasonal
    if method == "ridge":
        return float(sum((x - mu) / scale * coef for x, mu, scale, coef in zip(feature, model["mean"], model["scale"], model["coefficients"])) + model["intercept"])
    if method == "random_forest":
        values = []
        for tree in model["trees"]:
            node = 0
            while tree["left"][node] != -1:
                node = tree["left"][node] if feature[tree["feature"][node]] <= tree["threshold"][node] else tree["right"][node]
            values.append(tree["value"][node])
        return sum(values) / len(values)
    from xgboost import Booster, DMatrix
    booster = Booster(); booster.load_model(bytearray(model["xgboost_json"], "utf-8"))
    return float(booster.predict(DMatrix(np.array([feature])))[0])


def train(request, directory):
    rows = request["rows"]
    research = request["mode"] == "research_only"
    examples = build_examples(rows, research)
    dates = [row["period_month"] for row in rows]
    if research:
        train_indices = [i for i in examples if dates[i].startswith("2021-")]
        validation = [i for i in examples if dates[i].startswith("2022-") and examples[i]["seasonal12"] is not None]
        test = [i for i in examples if dates[i].startswith("2023-") and examples[i]["seasonal12"] is not None]
    else:
        eligible_evaluation = [i for i in examples if examples[i]["seasonal12"] is not None]
        test = eligible_evaluation[-12:]
        validation = eligible_evaluation[-24:-12]
        train_indices = [i for i in examples if validation and i < validation[0]]
    not_ready = {"status": "not_ready", "reason": "insufficient_time_safe_history", "eligible_rows": len(examples), "can_deploy": False, "dataset_sha256": request["dataset_sha256"], "quality_warning": "No missing periods or historical availability timestamps were fabricated."}
    if len(train_indices) < 6 or len(validation) != 12 or len(test) != 12:
        return not_ready
    train_indices = [i for i in train_indices if known(rows[i], cutoff(dates[validation[0]]), research)]
    refit_indices = train_indices + [i for i in validation if known(rows[i], cutoff(dates[test[0]]), research)]
    if len(train_indices) < 6 or len(refit_indices) < 18:
        return not_ready
    candidates = {}; fitted = {}
    for method in METHODS:
        initial = None if method in METHODS[:3] else fit(method, examples, train_indices, request["seed"])
        model = None if method in METHODS[:3] else fit(method, examples, refit_indices, request["seed"])
        candidates[method] = {"validation": evaluate_metrics([examples[i]["actual"] for i in validation], predict_set(method, initial, examples, validation)), "test": evaluate_metrics([examples[i]["actual"] for i in test], predict_set(method, model, examples, test))}
        fitted[method] = model
    selected = min(METHODS, key=lambda method: (candidates[method]["validation"]["WAPE_pct"] if candidates[method]["validation"]["WAPE_pct"] is not None else candidates[method]["validation"]["MAE_t"], candidates[method]["validation"]["MAE_t"]))
    model_id = canonical_sha({"snapshot_id": request["snapshot_id"], "sha": request["dataset_sha256"], "product": request["product_code"], "seed": request["seed"], "mode": request["mode"], "code_sha": hashlib.sha256(Path(__file__).read_bytes()).hexdigest()})
    report = {"status": "completed", "model_id": model_id, "snapshot_id": request["snapshot_id"], "dataset_sha256": request["dataset_sha256"], "product_code": request["product_code"], "time_granularity": "monthly", "prediction_horizon": "next_month", "feature_schema": FEATURE_SCHEMA, "features": FEATURES, "seed": request["seed"], "mode": request["mode"], "selected_method": selected, "selection_basis": "validation_only", "evaluation_protocol": "one_step_monthly_fixed_model_with_past_available_actuals", "artifact_fit": "train_and_validation_only_no_test_refit", "eligible_rows": len(examples), "can_deploy": False, "promotion_status": "awaiting_human_review", "split": {"train": [dates[train_indices[0]], dates[train_indices[-1]]], "validation": [dates[validation[0]], dates[validation[-1]]], "test": [dates[test[0]], dates[test[-1]]]}, "candidates": candidates, "code_sha256": hashlib.sha256(Path(__file__).read_bytes()).hexdigest(), "environment": {name: importlib.metadata.version(name) for name in ["numpy", "scikit-learn", "xgboost", "fastapi"]}, "quality_warning": "Candidate only. No automatic deployment; small monthly history and shutdown evidence require independent review.", "postprocessing": "none; negative predictions flagged, never silently clipped"}
    artifact = {"report": report, "model": serialize_model(selected, fitted[selected])}
    content = canonical_bytes(artifact)
    directory = Path(directory); directory.mkdir(parents=True, exist_ok=True)
    path = directory / (model_id + ".json")
    write_once(path, content)
    return report | {"artifact_sha256": hashlib.sha256(content).hexdigest()}
