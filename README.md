# AlphaGO APWS Microservice

Microserviço Laravel para ingestão da AP Web Service (Arquivo da Propaganda) e exposição de APIs de consulta para as páginas de Publicidade (Marcas, Produtos e Regiões).

## Arquitetura

- `Http/Controllers`: camada HTTP
- `Http/Requests`: validação de entrada
- `Http/Resources`: normalização de saída
- `Http/Services`: orquestração de domínio (sync + consultas)
- `Repositories`: persistência e agregações
- `Models`: entidades Eloquent
- `database/migrations`: schema do microserviço

## Segurança

As rotas de dados usam middleware `internal.api` e exigem `X-API-KEY` (ou Bearer) igual a `APWS_API_KEY`.

## Endpoints principais (`/api/v1`)

- `GET /status`
- `POST /sync/run`
- `GET /sync/runs`
- `GET /sync/cursor`
- `GET /filters`
- `GET /campaigns`
- `GET /campaigns/{creativeCode}`
- `GET /analytics/summary`
- `GET /analytics/brands`
- `GET /analytics/products`
- `GET /analytics/regions`
- `GET /analytics/media`
- `GET /analytics/timeline`

## Variáveis de ambiente

- `APWS_API_KEY`
- `APWS_PROVIDER_BASE_URL`
- `APWS_PROVIDER_TIMEOUT`
- `APWS_PROVIDER_RETRIES`
- `APWS_PROVIDER_RETRY_SLEEP_MS`
- `APWS_SYNC_ENABLED`
- `APWS_SYNC_TEST`
- `APWS_SYNC_WEEKDAYS_ONLY`
- `APWS_SYNC_BUSINESS_START`
- `APWS_SYNC_BUSINESS_END`
- `APWS_SYNC_MINUTE`
- `APWS_SYNC_WINDOW_MINUTES`
- `APWS_SYNC_MAX_WINDOW_DAYS`
- `APWS_SYNC_INITIAL_T1`
- `APWS_SYNC_PERSIST_RAW`
- `APWS_SYNC_TIMEZONE`

## Rotina de sincronização (documentação APWS)

- O comando `apws:sync-window` controla janela com `T1/T2`.
- Em execução automática, usa `T1 = last_success_t2` (cursor local).
- Se `T1` não existir, usa `APWS_SYNC_INITIAL_T1`; se vazio, usa janela retroativa de `APWS_SYNC_WINDOW_MINUTES`.
- `T2` padrão é o horário atual (timezone configurável).
- Respeita limite do provedor de até 30 dias por consulta (`APWS_SYNC_MAX_WINDOW_DAYS`).
- Cursor só avança quando a execução é `success` (em erro, não avança).
- Agendamento padrão: **1x por hora, dias úteis, horário comercial**.

Comandos úteis:

```bash
php artisan schedule:list
php artisan apws:sync-window --dry-run
php artisan apws:sync-window
```

## Execução local (docker)

```bash
docker compose -f docker-compose-dev.yml up -d
```

Serviço disponível em `http://localhost:8004/api/v1`.
