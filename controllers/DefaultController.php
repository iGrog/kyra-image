<?php

    namespace kyra\image\controllers;

    use kyra\common\Transliter;
    use kyra\image\models\Image;
    use Yii;
    use yii\base\Exception;
    use yii\web\Controller;
    use yii\web\Response;
    use yii\web\UploadedFile;

    class DefaultController extends Controller
    {
        public function actionUpload()
        {
            $path = isset($_POST['path']) ? $_POST['path'] : $this->module->defaultUploadPath;
            if(!array_key_exists($path, $this->module->uploadParams)) throw new Exception('No key '. $path. ' in `uploadParams`');
            $uploadParams = $this->module->uploadParams[$path];

            $img = null;
            foreach($this->module->fieldNames as $name)
            {
                $img = UploadedFile::getInstanceByName($name);
                if(!empty($img)) break;
            }

            if(empty($img)) return ['hasError' => true, 'error' => 'No image'];
            $imgParams = isset($_POST['params']) ? $_POST['params'] : [];
            $imgParams['nameTemplate'] = $this->module->nameTemplate;
            $uid = Yii::$app->user->id;

            $i = new Image;
            $data = $i->AddImage($img, $uploadParams, $uid, $imgParams);

            Yii::$app->response->format = Response::FORMAT_JSON;
            return $data;
        }
    }