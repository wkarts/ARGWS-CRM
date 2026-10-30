# HTTP447 FINALv2 — protocolo de tarifa corrigido

Fresh447 e upgrade real446→447 aprovados em bancos/webroots novos, com fonte congelada18574paths SHA256 `61676ef60e2f34022da55960e4dbeeaea12bf90c0063b3dac20f8640499eb596`. PHP8.3.6/memória128M, MariaDB10.11.14 e Chromium140. O SHA447 publicado e o CI remoto terminal são registrados separadamente pelo root.

- Fresh: installer4473,780s; Tariff A/B17,586s; LOCAL A/B31,333s; Calendar patchado final completo80,058s.
- Upgrade: instalação verdadeira do Git publicado `b47d1666f7bb788bf29ead9c9aa03ff3f36221d1`, núcleo446 e BB122; POST nativo4471,950s, BB123, preservação de310 tabelas e3 novas tabelas LOCAL vazias antes dos fluxos. Tempos finais completos constam no JSON.
- Tariff em ambos: criação/importação/vínculo pelo GUI, bruto105,53/tarifa2,53/líquido103,00, PDF e replay/contexto/CSRF. Todos os recibos foram criados sob o novo protocolo, sem reutilizar f843 ou fallback antigo.
- LOCAL em ambos: protocolo API16 sintético explicitamente qualificado antes dos consentimentos e movimentos GUI/bearer reais; caixa7, crédito15, residual78 após estornos, seis movimentos preservados por empresa, PDF/pdftotext, JSON/CSV e isolamento A/B.
- Manifestos independentes recalculam bytes/SHA256/modos, iguais ao root por arquivo:18574 fontes e18560 no runtime;14 arquivos do instalador removidos após sucesso nativo. Gates de quota>=1,5GB foram registrados antes das novas árvores.

As provas anteriores f843 permanecem intactas e limitadas ao escopo então testado. Elas não cobriam os seis REDs de replay tardio posteriormente demonstrados. As duas webroots f843 e a nova fresh6167 foram arquivadas sem perda (Gitb47+deltaTAR+manifesto completo), verificadas por bytes/modos antes de remover, com bancos `.preserve` intactos. O manifesto f843 foi recuperado com o hash original exato. A webroot do upgrade novo permanece disponível.

Calendar completo passou com o diagnóstico integrado sem ampliar timeout, filtrar história ou retirar asserções. Isso não declara resolvida a falha remota446; o diagnóstico permite recuperar resposta/log real no próximo CI. Sem merge, deploy ou chamada bancária.

Evidência: `http-resume447-final-v2.json`, SHA256 `91a18276e2ca5081a15fcfad512d391287879d0f74abf63e20ce626397e266f1`.
