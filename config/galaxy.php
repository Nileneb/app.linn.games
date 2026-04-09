<?php

return [
    'python_script' => env('GALAXY_PYTHON_SCRIPT', base_path('scripts/cluster-explorer/generate_galaxy.py')),
    'python_bin' => env('GALAXY_PYTHON_BIN', 'python3'),
];
