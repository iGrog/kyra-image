<?php

namespace kyra\image\models;

abstract class BasePathGenerator
{
    public abstract function GeneratePaths($data);

    public function GenFileNameByTemplate($template, $params)
    {
        foreach ($params as $key => $value)
        {
            if (is_array($value)) continue;
            if(empty($value)) $value = 0;
            $template = str_replace('{' . $key . '}', $value, $template);
        }

        return $template;
    }
}
