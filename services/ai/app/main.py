"""Authenticated private API. Run: python -m uvicorn app.main:app --host 127.0.0.1 --port 8001."""
from __future__ import annotations

import hashlib
import hmac
import json
import os
import re
from decimal import Decimal, InvalidOperation
from pathlib import Path
from typing import Literal

from fastapi import Depends, FastAPI, Header, HTTPException
from pydantic import BaseModel, ConfigDict, Field, field_validator

from .training import FEATURE_SCHEMA, build_examples, canonical_bytes, canonical_sha, cutoff, month_number, predict_json, timestamp, train, write_once

app = FastAPI(title="IDPS private monthly AI worker", version="1.0.0", docs_url=None, redoc_url=None)


class Contract(BaseModel):
    model_config = ConfigDict(extra="forbid")


class Row(Contract):
    period_month: str
    quantity: str
    available_at: str
    approved_at: str | None = None
    status: Literal["approved", "provisional"]

    @field_validator("period_month")
    @classmethod
    def period(cls, value): month_number(value); return value

    @field_validator("available_at", "approved_at")
    @classmethod
    def date(cls, value):
        if value is not None: timestamp(value)
        return value

    @field_validator("quantity")
    @classmethod
    def quantity_valid(cls, value):
        try: decimal = Decimal(value)
        except InvalidOperation: raise ValueError("invalid_quantity") from None
        if not decimal.is_finite() or decimal < 0 or decimal > Decimal("99999999999999.999999"):
            raise ValueError("invalid_quantity")
        return value


class TrainingRequest(Contract):
    snapshot_id: str = Field(min_length=1, max_length=191)
    dataset_sha256: str = Field(pattern=r"^[a-f0-9]{64}$")
    product_code: str = Field(pattern=r"^[A-Za-z0-9_\-]{1,64}$")
    seed: int = Field(default=42, ge=0, le=2147483647)
    mode: Literal["approved", "research_only"] = "approved"
    rows: list[Row] = Field(min_length=1, max_length=2400)


class ModelReference(Contract):
    model_id: str = Field(pattern=r"^[a-f0-9]{64}$")
    artifact_sha256: str = Field(pattern=r"^[a-f0-9]{64}$")


class PromotionRequest(ModelReference):
    approval_reference: str = Field(min_length=1, max_length=191)
    approved_by: str = Field(min_length=1, max_length=191)


class PredictionRequest(ModelReference):
    dataset_sha256: str = Field(pattern=r"^[a-f0-9]{64}$")
    product_code: str
    feature_schema: str
    as_of: str
    period_month: str
    rows: list[Row] = Field(min_length=1, max_length=2400)

    @field_validator("as_of")
    @classmethod
    def date(cls, value): timestamp(value); return value

    @field_validator("period_month")
    @classmethod
    def period(cls, value): month_number(value); return value


def authorized(x_idps_service_key: str | None = Header(default=None)):
    expected = os.environ.get("IDPS_AI_SERVICE_KEY")
    if not expected: raise HTTPException(503, detail={"code": "SERVICE_NOT_CONFIGURED"})
    if not x_idps_service_key or not hmac.compare_digest(x_idps_service_key.encode("utf-8"), expected.encode("utf-8")):
        raise HTTPException(401, detail={"code": "UNAUTHORIZED"})


def artifact_directory():
    return Path(os.environ.get("IDPS_AI_ARTIFACT_DIR", str(Path(__file__).resolve().parents[3] / ".local" / "ai-artifacts")))


def validate_rows(rows, mode, sha=None):
    if sha is not None and canonical_sha(rows) != sha: raise ValueError("dataset_hash_mismatch")
    periods = [row["period_month"] for row in rows]
    if periods != sorted(set(periods)): raise ValueError("duplicate_or_unsorted_months")
    if mode == "approved" and any(row["status"] != "approved" or not row["approved_at"] for row in rows):
        raise ValueError("unapproved_dataset")


def load_artifact(reference):
    path = artifact_directory() / (reference.model_id + ".json")
    if not path.is_file(): raise HTTPException(404, detail={"code": "MODEL_NOT_FOUND"})
    content = path.read_bytes()
    if hashlib.sha256(content).hexdigest() != reference.artifact_sha256:
        raise HTTPException(409, detail={"code": "ARTIFACT_INTEGRITY_FAILED"})
    return json.loads(content)


@app.get("/health")
def health():
    return {"status": "ok", "service": "idps-ai", "scope": "monthly", "configured": bool(os.environ.get("IDPS_AI_SERVICE_KEY"))}


@app.post("/internal/train", dependencies=[Depends(authorized)])
def training(request: TrainingRequest):
    data = request.model_dump()
    try:
        validate_rows(data["rows"], data["mode"], data["dataset_sha256"])
        return train(data, artifact_directory())
    except ValueError as error:
        raise HTTPException(422, detail={"code": str(error)}) from None


@app.post("/internal/promote", dependencies=[Depends(authorized)])
@app.post("/internal/rollback", dependencies=[Depends(authorized)])
def promote(request: PromotionRequest):
    artifact = load_artifact(request)
    if artifact["report"]["mode"] != "approved":
        raise HTTPException(409, detail={"code": "RESEARCH_MODEL_BLOCKED"})
    # Laravel owns the durable human approval, separation of duties and registry transaction.
    approval = request.model_dump()
    path = artifact_directory() / (request.model_id + ".approval.json")
    if path.exists():
        previous = json.loads(path.read_bytes())
        if previous["artifact_sha256"] != request.artifact_sha256:
            raise HTTPException(409, detail={"code": "PROMOTION_CONFLICT"})
    else:
        try:
            write_once(path, canonical_bytes(approval))
        except ValueError:
            raise HTTPException(409, detail={"code": "PROMOTION_CONFLICT"}) from None
    return {"status": "approved_for_internal_serving", "model_id": request.model_id, "approval_reference": request.approval_reference}


@app.post("/internal/evaluate", dependencies=[Depends(authorized)])
def evaluate(request: ModelReference):
    """Return the immutable stored holdout; never tune or refit against this endpoint."""
    return load_artifact(request)["report"]


@app.post("/internal/predict", dependencies=[Depends(authorized)])
def predict(request: PredictionRequest):
    artifact = load_artifact(request); report = artifact["report"]
    approval_path = artifact_directory() / (request.model_id + ".approval.json")
    if report["mode"] != "approved" or not approval_path.is_file():
        raise HTTPException(409, detail={"code": "MODEL_NOT_APPROVED"})
    if json.loads(approval_path.read_bytes())["artifact_sha256"] != request.artifact_sha256:
        raise HTTPException(409, detail={"code": "APPROVAL_INTEGRITY_FAILED"})
    if request.dataset_sha256 != report["dataset_sha256"] or request.product_code != report["product_code"] or request.feature_schema != FEATURE_SCHEMA:
        raise HTTPException(409, detail={"code": "MODEL_SCOPE_MISMATCH"})
    rows = [row.model_dump() for row in request.rows]
    try:
        validate_rows(rows, "approved")
        as_of = timestamp(request.as_of)
        target_start = cutoff(request.period_month)
        if as_of >= target_start: raise ValueError("as_of_must_precede_target")
        if any(month_number(row["period_month"]) >= month_number(request.period_month) for row in rows):
            raise ValueError("target_or_future_actuals_rejected")
        # Restrict to knowledge available at the actual caller's cutoff, not month-end alone.
        from .training import known
        history = [row for row in rows if known(row, as_of)]
        placeholder = {"period_month": request.period_month, "quantity": "0", "available_at": request.as_of, "approved_at": request.as_of, "status": "approved"}
        examples = build_examples(history + [placeholder])
        example = examples.get(len(history))
        if example is None: raise ValueError("missing_time_safe_contiguous_history")
        prediction = predict_json(artifact["model"], example["features"], example["seasonal12"])
        if not math_is_valid(prediction): raise ValueError("invalid_or_negative_prediction")
        return {"status": "completed", "product": request.product_code, "time_granularity": "monthly", "prediction_horizon": "next_month", "as_of": request.as_of, "period_month": request.period_month, "dataset_sha": request.dataset_sha256, "input_sha256": canonical_sha(rows), "source_approval_status": "approved", "model_version": request.model_id, "engine_type": "statistical_baseline" if report["selected_method"] in ["last_month", "mean3", "seasonal12"] else "ml_model", "prediction": prediction, "unit": "t", "evaluation": report["candidates"][report["selected_method"]], "quality_warning": "Intervals are not calibrated; prediction depends on approved historical observations.", "uncertainty_status": "not_calibrated", "feature_schema": FEATURE_SCHEMA}
    except ValueError as error:
        raise HTTPException(409, detail={"code": str(error)}) from None


def math_is_valid(value):
    import math
    return math.isfinite(value) and value >= 0
