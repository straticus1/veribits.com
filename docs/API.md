# API Notes
- Auth: JWT bearer (mints locally for MVP); replace with Cognito JWK validation in prod.
- Webhooks: `verification.completed`, `badge.issued` (DB schema included).
- Billing: usage-metered tables present; connect your PSP in a worker/service.

## Rate Limits & Quotas
- Use `api_keys`, `quotas`, and Redis for counters. Return `429` when exceeded.
