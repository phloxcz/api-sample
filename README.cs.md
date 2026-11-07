# Ukázková aplikace Phlox API

[English](README.md) | **Česky**

Malá aplikace, která používá API knihovny Phlox dohromady: REST a JSON-RPC, dotazovací jazyk nad entitami a Resource, DTO s validací a generovanou dokumentaci. `tests/SmokeTest.phpt` všechno spouští přes skutečné HTTP.

## Spuštění

```bash
composer install
composer setup     # vytvoří data/app.db
composer serve     # http://localhost:8000
composer test
```

Tokeny jsou `token-of-ann` (administrátor) a `token-of-bob`.

```bash
curl -H "Authorization: Bearer token-of-ann" http://localhost:8000/api/v1/projects

curl -X POST -H "Authorization: Bearer token-of-ann" -H "Content-Type: application/json" \
     -d '{"take":10,"skip":0,"select":"id,name,owner(name),openTasks(id,canEdit)","sort":[{"field":"name","dir":"desc"}],"filter":{"logic":"and","filters":[{"field":"name","operator":"contains","value":"o"}]}}' \
     http://localhost:8000/api/v1/projects/query

curl http://localhost:8000/api/v1/openapi.yaml

curl -X POST -H "Content-Type: application/json" -d '{"jsonrpc":"2.0","method":"rpc.discover","id":1}' http://localhost:8000/rpc
```

## Kam se podívat

| Co | Kde |
|---|---|
| Spuštění API a co zbývá na konfiguraci | [`www/index.php`](www/index.php), [`config/config.neon`](config/config.neon) |
| Kontroler, který pro dotaz nepotřebuje atribut, protože platí Resource entity | [`ProjectController`](app/Api/Rest/ProjectController.php) |
| Řízení přístupu, které platí všude, a relace na Resource i na entity | `restrict()` v [`ProjectResource`](app/Api/Resource/ProjectResource.php) |
| Pole dopočítané se službou, jen když si o něj dotaz řekne | `canEdit()` v [`TaskResource`](app/Api/Resource/TaskResource.php) |
| Klienti odstínění od entity: Resource a DTO s validací | [`TaskController`](app/Api/Rest/TaskController.php), [`TaskResource`](app/Api/Resource/TaskResource.php), [`CreateTask`](app/Api/Resource/CreateTask.php) |
| Ukládání: POST, PUT, PATCH a kolekce v jedné transakci | `create()`, `replace()`, `change()` a `batch()` v [`TaskController`](app/Api/Rest/TaskController.php), [`ImportTasks`](app/Api/Resource/ImportTasks.php) |
| Tělo streamované metodou a malý soubor jako parametr | `attach()` a `picture()` v [`TaskController`](app/Api/Rest/TaskController.php) |
| Soubor z HTML formuláře a hlavička | `document()` v [`TaskController`](app/Api/Rest/TaskController.php) |
| Metoda, která zapisuje odpověď sama, z výběru zúženého dotazem | [`ReportController`](app/Api/Rest/ReportController.php) |
| Odpovědi popsané objekty: stránka v obálce, seznam, počty podle klíče | `#[Response]` v [`ProjectController`](app/Api/Rest/ProjectController.php) a [`TaskController`](app/Api/Rest/TaskController.php) |
| Každá odpověď smoke testu ověřená proti dokumentaci | [`ResponseChecker`](tests/ResponseChecker.php) |
| Přihlášení tokenem bez session | [`TokenStorage`](app/Security/TokenStorage.php) |
| Stejný dotaz přes JSON-RPC | [`StatsController`](app/Api/Rpc/StatsController.php) |
| Tajemství, na která dotaz nedosáhne, a podmínka relace | [`config/entities.neon`](config/entities.neon) |

## Entity

`app/Entity` se generuje z databáze a `config/entities.neon`. Po změně kteréhokoli z nich spusťte:

```bash
php bin/generate-entities.php
```
