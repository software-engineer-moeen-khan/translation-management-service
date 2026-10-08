<?php

use Illuminate\Support\Facades\Schedule;

// Commands live in app/Console/Commands and are discovered automatically.

// Expired tokens are already rejected; this only keeps the table small.
Schedule::command('sanctum:prune-expired --hours=24')->daily();
