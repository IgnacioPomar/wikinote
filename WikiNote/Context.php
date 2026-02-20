<?php

namespace WikiNote;

class Context
{
	public \mysqli $mysqli;
	public ?string $userId;
	public bool $isAdmin = false;
	public array $groups = [];
	public ?array $jwtClaims = null;
}
