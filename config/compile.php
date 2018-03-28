<?php

$kernel =  [
    /* HTTP KERNEL */
    BASE_PATH . '/app/Kernels/Http/Kernel.php',
    BASE_PATH . '/app/Kernels/Http/Controllers/ControllerBase.php',
    BASE_PATH . '/app/Kernels/Http/Controllers/ControllerJson.php',

    /* MICRO KERNEL */
    BASE_PATH . '/app/Kernels/Micro/Kernel.php',
    BASE_PATH . '/app/Kernels/Micro/Controllers/MicroController.php',

    /* HTTP KERNEL - NO-MODULE */
    BASE_PATH . '/app/Kernels/Http/Controllers/HomeController.php',

    /* HTTP KERNEL - MODULE FRONTEND */
    BASE_PATH . '/app/Kernels/Http/Modules/Frontend/Module.php',
    BASE_PATH . '/app/Kernels/Http/Modules/Frontend/Controllers/ControllerBase.php',
    BASE_PATH . '/app/Kernels/Http/Modules/Frontend/Controllers/IndexController.php',
];

$interfaces = [];
foreach (glob(BASE_PATH . '/vendor/nucleon/framework/src/Neutrino/*') as $item) {
    if (is_file($item)) {
        $interfaces[] = $item;
    }
}
foreach (glob(BASE_PATH . '/vendor/nucleon/framework/src/Neutrino/Dotconst/*') as $item) {
    if (is_file($item)) {
        $interfaces[] = $item;
    }
}
foreach (glob(BASE_PATH . '/vendor/nucleon/framework/src/Neutrino/Config/Loader') as $item) {
    if (is_file($item)) {
        $interfaces[] = $item;
    }
}
foreach (glob(BASE_PATH . '/vendor/nucleon/framework/src/Neutrino/Interfaces/*') as $item) {
    if (is_file($item)) {
        $interfaces[] = $item;
    }
}
foreach (glob(BASE_PATH . '/vendor/nucleon/framework/src/Neutrino/Interfaces/Auth/*') as $item) {
    if (is_file($item)) {
        $interfaces[] = $item;
    }
}
foreach (glob(BASE_PATH . '/vendor/nucleon/framework/src/Neutrino/Interfaces/Middleware/*') as $item) {
    if (is_file($item)) {
        $interfaces[] = $item;
    }
}
foreach (glob(BASE_PATH . '/vendor/nucleon/framework/src/Neutrino/Interfaces/Repositories/*') as $item) {
    if (is_file($item)) {
        $interfaces[] = $item;
    }
}
foreach (glob(BASE_PATH . '/vendor/nucleon/framework/src/Neutrino/Repositories/*') as $item) {
    if (is_file($item)) {
        $interfaces[] = $item;
    }
}
foreach (glob(BASE_PATH . '/vendor/nucleon/framework/src/Neutrino/Repositories/Exceptions/*') as $item) {
    if (is_file($item)) {
        $interfaces[] = $item;
    }
}

foreach (glob(BASE_PATH . '/vendor/nucleon/framework/src/Neutrino/Providers/*') as $item) {
    if (is_file($item)) {
        $interfaces[] = $item;
    }
}

foreach (glob(BASE_PATH . '/vendor/nucleon/framework/src/Neutrino/Providers/Http/*') as $item) {
    if (is_file($item)) {
        $interfaces[] = $item;
    }
}

foreach (glob(BASE_PATH . '/vendor/nucleon/framework/src/Neutrino/Providers/Micro/*') as $item) {
    if (is_file($item)) {
        $interfaces[] = $item;
    }
}

foreach (glob(BASE_PATH . '/vendor/nucleon/framework/src/Neutrino/Support/DesignPatterns/*') as $item) {
    if (is_file($item)) {
        $interfaces[] = $item;
    }
}

foreach (glob(BASE_PATH . '/vendor/nucleon/framework/src/Neutrino/Support/DesignPatterns/Strategy/*') as $item) {
    if (is_file($item)) {
        $interfaces[] = $item;
    }
}

foreach (glob(BASE_PATH . '/vendor/nucleon/framework/src/Neutrino/Support/Fluent/*') as $item) {
    if (is_file($item)) {
        $interfaces[] = $item;
    }
}

foreach (glob(BASE_PATH . '/vendor/nucleon/framework/src/Neutrino/Support/Traits/*') as $item) {
    if (is_file($item)) {
        $interfaces[] = $item;
    }
}

foreach (glob(BASE_PATH . '/vendor/nucleon/framework/src/Neutrino/Support/Model/*') as $item) {
    if (is_file($item)) {
        $interfaces[] = $item;
    }
}

foreach (glob(BASE_PATH . '/vendor/nucleon/framework/src/Neutrino/Support/*') as $item) {
    if (is_file($item)) {
        $interfaces[] = $item;
    }
}

foreach (glob(BASE_PATH . '/vendor/nucleon/framework/src/Neutrino/View/Engines/*') as $item) {
    if (is_file($item)) {
        $interfaces[] = $item;
    }
}

foreach (glob(BASE_PATH . '/vendor/nucleon/framework/src/Neutrino/View/Engines/Volt/*') as $item) {
    if (is_file($item)) {
        $interfaces[] = $item;
    }
}

foreach (glob(BASE_PATH . '/vendor/nucleon/framework/src/Neutrino/View/Engines/Volt/Compiler/*') as $item) {
    if (is_file($item)) {
        $interfaces[] = $item;
    }
}
foreach (glob(BASE_PATH . '/vendor/nucleon/framework/src/Neutrino/View/Engines/Volt/Compiler/Extensions/*') as $item) {
    if (is_file($item)) {
        $interfaces[] = $item;
    }
}

foreach (glob(BASE_PATH . '/vendor/nucleon/framework/src/Neutrino/View/Engines/Volt/Compiler/Filters/*') as $item) {
    if (is_file($item)) {
        $interfaces[] = $item;
    }
}

$auth = [
  __DIR__.'/../vendor/nucleon/framework/src/Neutrino/Foundation/Auth/User.php'
];
foreach (glob(BASE_PATH . '/vendor/nucleon/framework/src/Neutrino/Auth/*') as $item) {
    if (is_file($item)) {
        $auth[] = $item;
    }
}
$app = [];
foreach (glob(BASE_PATH . '/app/Core/Constants/*') as $item) {
    if (is_file($item)) {
        $app[] = $item;
    }
}
foreach (glob(BASE_PATH . '/app/Core/Facades/*') as $item) {
    if (is_file($item)) {
        $app[] = $item;
    }
}
foreach (glob(BASE_PATH . '/app/Core/Models/*') as $item) {
    if (is_file($item)) {
        $app[] = $item;
    }
}
foreach (glob(BASE_PATH . '/app/Core/Providers/*') as $item) {
    if (is_file($item)) {
        $app[] = $item;
    }
}
foreach (glob(BASE_PATH . '/app/Core/Services/*') as $item) {
    if (is_file($item)) {
        $app[] = $item;
    }
}
foreach (glob(BASE_PATH . '/app/Kernels/Modules/Example/*') as $item) {
    if (is_file($item)) {
        $app[] = $item;
    }
}
foreach (glob(BASE_PATH . '/app/Kernels/Modules/Example/Controllers/*') as $item) {
    if (is_file($item)) {
        $app[] = $item;
    }
}
return array_merge($kernel, $interfaces, $auth, $app);
