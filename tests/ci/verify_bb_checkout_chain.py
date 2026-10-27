#!/usr/bin/env python3
"""Fail closed if an SQL suite deduplicated in CI stops being included."""

from pathlib import Path
import re

tests = Path(__file__).resolve().parents[1]
chain = (
    "banco_brasil_checkout_mysql.php",
    "banco_brasil_api16_components_portal_mysql.php",
    "banco_brasil_api16_components_mysql.php",
    "banco_brasil_api_percentage_discount_mysql.php",
    "banco_brasil_api_fixed_discount_mysql.php",
    "banco_brasil_api_monthly_interest_mysql.php",
)

for source, target in zip(chain, chain[1:]):
    text = (tests / source).read_text(encoding="utf-8")
    # The first executable statement must remain an unconditional require.
    prefix = re.sub(r"/\*.*?\*/", "", text, flags=re.DOTALL)
    required = r"\A\s*<\?php\s+require\s+__DIR__\s*\.\s*['\"]/" + re.escape(target) + r"['\"]\s*;"
    if not re.match(required, prefix):
        raise SystemExit(f"Cobertura BB incompleta: {source} não exige {target} incondicionalmente")
print("Cadeia dos testes SQL BB deduplicados preservada.")
