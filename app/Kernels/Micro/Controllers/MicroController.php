<?php

namespace App\Kernels\Micro\Controllers;

use Phalcon\Mvc\Controller;

class MicroController extends Controller
{

    public function indexAction()
    {
        return $this->response
          ->setStatusCode(200, 'OK')
          ->setJsonContent([
            'status' => 'OK',
            'code' => 200,
            'controller' => __CLASS__,
            'action' => __FUNCTION__,
          ]);
    }
}
