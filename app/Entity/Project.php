<?php

namespace App\Entity;

use Nette\Utils\DateTime as NetteDateTime;
use Phlox\EntityDatabase\Database\Table\GroupedSelection;
use Phlox\EntityDatabase\Entity\Attributes\Property;
use Phlox\EntityDatabase\Entity\Attributes\Table;
use Phlox\EntityDatabase\Entity\Entity;
use Phlox\EntityDatabase\Entity\EntityHelpers;

/**
 * A set of tasks
 */
#[Table(name: 'projects')]
class Project extends Entity
{
	#[Property(
		col: 'id',
		key: true,
		nullable: true,
		searchable: false,
		virtual: false,
		type: 'int',
		access: 'rw',
		nativeType: 'integer',
	)]
	public ?int $id = null {
		set(?int $value) {
			$this->id = $value;
			$this->_ctx->modified['id'] = true;
		}
		get => $this->_ctx->modified['id'] ?? false
		    ? $this->id
		    : $this->_ctx->activeRow->id ?? null;
	}

	#[Property(
		col: 'owner_id',
		key: false,
		nullable: false,
		searchable: false,
		virtual: false,
		type: 'int',
		access: 'rw',
		nativeType: 'integer',
	)]
	public int $ownerId {
		set(int $value) {
			$this->ownerId = $value;
			$this->_ctx->modified['ownerId'] = true;
		}
		get => $this->_ctx->modified['ownerId'] ?? false
		    ? $this->ownerId
		    : $this->_ctx->activeRow->owner_id ?? null;
	}

	#[Property(
		col: 'name',
		key: false,
		nullable: false,
		searchable: true,
		virtual: false,
		type: 'string',
		access: 'rw',
		nativeType: 'varchar',
	)]
	public string $name {
		set(string $value) {
			$this->name = $value;
			$this->_ctx->modified['name'] = true;
		}
		get => $this->_ctx->modified['name'] ?? false
		    ? $this->name
		    : $this->_ctx->activeRow->name ?? null;
	}

	#[Property(
		col: 'created_at',
		key: false,
		nullable: false,
		searchable: false,
		virtual: false,
		type: '\DateTime',
		access: 'rw',
		nativeType: 'datetime',
	)]
	public \DateTime $createdAt {
		set(string|\DateTimeInterface|null $value) {
			$this->createdAt = match(true) {
			    $value === null => null,
			    $value instanceof \DateTimeInterface => NetteDateTime::from($value),
			    default => EntityHelpers::parseDateTime($value, 'Y-m-d H:i:s'),
			};
			$this->_ctx->modified['createdAt'] = true;
		}
		get => $this->_ctx->modified['createdAt'] ?? false
		    ? $this->createdAt
		    : $this->_ctx->activeRow->created_at ?? null;
	}

	#[Property(type: '\App\Entity\User', ref: 'users', through: 'owner_id', access: 'r')]
	public ?User $owner {
		get => ($row = $this->_ctx->activeRow?->ref('users', 'owner_id')) === null
		    ? null
		    : $this->_ctx->selection?->entityFactory->create($row, $this->_ctx->selection);
	}

	/** @var \Phlox\EntityDatabase\Database\Table\GroupedSelection<\App\Entity\Task> */
	#[Property(related: 'tasks', through: 'project_id', access: 'r')]
	public GroupedSelection $tasks {
		get => $this->_ctx->selection->createGroupedSelection(
		    $this->_ctx->activeRow->related('tasks', 'project_id')
		);
	}

	/** @var \Phlox\EntityDatabase\Database\Table\GroupedSelection<\App\Entity\Task> */
	#[Property(related: 'tasks', through: 'project_id', access: 'r', condition: 'status <> \'done\'')]
	public GroupedSelection $openTasks {
		get => $this->_ctx->selection->createGroupedSelection(
		    $this->_ctx->activeRow->related('tasks', 'project_id')
		)
		    ->where('status <> \'done\'');
	}
}
