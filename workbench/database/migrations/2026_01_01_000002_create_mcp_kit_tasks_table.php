<?php

// The package keeps its migration as a .php.stub (published in a real app
// via `vendor:publish --tag=mcp-kit-migrations`). The workbench includes it
// directly so `migrate` creates the mcp_kit_tasks table.
return require dirname(__DIR__, 3).'/database/migrations/create_mcp_kit_tasks_table.php.stub';
