<?php

namespace App\Kernels\Micro\Controllers;

use Phalcon\Mvc\Controller;

class MicroController extends Controller
{

    public function indexAction()
    {
        $this->response->setStatusCode(200);

        $this->response->setJsonContent([
          'controller' => __CLASS__,
          'action' => __FUNCTION__,
        ]);

        return $this->response;
    }
}