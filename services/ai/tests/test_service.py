"""Synthetic fixtures test software contracts; these are not refinery observations."""
import calendar
import copy
import hashlib
import json
import sys
from pathlib import Path

import pytest
from fastapi.testclient import TestClient

sys.path.insert(0, str(Path(__file__).resolve().parents[1]))
from app.main import app
from app.training import build_examples, canonical_sha, evaluate_metrics, estimator, predict_json, serialize_model


def rows():
    result = []
    for year in range(2021, 2024):
        for month in range(1, 13):
            end = calendar.monthrange(year, month)[1]
            result.append({"period_month": f"{year}-{month:02}", "quantity": str(100 + month * 3 + (year - 2021) * 5), "available_at": f"{year}-{month:02}-{end}T12:00:00Z", "approved_at": f"{year}-{month:02}-{end}T13:00:00Z", "status": "approved"})
    return result


def payload(data=None, mode="approved"):
    data = data or rows()
    return {"snapshot_id": "synthetic-fixture", "dataset_sha256": canonical_sha(data), "product_code": "DIESEL", "seed": 42, "rows": data, "mode": mode}


@pytest.fixture
def client(tmp_path, monkeypatch):
    monkeypatch.setenv("IDPS_AI_SERVICE_KEY", "test-only-key")
    monkeypatch.setenv("IDPS_AI_ARTIFACT_DIR", str(tmp_path))
    with TestClient(app) as result:
        result.headers["X-IDPS-Service-Key"] = "test-only-key"
        yield result


def test_auth_fails_closed(client, monkeypatch):
    assert client.get("/health").status_code == 200
    assert client.post("/internal/train", json=payload(), headers={"X-IDPS-Service-Key": "bad"}).status_code == 401
    monkeypatch.delenv("IDPS_AI_SERVICE_KEY")
    assert client.post("/internal/train", json=payload()).status_code == 503


def test_rejects_hash_duplicates_unapproved_and_serialized_input(client):
    request = payload()
    request["dataset_sha256"] = "0" * 64
    assert client.post("/internal/train", json=request).status_code == 422
    data = rows() + [rows()[0]]
    assert client.post("/internal/train", json=payload(data)).status_code == 422
    data = rows(); data[0]["status"] = "provisional"
    assert client.post("/internal/train", json=payload(data)).status_code == 422
    request = payload(); request["artifact_path"] = "untrusted.joblib"
    assert client.post("/internal/train", json=request).status_code == 422


def test_features_ignore_target_and_exclude_late_revision_and_gap():
    data = rows()
    original = build_examples(data)
    changed = copy.deepcopy(data); changed[24]["quantity"] = "999999"
    assert original[24]["features"] == build_examples(changed)[24]["features"]
    changed = copy.deepcopy(data); changed[23]["approved_at"] = "2026-01-01T00:00:00Z"
    assert 24 not in build_examples(changed)
    changed = data[:10] + data[11:]
    assert all(example["period_month"] != "2021-12" for example in build_examples(changed).values())


def test_zero_metrics_and_sparse_history():
    assert evaluate_metrics([0, 0], [1, 0])["WAPE_pct"] is None


def test_training_compares_same_dates_keeps_holdout_and_requires_promotion(client):
    response = client.post("/internal/train", json=payload())
    assert response.status_code == 200, response.text
    result = response.json()
    assert result["status"] == "completed"
    assert result["can_deploy"] is False
    assert result["promotion_status"] == "awaiting_human_review"
    assert set(result["candidates"]) == {"last_month", "mean3", "seasonal12", "ridge", "random_forest", "xgboost"}
    assert all(candidate["validation"]["n"] == 12 and candidate["test"]["n"] == 12 for candidate in result["candidates"].values())
    assert result["split"]["test"] == ["2023-01", "2023-12"]
    request = {"model_id": result["model_id"], "artifact_sha256": result["artifact_sha256"], "dataset_sha256": result["dataset_sha256"], "product_code": "DIESEL", "feature_schema": result["feature_schema"], "period_month": "2024-01", "as_of": "2023-12-31T23:59:59Z", "rows": rows()}
    assert client.post("/internal/predict", json=request).status_code == 409
    promote = {"model_id": result["model_id"], "artifact_sha256": result["artifact_sha256"], "approval_reference": "laravel-approved-event-id", "approved_by": "independent-reviewer"}
    assert client.post("/internal/promote", json=promote).status_code == 200
    forecast = client.post("/internal/predict", json=request)
    assert forecast.status_code == 200, forecast.text
    assert forecast.json()["model_version"] == result["model_id"]
    assert forecast.json()["prediction"] >= 0
    request["product_code"] = "LPG"
    assert client.post("/internal/predict", json=request).status_code == 409


def test_research_never_promotes_and_tampered_artifact_rejected(client, tmp_path):
    result = client.post("/internal/train", json=payload(mode="research_only")).json()
    promotion = {"model_id": result["model_id"], "artifact_sha256": result["artifact_sha256"], "approval_reference": "decision", "approved_by": "reviewer"}
    assert client.post("/internal/promote", json=promotion).status_code == 409
    artifact = tmp_path / (result["model_id"] + ".json")
    artifact.write_text("{}")
    assert client.post("/internal/promote", json=promotion).status_code == 409


def test_sparse_returns_not_ready_without_fabrication(client):
    result = client.post("/internal/train", json=payload(rows()[:8])).json()
    assert result["status"] == "not_ready"
    assert result["can_deploy"] is False
    assert result["eligible_rows"] <= 5


def test_late_approvals_block_historical_training_and_bad_utc_is_rejected(client):
    data = rows()
    for row in data:
        row["approved_at"] = "2026-01-01T00:00:00Z"
    assert client.post("/internal/train", json=payload(data)).json()["status"] == "not_ready"
    data = rows(); data[0]["available_at"] = "2021-01-31T12:00:00"
    assert client.post("/internal/train", json=payload(data)).status_code == 422


@pytest.mark.parametrize("method", ["ridge", "random_forest", "xgboost"])
def test_data_only_serialization_matches_estimator_without_pickle(method):
    import numpy as np
    features = np.array([[i * 1.5, i / 3, .5, -.5] for i in range(24)])
    quantities = np.array([100 + i * 2.1 for i in range(24)])
    model = estimator(method, 42).fit(features, quantities)
    data = serialize_model(method, model)
    restored = json.loads(json.dumps(data))
    for example in features[[0, 11, 23]]:
        assert predict_json(restored, example, None) == pytest.approx(float(model.predict(np.array([example]))[0]), rel=1e-6)


def test_concurrent_artifact_writes_are_immutable_and_idempotent(tmp_path):
    from concurrent.futures import ThreadPoolExecutor
    from app.training import write_once
    path = tmp_path / "model.json"
    content = b'{"synthetic":true}'
    with ThreadPoolExecutor(max_workers=4) as executor:
        list(executor.map(lambda _: write_once(path, content), range(12)))
    assert path.read_bytes() == content
    with pytest.raises(ValueError, match="immutable_artifact_conflict"):
        write_once(path, b"changed")
    assert path.read_bytes() == content
