<?php

/**
 * The whole application over real HTTP, on PHP's built-in server.
 */

declare(strict_types=1);

use Tester\Assert;

$root = dirname(__DIR__);
require $root . '/vendor/autoload.php';

Tester\Environment::setup();

require $root . '/bin/init-db.php';
Nette\Utils\FileSystem::delete($root . '/temp/cache');

$port = 18765;
$server = proc_open(
    [PHP_BINARY, '-S', "127.0.0.1:{$port}", '-t', $root . '/www', $root . '/www/index.php'],
    [1 => ['file', $root . '/temp/server.out', 'w'], 2 => ['file', $root . '/temp/server.err', 'w']],
    $pipes,
    $root,
);
Assert::type('resource', $server);

register_shutdown_function(static fn() => proc_terminate($server));

for ($i = 0; $i < 50 && !@fsockopen('127.0.0.1', $port); $i++) {
    usleep(100_000);
}

/**
 * @return array{int, string, array<string, string>, mixed}
 */
$http = static function (string $method, string $path, mixed $body = null, ?string $token = 'token-of-ann', string $type = 'application/json') use ($port): array {
    $headers = [];
    if ($token !== null) {
        $headers[] = "Authorization: Bearer {$token}";
    }
    if ($body !== null) {
        $headers[] = "Content-Type: {$type}";
    }

    $text = @file_get_contents("http://127.0.0.1:{$port}{$path}", false, stream_context_create(['http' => [
        'method' => $method,
        'header' => $headers,
        'content' => is_string($body) ? $body : ($body === null ? '' : json_encode($body)),
        'ignore_errors' => true,
        'timeout' => 20,
    ]]));

    preg_match('~ (\d{3}) ~', $http_response_header[0] ?? '', $status);
    $received = [];
    foreach ($http_response_header ?? [] as $line) {
        if (str_contains($line, ':')) {
            [$name, $value] = explode(':', $line, 2);
            $received[strtolower($name)] = trim($value);
        }
    }

    return [(int) ($status[1] ?? 0), (string) $text, $received, json_decode((string) $text, true)];
};

$api = '/api/v1';

// --- Entities, with the wrapper of the application

[$status, , , $json] = $http('GET', "{$api}/projects");
Assert::same(200, $status);
Assert::same(2, $json['value']['total']);
Assert::same('Website', $json['value']['data'][0]['name']);
Assert::match('2024-01-02T10:00:00%A%', $json['value']['data'][0]['createdAt']);

// What a Kendo UI grid posts
[$status, , , $json] = $http('POST', "{$api}/projects/query", [
    'take' => 1, 'skip' => 0,
    'sort' => [['field' => 'name', 'dir' => 'desc']],
    'filter' => ['logic' => 'and', 'filters' => [['field' => 'name', 'operator' => 'contains', 'value' => 'o']]],
    'select' => 'id,name,owner(name),openTasks(id,canEdit)',
]);
Assert::same(200, $status);
Assert::same('Mobile app', $json['value']['data'][0]['name']);
Assert::same('Bob Smith', $json['value']['data'][0]['owner']['name']);
Assert::same(4, $json['value']['data'][0]['openTasks']['data'][0]['id']);
Assert::true($json['value']['data'][0]['openTasks']['data'][0]['canEdit'], 'an event, in a nested collection');

// Access control of the event: Bob sees his own, in the total too
[, , , $json] = $http('GET', "{$api}/projects", token: 'token-of-bob');
Assert::same(1, $json['value']['total']);
Assert::same('Mobile app', $json['value']['data'][0]['name']);

// A write-only column isn't there
[$status, , , $json] = $http('POST', "{$api}/projects/query", ['select' => 'owner(apiToken)']);
Assert::same(400, $status);
Assert::same(['select.owner.apiToken' => 'is not a known field.'], $json['error']['fields']);
[$status] = $http('POST', "{$api}/projects/query", ['filter' => ['field' => 'owner.apiToken', 'operator' => 'startswith', 'value' => 'token']]);
Assert::same(400, $status);

// The condition of a relation
[, , , $json] = $http('POST', "{$api}/projects/query", ['select' => 'id,tasks(id),openTasks(id)', 'pageSize' => 1]);
Assert::same(3, $json['value']['data'][0]['tasks']['total']);
Assert::same(2, $json['value']['data'][0]['openTasks']['total'], 'without the finished one');

[$status, , , $json] = $http('GET', "{$api}/projects/99");
Assert::same(404, $status);
Assert::same('Project 99 not found.', $json['error']['message']);

// --- Authorization

[$status] = $http('GET', "{$api}/projects", token: null);
Assert::same(401, $status);
[$status] = $http('GET', "{$api}/projects", token: 'wrong');
Assert::same(401, $status);
[$status] = $http('POST', "{$api}/reports/tasks", ['select' => 'id'], token: 'token-of-bob');
Assert::same(403, $status);

// --- A resource and a DTO

[$status, , , $json] = $http('POST', "{$api}/projects/1/tasks/query", ['select' => 'id,name,label,canEdit,assignee(name)', 'filter' => ['field' => 'name', 'operator' => 'startswith', 'value' => 'write']]);
Assert::same(200, $status);
Assert::equal(['id' => 2, 'name' => 'Write the texts', 'label' => '#2 Write the texts', 'canEdit' => true, 'assignee' => ['name' => 'Bob Smith']], $json['data'][0]);

[$status, , , $json] = $http('POST', "{$api}/projects/1/tasks/query", ['select' => 'title']);
Assert::same(400, $status, 'the name of the entity is not known here');

[$status, , , $json] = $http('POST', "{$api}/projects/1/tasks", ['title' => 'Buy a domain', 'priority' => 2, 'dueDate' => '2024-05-06', 'status' => 'done']);
Assert::same(201, $status);
Assert::same('Buy a domain', $json['name']);
Assert::same('open', $json['status'], 'what the DTO does not declare can not get in');

[$status, , , $json] = $http('POST', "{$api}/projects/1/tasks", ['title' => 'x', 'priority' => 9]);
Assert::same(422, $status);
Assert::same(['title' => 'must have at least 3 characters.', 'priority' => 'must be at most 5.'], $json['error']['fields']);

// --- Bodies

[$status, , , $json] = $http('POST', "{$api}/projects/1/tasks/1/attachment", 'binary data', type: 'application/octet-stream');
Assert::same(200, $status);
Assert::same(11, $json['bytes'], 'a stream the method reads itself');

[$status, , , $json] = $http('POST', "{$api}/projects/1/tasks/1/picture", 'image', type: 'image/png');
Assert::same(5, $json['bytes']);
[$status] = $http('POST', "{$api}/projects/1/tasks/1/picture", 'text', type: 'text/plain');
Assert::same(415, $status);

[$status, , , $json] = $http('GET', "{$api}/projects/1/tasks/summary");
Assert::same(200, $status);
Assert::same(1, $json['done']);

// --- A method writing the response itself, from the selection a query narrowed

[$status, $text, $headers] = $http('POST', "{$api}/reports/tasks", ['filter' => ['field' => 'status', 'value' => 'done'], 'sort' => [['field' => 'id']]]);
Assert::same(200, $status);
Assert::same("1;Design the home page\n", $text);
Assert::contains('text/csv', $headers['content-type']);

// --- JSON-RPC

$rpc = static fn(array $call): array => $http('POST', '/rpc', ['jsonrpc' => '2.0', 'id' => 1] + $call, token: null);

[$status, , , $json] = $rpc(['method' => 'stats.tasks', 'params' => ['query' => ['select' => 'name', 'pageSize' => 1]]]);
Assert::same(200, $status);
Assert::same([['name' => 'Design the home page']], $json['result']['data']);

[, , , $json] = $rpc(['method' => 'rpc.discover']);
Assert::same('1.3.2', $json['result']['openrpc']);
Assert::same('stats.tasks', $json['result']['methods'][0]['name']);

// --- Documents

[$status, $openApiText, $headers, $openApi] = $http('GET', "{$api}/openapi.json", token: null);
Assert::same(200, $status);
Assert::same('3.1.0', $openApi['openapi']);
Assert::same('Sample API', $openApi['info']['title']);
$schemas = $openApi['components']['schemas'];

// An entity named in #[Response], without what's write-only
Assert::same(['$ref' => '#/components/schemas/Project'], $openApi['paths']['/projects']['get']['responses'][200]['content']['application/json']['schema']['properties']['value']['properties']['data']['items']);
Assert::true(isset($schemas['Project']['properties']['owner']));
Assert::false(isset($schemas['User']['properties']['apiToken']));
Assert::true(isset($schemas['Task']['properties']['canEdit']), 'what an event adds');

// A resource and its query
$tasks = $openApi['paths']['/projects/{projectId}/tasks/query']['post'];
Assert::same(['$ref' => '#/components/schemas/Query_TaskResource'], $tasks['requestBody']['content']['application/json']['schema']);
Assert::contains('assignee.name', $schemas['QueryFilter_TaskResource']['properties']['field']['enum']);
Assert::notContains('label', $schemas['QueryFilter_TaskResource']['properties']['field']['enum']);
Assert::same(['$ref' => '#/components/schemas/TaskResource'], $tasks['responses'][200]['content']['application/json']['schema']['properties']['data']['items']);

// A DTO, a code the signature can't tell, and a documented error
$create = $openApi['paths']['/projects/{projectId}/tasks']['post'];
Assert::same(['$ref' => '#/components/schemas/CreateTask'], $create['requestBody']['content']['application/json']['schema']);
Assert::same(['$ref' => '#/components/schemas/TaskResource'], $create['responses'][201]['content']['application/json']['schema']);
Assert::contains('The project is closed.', $create['responses'][409]['description']);

// What's left of security
Assert::same([['bearerAuth' => []]], $tasks['security']);

file_put_contents($root . '/temp/openapi.json', $openApiText);
[$status, $yaml] = $http('GET', "{$api}/openapi.yaml", token: null);
Assert::same(200, $status);
Assert::contains('openapi: "3.1.0"', $yaml);
file_put_contents($root . '/temp/openapi.yaml', $yaml);
file_put_contents($root . '/temp/openrpc.json', json_encode($rpc(['method' => 'rpc.discover'])[3]['result'], JSON_UNESCAPED_SLASHES));

// --- Elsewhere, the application goes on

[$status] = $http('GET', '/somewhere/else', token: null);
Assert::same(404, $status);
