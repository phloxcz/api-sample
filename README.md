# Phlox API sample

**English** | [Česky](README.cs.md)

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
| A controller needing no attribute for its query, as the resource of the entity holds | [`ProjectController`](app/Api/Rest/ProjectController.php) |
| Access control holding everywhere, and relations to resources and to entities | `restrict()` of [`ProjectResource`](app/Api/Resource/ProjectResource.php) |
| A field computed with a service, only when it's asked for | `canEdit()` of [`TaskResource`](app/Api/Resource/TaskResource.php) |
| Clients shielded from the entity: a resource and a DTO with validation | [`TaskController`](app/Api/Rest/TaskController.php), [`TaskResource`](app/Api/Resource/TaskResource.php), [`CreateTask`](app/Api/Resource/CreateTask.php) |
| Saving: POST, PUT, PATCH, and a collection in one transaction | `create()`, `replace()`, `change()` and `batch()` of [`TaskController`](app/Api/Rest/TaskController.php), [`ImportTasks`](app/Api/Resource/ImportTasks.php) |
| A body streamed by the method, and a small file as a parameter | `attach()` and `picture()` of [`TaskController`](app/Api/Rest/TaskController.php) |
| A file from an HTML form, and a header | `document()` of [`TaskController`](app/Api/Rest/TaskController.php) |
| A method writing the response itself, from a selection narrowed by a query | [`ReportController`](app/Api/Rest/ReportController.php) |
| Responses described by objects: a page in an envelope, a list, counts by a key | `#[Response]` in [`ProjectController`](app/Api/Rest/ProjectController.php) and [`TaskController`](app/Api/Rest/TaskController.php) |
| Every response of the smoke test checked against the documentation | [`ResponseChecker`](tests/ResponseChecker.php) |
| Logging in by a token, with no session | [`TokenStorage`](app/Security/TokenStorage.php) |
| The same query over JSON-RPC | [`StatsController`](app/Api/Rpc/StatsController.php) |
| Secrets a query can't reach, and a condition of a relation | [`config/entities.neon`](config/entities.neon) |

## Entities

`app/Entity` is generated from the database and `config/entities.neon`. After changing either:

```bash
php bin/generate-entities.php
```
