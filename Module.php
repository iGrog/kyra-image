<?php

namespace kyra\image;

use yii\base\Exception;
use yii\base\Module as BaseModule;

class Module extends BaseModule
{
    public $uploadParams = [];
    public $nameTemplate = '{IID}_{UID}_{Key}_{FileName}';
    public $transliterateName = true;
    public $defaultUploadPath = 'upload';
    public $controllerNamespace = 'kyra\image\controllers';
    public $fieldNames = ['Image', 'attachment[file]'];
    public $accessRoles = ['admin'];
    public $pathGeneratorClass = '';

    public function init()
    {
        if(empty($this->uploadParams)) throw new Exception('`uploadParams` must be set in config');
    }
}
