<?php

namespace kyra\image\models;

use Yii;
use yii\base\Exception;

class DefaultPathGenerator extends BasePathGenerator
{
    public function GeneratePaths($params)
    {
        if(!isset($params['sizes']) || empty($params['sizes'])) throw new Exception('No `sizes` key in params');
        $defPath = $params[0];
        $absPath = Yii::getAlias($defPath).'/';
        $relPath = str_replace('@webroot', '', $defPath).'/';

        $ret = [];
        foreach($params['sizes'] as $key=>$size)
        {
            $params['Key'] = $key;
            $fileName = $this->GenFileNameByTemplate($params['nameTemplate'], $params);
            $ret[$key] = [
                'ABS' => $absPath.$fileName,
                'REL' => $relPath.$fileName,
                'ABSFOLDER' => $absPath,
                'RELFOLDER' => $relPath,
            ];
        }

        return $ret;
    }


}