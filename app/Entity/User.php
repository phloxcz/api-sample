<?php

namespace App\Entity;

use Phlox\EntityDatabase\Database\Table\GroupedSelection;
use Phlox\EntityDatabase\Entity\Attributes\Property;
use Phlox\EntityDatabase\Entity\Attributes\Table;
use Phlox\EntityDatabase\Entity\Entity;
use Phlox\EntityDatabase\Exception\EntityException;

/**
 * Someone who works on tasks
 */
#[Table(name: 'users')]
class User extends Entity
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
		col: 'email',
		key: false,
		nullable: false,
		searchable: true,
		virtual: false,
		type: 'string',
		access: 'rw',
		nativeType: 'varchar',
	)]
	public string $email {
		set(string $value) {
			$this->email = $value;
			$this->_ctx->modified['email'] = true;
		}
		get => $this->_ctx->modified['email'] ?? false
		    ? $this->email
		    : $this->_ctx->activeRow->email ?? null;
	}

	#[Property(
		col: 'api_token',
		key: false,
		nullable: false,
		searchable: false,
		virtual: false,
		type: 'string',
		access: 'w',
		nativeType: 'varchar',
	)]
	public string $apiToken {
		set(string $value) {
			$this->apiToken = $value;
			$this->_ctx->modified['apiToken'] = true;
		}
		get => throw new EntityException('Property $apiToken is write-only.', EntityException::WRITE_ONLY_PROPERTY);
	}

	/** @var \Phlox\EntityDatabase\Database\Table\GroupedSelection<\App\Entity\Task> */
	#[Property(related: 'tasks', through: 'assignee_id', access: 'r')]
	public GroupedSelection $tasks {
		get => $this->_ctx->selection->createGroupedSelection(
		    $this->_ctx->activeRow->related('tasks', 'assignee_id')
		);
	}
}
