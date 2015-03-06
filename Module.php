<?php

namespace kyra\image;

use Yii;
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
        $this->registerTranslations();
    }

    public function registerTranslations()
    {
        Yii::$app->i18n->translations['kyra.image/*'] = [
            'class' => 'yii\i18n\PhpMessageSource',
            'sourceLanguage' => 'en-US',
            'basePath' => '@app/modules/users/messages',
            'fileMap' => [
                'modules/users/validation' => 'validation.php',
                'modules/users/form' => 'form.php'
            ],
        ];
    }

    public static function t($category, $message, $params = [], $language = null)
    {
        return Yii::t('modules/users/' . $category, $message, $params, $language);
    }
}
