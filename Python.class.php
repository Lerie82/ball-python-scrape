<?php

class Python
{
    protected $vals = array();

    public function __get($key)
    {
        return $this->vals[ $key ];
    }

    public function __set($key, $value)
    {
        $this->vals[$key] = $value;
    }
}

?>
