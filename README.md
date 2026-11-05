# Phlox API sample

A small application using the Phlox API libraries together: REST and JSON-RPC, the query language over entities and resources, DTOs with validation, and generated documentation. `tests/SmokeTest.phpt` runs all of it over real HTTP.

## Running it

```bash
composer install
composer setup     # creates data/app.db
composer serve     # http://localhost:8000
composer test
```

The tokens are `token-of-ann`, who is an admin, and `token-of-bob`.

```bash
curl -H "Authorization: Bearer token-of-ann" http://localhost:8000/api/v1/projects

curl -X POST -H "Authorization: Bearer token-of-ann" -H "Content-Type: application/json" \
     -d '{"take":10,"skip":0,"select":"id,name,owner(name),openTasks(id,canEdit)","sort":[{"field":"name","dir":"desc"}],"filter":{"logic":"and","filters":[{"field":"name","operator":"contains","value":"o"}]}}' \
     http://localhost:8000/api/v1/projects/query

curl http://localhost:8000/api/v1/openapi.yaml

curl -X POST -H "Content-Type: application/json" -d '{"jsonrpc":"2.0","method":"rpc.discover","id":1}' http://localhost:8000/rpc
```

## Where to look

| What | Where |
|---|---|
| Starting the API, and what's left to the configuration | [`www/index.php`](www/index.php), [`config/config.neon`](config/config.neon) |
| A controller working with entities, needing no attribute for its query | [`ProjectController`](app/Api/Rest/ProjectController.php) |
| A shared base controller, with events for every query | [`BaseController`](app/Api/Rest/BaseController.php) |
| Access control and computed fields at every level | [`QueryListener`](app/Api/QueryListener.php) |
| Clients shielded from the entity: a resource and a DTO with validation | [`TaskController`](app/Api/Rest/TaskController.php), [`TaskResource`](app/Api/Resource/TaskResource.php), [`CreateTask`](app/Api/Resource/CreateTask.php) |
| A body streamed by the method, and a small file as a parameter | `attach()` and `picture()` of [`TaskController`](app/Api/Rest/TaskController.php) |
| A method writing the response itself, from a selection narrowed by a query | [`ReportController`](app/Api/Rest/ReportController.php) |
| Logging in by a token, with no session | [`TokenStorage`](app/Security/TokenStorage.php) |
| The same query over JSON-RPC | [`StatsController`](app/Api/Rpc/StatsController.php) |
| Secrets a query can't reach, and a condition of a relation | [`config/entities.neon`](config/entities.neon) |

## Entities

`app/Entity` is generated from the database and `config/entities.neon`. After changing either:

```bash
php bin/generate-entities.php
```
