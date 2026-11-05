<?php

namespace App\Entity;

use Nette\Utils\DateTime as NetteDateTime;
use Phlox\EntityDatabase\Entity\Attributes\Property;
use Phlox\EntityDatabase\Entity\Attributes\Table;
use Phlox\EntityDatabase\Entity\Entity;
use Phlox\EntityDatabase\Entity\EntityHelpers;

/**
 * One thing to do
 */
#[Table(name: 'tasks')]
class Task extends Entity
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
		col: 'project_id',
		key: false,
		nullable: false,
		searchable: false,
		virtual: false,
		type: 'int',
		access: 'rw',
		nativeType: 'integer',
	)]
	public int $projectId {
		set(int $value) {
			$this->projectId = $value;
			$this->_ctx->modified['projectId'] = true;
		}
		get => $this->_ctx->modified['projectId'] ?? false
		    ? $this->projectId
		    : $this->_ctx->activeRow->project_id ?? null;
	}

	#[Property(
		col: 'assignee_id',
		key: false,
		nullable: true,
		searchable: false,
		virtual: false,
		type: 'int',
		access: 'rw',
		nativeType: 'integer',
	)]
	public ?int $assigneeId = null {
		set(?int $value) {
			$this->assigneeId = $value;
			$this->_ctx->modified['assigneeId'] = true;
		}
		get => $this->_ctx->modified['assigneeId'] ?? false
		    ? $this->assigneeId
		    : $this->_ctx->activeRow->assignee_id ?? null;
	}

	#[Property(
		col: 'title',
		key: false,
		nullable: false,
		searchable: true,
		virtual: false,
		type: 'string',
		access: 'rw',
		nativeType: 'varchar',
	)]
	public string $title {
		set(string $value) {
			$this->title = $value;
			$this->_ctx->modified['title'] = true;
		}
		get => $this->_ctx->modified['title'] ?? false
		    ? $this->title
		    : $this->_ctx->activeRow->title ?? null;
	}

	#[Property(
		col: 'status',
		key: false,
		nullable: false,
		searchable: false,
		virtual: false,
		type: 'string',
		access: 'rw',
		nativeType: 'varchar',
	)]
	public string $status {
		set(string $value) {
			$this->status = $value;
			$this->_ctx->modified['status'] = true;
		}
		get => $this->_ctx->modified['status'] ?? false
		    ? $this->status
		    : $this->_ctx->activeRow->status ?? null;
	}

	#[Property(
		col: 'priority',
		key: false,
		nullable: false,
		searchable: false,
		virtual: false,
		type: 'int',
		access: 'rw',
		nativeType: 'integer',
	)]
	public int $priority {
		set(int $value) {
			$this->priority = $value;
			$this->_ctx->modified['priority'] = true;
		}
		get => $this->_ctx->modified['priority'] ?? false
		    ? $this->priority
		    : $this->_ctx->activeRow->priority ?? null;
	}

	#[Property(
		col: 'due_date',
		key: false,
		nullable: true,
		searchable: false,
		virtual: false,
		type: '\DateTime',
		access: 'rw',
		nativeType: 'date',
	)]
	public ?\DateTime $dueDate = null {
		set(string|\DateTimeInterface|null $value) {
			$this->dueDate = match(true) {
			    $value === null => null,
			    $value instanceof \DateTimeInterface => NetteDateTime::from($value),
			    default => EntityHelpers::parseDateTime($value, 'Y-m-d H:i:s'),
			};
			$this->_ctx->modified['dueDate'] = true;
		}
		get => $this->_ctx->modified['dueDate'] ?? false
		    ? $this->dueDate
		    : $this->_ctx->activeRow->due_date ?? null;
	}

	#[Property(type: '\App\Entity\Project', ref: 'projects', through: 'project_id', access: 'r')]
	public ?Project $project {
		get => ($row = $this->_ctx->activeRow?->ref('projects', 'project_id')) === null
		    ? null
		    : $this->_ctx->selection?->entityFactory->create($row, $this->_ctx->selection);
	}

	#[Property(type: '\App\Entity\User', ref: 'users', through: 'assignee_id', access: 'r')]
	public ?User $assignee {
		get => ($row = $this->_ctx->activeRow?->ref('users', 'assignee_id')) === null
		    ? null
		    : $this->_ctx->selection?->entityFactory->create($row, $this->_ctx->selection);
	}
}
