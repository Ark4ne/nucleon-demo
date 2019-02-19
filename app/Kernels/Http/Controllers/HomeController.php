<?php

namespace App\Kernels\Http\Controllers;

/**
 * Class HomeController
 *
 * @package Controllers
 */
class HomeController extends ControllerBase
{

    public function indexAction()
    {
        $this->view->render('home', 'index');
    }
}
