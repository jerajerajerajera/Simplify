<?php

// Livewire's error for a component that is not registered, without Livewire itself (tests/stale-code.php).
declare(strict_types=1);

namespace Livewire\Exceptions;

class ComponentNotFoundException extends \Exception {}
