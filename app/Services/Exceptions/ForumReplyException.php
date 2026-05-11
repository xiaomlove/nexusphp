<?php

namespace App\Services\Exceptions;

use App\Services\ForumPostService;
use RuntimeException;

/**
 * Thrown by {@see ForumPostService} when a reply cannot
 * be created — bad permissions, locked topic, anti-flood, etc.
 *
 * Caught by Livewire components and surfaced as a flash message.
 */
class ForumReplyException extends RuntimeException {}
