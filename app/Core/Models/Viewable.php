<?php

namespace App\Core\Models;

/**
 * Class Viewable
 *
 * @package     App\Core\Models
 */
trait Viewable
{
    /**
     * @param $name
     *
     * @return null
     */
    public function __get($name)
    {
        if (property_exists($this, $name)) {
            return $this->$name;
        }
        $func = str_studly($name);
        if (method_exists($this, 'get' . $func)) {
            return $this->{'get' . $func};
        }

        if (method_exists($this, 'is' . $func)) {
            return $this->{'is' . $func};
        }

        return null;
    }
}
