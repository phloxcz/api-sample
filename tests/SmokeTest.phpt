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

require __DIR__ . '/ResponseChecker.php';

$api = '/api/v1';
$checker = null;

/**
 * @return array{int, string, array<string, string>, mixed}
 */
$http = static function (string $method, string $path, mixed $body = null, ?string $token = 'token-of-ann', string $type = 'application/json', array $headers = []) use ($port, $api, &$checker): array {
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

    $result = [(int) ($status[1] ?? 0), (string) $text, $received, json_decode((string) $text, true)];

    // Every response of the API is checked against its documentation
    if ($checker !== null && str_starts_with($path, $api)) {
        $checker->checkRest($method, $path, $result[0], $received, $result[1]);
    }
    if ($checker !== null && $path === '/rpc' && is_array($body) && ($body['method'] ?? '') !== 'rpc.discover' && is_array($result[3]) && array_key_exists('result', $result[3])) {
        $checker->checkRpc($body['method'], $result[3]['result']);
    }

    return $result;
};

$checker = new ResponseChecker(
    $http('GET', "{$api}/openapi.json", token: null)[3],
    $http('POST', '/rpc', ['jsonrpc' => '2.0', 'method' => 'rpc.discover', 'id' => 1], token: null)[3]['result'],
    $api,
);

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

// --- Saving: PUT replaces, PATCH changes what's sent

[$status, , , $created] = $http('POST', "{$api}/projects/1/tasks", ['title' => 'To change', 'priority' => 4, 'dueDate' => '2024-05-06']);
$task = "{$api}/projects/1/tasks/{$created['id']}";

[$status, , , $json] = $http('PATCH', $task, ['priority' => 2, 'status' => 'done', 'id' => 1]);
Assert::same(200, $status);
Assert::same(2, $json['priority']);
Assert::same('To change', $json['name'], 'what was not sent stays');
Assert::same($created['dueDate'], $json['dueDate']);
Assert::same('open', $json['status'], 'what the DTO does not declare can not get in');
Assert::same($created['id'], $json['id']);

[$status, , , $json] = $http('PATCH', $task, ['dueDate' => null]);
Assert::same(200, $status);
Assert::null($json['dueDate'], 'null clears it');
Assert::same(2, $json['priority']);

[$status, , , $json] = $http('PATCH', $task, ['dueDate' => '2025-01-02']);
Assert::same(200, $status);
Assert::contains('2025-01-02', $json['dueDate'], 'a date');

[$status, , , $json] = $http('PATCH', $task, []);
Assert::same(200, $status, 'nothing to change is fine');

[$status, , , $json] = $http('PATCH', $task, ['title' => null, 'priority' => 9]);
Assert::same(422, $status);
Assert::same(['title', 'priority'], array_keys($json['error']['fields']));

[$status, , , $json] = $http('PATCH', $task, ['title' => ['a']]);
Assert::same(422, $status, 'a wrong type is the client\'s error');

[$status, , , $json] = $http('PATCH', $task, [1, 2]);
Assert::same(422, $status);

[$status] = $http('PATCH', "{$api}/projects/1/tasks/99999", ['priority' => 1]);
Assert::same(404, $status);

[$status] = $http('PATCH', "{$api}/projects/2/tasks/{$created['id']}", ['priority' => 1]);
Assert::same(404, $status, 'a task of another project');

[$status, , , $json] = $http('PUT', $task, ['title' => 'Replaced']);
Assert::same(200, $status);
Assert::same('Replaced', $json['name']);
Assert::same(1, $json['priority'], 'what was not sent gets its default');
Assert::null($json['dueDate']);

[$status, , , $json] = $http('PUT', $task, ['priority' => 3]);
Assert::same(422, $status);
Assert::same(['title' => 'is required.'], $json['error']['fields'], 'PUT needs all that is required');

[$status, , , $json] = $http('PATCH', "{$task}?query[select]=name", ['priority' => 5]);
Assert::same('Replaced', $json['name'], 'the answer can be narrowed down');
Assert::false(isset($json['priority']));

// --- A collection in a DTO

[$status, , , $json] = $http('POST', "{$api}/projects/1/tasks/batch", ['tasks' => [['title' => 'First one', 'priority' => 2], ['title' => 'Second one']]]);
Assert::same(201, $status);
Assert::count(2, $json['ids']);
$saved = $http('POST', "{$api}/projects/1/tasks/query", ['select' => 'priority', 'filter' => ['field' => 'id', 'value' => $json['ids'][0]]])[3];
Assert::same(2, $saved['data'][0]['priority'], 'saved as sent');

$tasksBefore = $http('POST', "{$api}/projects/1/tasks/query", ['select' => 'id'])[3]['total'];

[$status, , , $json] = $http('POST', "{$api}/projects/1/tasks/batch", ['tasks' => [['title' => 'Fine one'], ['title' => 'x', 'priority' => 9], ['priority' => 1]]]);
Assert::same(422, $status);
Assert::same(['tasks.1.title', 'tasks.1.priority', 'tasks.2.title'], array_keys($json['error']['fields']), 'each row, with its position');

[$status, , , $json] = $http('POST', "{$api}/projects/1/tasks/batch", ['tasks' => []]);
Assert::same(422, $status);
Assert::same(['tasks' => 'must have at least 1 items.'], $json['error']['fields']);

[$status, , , $json] = $http('POST', "{$api}/projects/1/tasks/batch", ['tasks' => 'none']);
Assert::same(422, $status, 'not a list');

Assert::same($tasksBefore, $http('POST', "{$api}/projects/1/tasks/query", ['select' => 'id'])[3]['total'], 'nothing is saved of what was refused');

$openApi = $http('GET', "{$api}/openapi.json")[3];
$patch = $openApi['paths']['/projects/{projectId}/tasks/{id}']['patch']['requestBody']['content']['application/json']['schema'] ?? null;
Assert::same(['$ref' => '#/components/schemas/CreateTask_Patch'], $patch);
Assert::same(['title'], $openApi['components']['schemas']['CreateTask']['required']);
Assert::false(isset($openApi['components']['schemas']['CreateTask_Patch']['required']));

// --- Bodies

[$status, , , $json] = $http('POST', "{$api}/projects/1/tasks/1/attachment", 'binary data', type: 'application/octet-stream');
Assert::same(200, $status);
Assert::same(11, $json['bytes'], 'a stream the method reads itself');

[$status, , , $json] = $http('POST', "{$api}/projects/1/tasks/1/picture", 'image', type: 'image/png');
Assert::same(5, $json['bytes']);
[$status] = $http('POST', "{$api}/projects/1/tasks/1/picture", 'text', type: 'text/plain');
Assert::same(415, $status);
[$status] = $http('POST', "{$api}/projects/1/tasks/1/attachment", '{}');
Assert::same(415, $status, 'a stream, but of the type it takes');

// A file from a form, and a header
$multipart = static fn(array $parts): string => implode('', array_map(
    static fn(array $part): string => "--x\r\nContent-Disposition: form-data; name=\"{$part[0]}\"" . (isset($part[2]) ? "; filename=\"{$part[2]}\"\r\nContent-Type: application/pdf" : '') . "\r\n\r\n{$part[1]}\r\n",
    $parts,
)) . "--x--\r\n";

[$status, , , $json] = $http('POST', "{$api}/projects/1/tasks/1/document", $multipart([['file', '%PDF-1', 'report.pdf'], ['note', 'Signed']]), type: 'multipart/form-data; boundary=x', headers: ['X-Request-Id: r-42']);
Assert::same(200, $status);
Assert::same(['task' => 1, 'name' => 'report.pdf', 'bytes' => 6, 'requestId' => 'r-42', 'note' => 'Signed'], $json);

[$status, , , $json] = $http('POST', "{$api}/projects/1/tasks/1/document", $multipart([['note', 'No file']]), type: 'multipart/form-data; boundary=x');
Assert::same(422, $status);
Assert::same(['file' => 'is required.'], $json['error']['fields']);

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

// --- What came matches what the documents say

Assert::same([], $checker->errors);
Assert::true($checker->checked >= 35, "only {$checker->checked} responses checked");

// It does see a difference
$page = $openApi['paths']['/projects']['get']['responses'][200]['content']['application/json']['schema'];
Assert::same([], ResponseChecker::validate(['value' => ['data' => [], 'total' => 0, 'page' => 1, 'pageSize' => 10, 'from' => null, 'to' => null]], $page, $openApi, 'x'));
Assert::same(
    ["x.value: 'from' is missing", 'x.value.data[0].name: 5 is not string', 'x.value.total: "2" is not integer'],
    ResponseChecker::validate(['value' => ['data' => [['name' => 5]], 'total' => '2', 'page' => 1, 'pageSize' => 10, 'to' => null]], $page, $openApi, 'x'),
);
