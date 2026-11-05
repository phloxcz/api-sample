<?php

declare(strict_types=1);

// Creates the SQLite database of the sample, with a few rows.

$dir = __DIR__ . '/../data';
@mkdir($dir, 0777, true);
$file = $dir . '/app.db';
if (is_file($file)) {
    unlink($file);
}

$pdo = new PDO("sqlite:{$file}");
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$pdo->exec('CREATE TABLE users (
    id INTEGER PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL,
    api_token VARCHAR(64) NOT NULL
)');
$pdo->exec('CREATE TABLE projects (
    id INTEGER PRIMARY KEY,
    owner_id INTEGER NOT NULL REFERENCES users(id),
    name VARCHAR(100) NOT NULL,
    created_at DATETIME NOT NULL
)');
$pdo->exec("CREATE TABLE tasks (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    project_id INTEGER NOT NULL REFERENCES projects(id),
    assignee_id INTEGER REFERENCES users(id),
    title VARCHAR(200) NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'open',
    priority INTEGER NOT NULL DEFAULT 1,
    due_date DATE
)");

$at = static fn(string $date): int => (int) strtotime($date); // Nette keeps dates of SQLite as Unix timestamps

$pdo->exec("INSERT INTO users VALUES (1, 'Ann Novak', 'ann@example.com', 'token-of-ann'), (2, 'Bob Smith', 'bob@example.com', 'token-of-bob')");
$pdo->exec("INSERT INTO projects VALUES (1, 1, 'Website', {$at('2024-01-02 10:00:00')}), (2, 2, 'Mobile app', {$at('2024-03-04 12:00:00')})");
$pdo->exec("INSERT INTO tasks VALUES
    (1, 1, 1, 'Design the home page', 'done', 2, {$at('2024-02-01')}),
    (2, 1, 2, 'Write the texts', 'open', 1, {$at('2024-02-15')}),
    (3, 1, NULL, 'Find a photographer', 'open', 3, NULL),
    (4, 2, 2, 'Set up the build', 'open', 2, {$at('2024-04-01')})");

echo "Created {$file}\n";
