<?php

return [
    // Корень хранилища файлов сервлетов: <files_path>/<userId>/<servlet>/{in,out}
    'files_path' => env('WORKBENCH_FILES_PATH') ?: storage_path('app/workbench-files'),
];
