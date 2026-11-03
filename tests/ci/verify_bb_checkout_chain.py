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
receipt = (tests / "banco_brasil_cnab400_receipt_mysql.php").read_text(encoding="utf-8")
receipt = re.sub(r"/\*.*?\*/", "", receipt, flags=re.DOTALL)
if not re.match(r"\A\s*<\?php\s+declare\s*\(strict_types=1\)\s*;\s*require\s+__DIR__\s*\.\s*['\"]/banco_brasil_cnab400_preparation_mysql\.php['\"]\s*;", receipt):
    raise SystemExit("Cobertura BB incompleta: recibo400 deve executar a suíte de preparação400")
preparation = (tests / "banco_brasil_cnab400_preparation_mysql.php").read_text(encoding="utf-8")
preparation = re.sub(r"/\*.*?\*/", "", preparation, flags=re.DOTALL)
if not re.match(r"\A\s*<\?php\s+declare\s*\(strict_types=1\)\s*;\s*require\s+__DIR__\s*\.\s*['\"]/banco_brasil_return_rejections_mysql\.php['\"]\s*;", preparation):
    raise SystemExit("Cobertura BB incompleta: preparação400 deve executar a suíte existente de rejeições")
print("Cadeia dos testes SQL BB deduplicados preservada.")
