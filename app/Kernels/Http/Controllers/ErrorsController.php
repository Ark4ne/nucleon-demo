<?php

namespace App\Kernels\Http\Controllers;

/**
 * Class ErrorsController
 *
 * @package App\Kernels\Http\Modules\Frontend\Controllers
 */
class ErrorsController extends ControllerBase
{
    public function indexAction()
    {
        $this->response->setStatusCode(500);

        return $this->view->render('errors', 'http5xx');
    }

    public function http404Action()
    {
        $this->response->setStatusCode(404);

        return $this->view->render('errors', 'http404');
    }
}
