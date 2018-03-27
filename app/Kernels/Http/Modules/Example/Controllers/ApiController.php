<?php

namespace App\Kernels\Http\Modules\Example\Controllers;

/**
 * Class ApiController
 *
 * @package App\Kernels\Http\Modules\Example\Controllers
 */
class ApiController extends ControllerBase
{

    public function indexAction()
    {
        $this->view->render('example/api', 'index');
    }
}
