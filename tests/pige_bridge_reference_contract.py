"""Optional contract check against a separately checked-out PIGE backend.

Run with that backend and its tests directory on PYTHONPATH, and `pytest -p
conftest` so the reference supplies its disposable DB/auth fixtures. This does
not use deployed credentials or call an external CRM/bank. It is not a network
interoperability test and is intentionally separate from CRM-only CI.
"""
from sqlalchemy import func, select
from app.config import settings
from app.crm_bridge_models import CRMBridgeRequest
from app.db import SessionLocal


def test_crm_disabled_preparation_payload_and_replay(api, monkeypatch):
    monkeypatch.setattr(settings(), 'crm_bridge_enabled', True)
    monkeypatch.setattr(settings(), 'crm_bridge_connections', '{}')
    # Exact fields sent by PigeBridgeDiscovery::prepareRemote; no activation.
    payload = {
        'version': None, 'enabled': False, 'base_url': 'https://crm.example.test',
        'issuer_id': '7', 'account_id': 'crm-preparation-contract',
        'environment': 'sandbox',
        'secret': 'synthetic-preparation-contract-secret-000000001',
    }
    assert api.get('/crm-bridge/configuration')['configuration'] is None
    with SessionLocal() as db:
        before = db.scalar(select(func.count()).select_from(CRMBridgeRequest))
    created = api.call('PUT', '/crm-bridge/configuration', payload)
    assert created['version'] == 1 and created['enabled'] is False
    assert created['secret_configured'] is True
    assert set(created) == {
        'id', 'version', 'enabled', 'base_url', 'issuer_id', 'account_id',
        'environment', 'secret_configured',
    }
    for key in ('base_url', 'issuer_id', 'account_id', 'environment'):
        assert created[key] == payload[key]
    # A lost PUT response cannot authorize repeating creation or calling the
    # existing diagnostic while disabled, even with the master switch on.
    api.call('PUT', '/crm-bridge/configuration', payload, expect=409)
    api.call('POST', '/crm-bridge/connection-test', expect=409)
    assert api.get('/crm-bridge/configuration')['configuration'] == created
    with SessionLocal() as db:
        assert db.scalar(select(func.count()).select_from(CRMBridgeRequest)) == before
    # A financial bridge HMAC is not an administrative Bearer credential.
    response = api.client.get(
        api.base + '/crm-bridge/configuration',
        headers={'Authorization': 'Bearer ' + payload['secret']},
    )
    assert response.status_code == 401
