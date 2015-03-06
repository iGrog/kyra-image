<?php

namespace kyra\image\models;

use kyra\common\Transliter;
use Yii;
use yii\base\Exception;
use yii\helpers\ArrayHelper;
use yii\web\UploadedFile;

/**
 * This is the model class for table "images".
 *
 * @property string $IID
 * @property string $FileName
 *
 */
class Image extends \yii\db\ActiveRecord
{
    const IMAGE_UPLOADED = 'Kyra.Image.ImageUploaded';

    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'images';
    }

    public static function Sizes2StringArray($sizes)
    {
        $ret = [];
        foreach($sizes as $key=>$item)
        {
            list($w, $h) = $item;
            $q = isset($item[2]) ? intVal($item[2]) : 90;

            if($w == 0 && $h == 0) $str = ['Type' => 'Original', 'Str' => 'Keep original'];
            else if($w == 0 && $h != 0) $str = ['Type' => 'ResizeHeight', 'H' => $h, 'Str' => 'Resize to '.$h.'px height'];
            else if($w != 0 && $h == 0) $str = ['Type' => 'ResizeWidth', 'W' => $w, 'Str' => 'Resize to '.$w. 'px width'];
            else $str = ['Type' => 'Crop', 'W' => $w, 'H' => $h, 'Str' => 'Crop to '.$w.'x'.$h.'px'];
            $ret[$key] = $str;
        }
        return $ret;
    }


    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['FileName'], 'required'],
            [['FileName'], 'string', 'max' => 255]
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'IID' => 'Iid',
            'FileName' => 'File Name',
        ];
    }

    public  static function GetPathGeneratorByUploadParams($uploadParams)
    {
        $pathGenerator = isset($uploadParams['pathGeneratorClass'])
                        ? Yii::createObject($uploadParams['pathGeneratorClass'])
                        : new DefaultPathGenerator();

        return $pathGenerator;
    }

    public static function GetPathGenerator($path)
    {
        $imgModule = Yii::$app->getModule('kyra.image');
        $uploadParams = $imgModule->uploadParams[$path];
        return self::GetPathGeneratorByUploadParams($uploadParams);
    }


    public static function GetImageUrl($img, $path, $key)
    {
        $imgModule = Yii::$app->getModule('kyra.image');
        $uploadParams = $imgModule->uploadParams[$path];
        $pathGenerator = self::GetPathGeneratorByUploadParams($uploadParams);

        $paths = $pathGenerator->GeneratePaths(array_merge($uploadParams, $img));
        if(isset($paths[$key]))
            return $paths[$key]['REL'];
    }

    public function CropImage($uploadParams, $folderParams, $iid, $x, $y, $width, $height, $key, $increaseVersion=true)
    {
        $orig = Image::find()->where(['IID' => $iid])->asArray()->one();
        if(empty($orig)) return false;

        if(!empty($folderParams))
            $orig = ArrayHelper::merge($orig, $folderParams); // Нужно для FolderParam, для правильного построения путей

        $pathGenerator = self::GetPathGeneratorByUploadParams($uploadParams);
        $paths = $pathGenerator->GeneratePaths(array_merge($orig, $uploadParams));
        $origFile = $paths['o']['ABS'];
        $saveFile = $paths[$key]['ABS'];

        $sizeX = $uploadParams['sizes'][$key][0];
        $sizeY = $uploadParams['sizes'][$key][1];
        $quality = isset($uploadParams['sizes'][$key][2]) ? $uploadParams['sizes'][$key][2] : 90;

        $ret = $this->CropImageFile($origFile, $saveFile, $x, $y, $width, $height, $sizeX, $sizeY, $quality);
        return $ret;
    }

    public function CropImageFile($origFile, $fileToSave, $x, $y, $width, $height, $sizeX, $sizeY, $quality=80)
    {
        $origImage = Yii::$app->image->load($origFile);

        $origImage->crop($width, $height, $x, $y);
        $origImage->resize($sizeX, $sizeY, Yii\image\drivers\Image::NONE);
        $ret = $origImage->save($fileToSave, $quality);

        return $ret;
    }

    public function AddImage(UploadedFile $img, $uploadParams, $uid = 0, $imgParams = [])
    {
        $imgData = [
            'name' => $img->name,
            'tempName' => $img->tempName,
            'size' => $img->size,
            'type' => $img->type,
            'img' => $img,
        ];
        return $this->RealAddImage($imgData, $uploadParams, $uid, $imgParams);
    }


    public function RealAddImage($imgData, $uploadParams, $uid = 0, $imgParams = [])
    {
        if (empty($imgData)) return ['hasError' => true, 'error' => 'Empty file'];
        if (empty($uploadParams)) return ['hasError' => true, 'error' => 'Empty uploadParams'];
        if (empty($uid)) $uid = 0;

        // Привести имя файла в нормальный вид, транслитерировать и в нижний регистр
        $pInfo = pathinfo($imgData['name']);
        $name = trim(strtolower(Transliter::cleanString($pInfo['filename']) . '.' . $pInfo['extension']));

        try
        {
            $imgSize = getimagesize($imgData['tempName']);
            $i = new Image;
            $i->FileName = $name;
            $i->UID = empty($uid) ? null : $uid;
            $i->FileTitle = isset($imgParams['title']) ? $imgParams['title'] : null;
            $i->FileDesc = isset($imgParams['desc']) ? $imgParams['desc'] : null;
            $i->FileSize = $imgData['size'];
            $i->FileType = $imgData['type'];
            $i->Width = $imgSize[0];
            $i->Height = $imgSize[1];
            $i->Orientation = $imgSize[0] == $imgSize[1]
                            ? 'S'
                            : ($imgSize[0] > $imgSize[1] ? 'L' : 'P');
            $i->Exif = isset($uploadParams['saveExif'])
                        ? @json_encode(@exif_read_data($imgData['tempName']))
                        : null;
            $i->save(false);
            $iid = $i->IID;
            if (empty($iid)) return ['hasError' => false, 'error' => 'Error in DB while adding image'];

            $pathGenerator = isset($uploadParams['pathGeneratorClass'])
                            ? Yii::createObject($uploadParams['pathGeneratorClass'])
                            : new DefaultPathGenerator();

            $data = ['IID' => $iid];

            $obj = array_merge($i->attributes,
                $imgParams,
                $uploadParams,
                ['UID' => $uid, 'FileName' => $name]
                );

            $paths = $pathGenerator->GeneratePaths($obj);

            foreach ($uploadParams['sizes'] as $key => $sizes)
            {
                // Приходится грузить каждый раз картинку заново, так как ресайзить её несколько раз не получается за 1 раз
                $origImage = Yii::$app->image->load($imgData['tempName']);
                $origWidth = $origImage->width;
                $origHeight = $origImage->height;

                $paramAbsolutePath = $paths[$key]['ABS'];
                $paramRelativePath = $paths[$key]['REL'];

                if(!is_dir($paths[$key]['ABSFOLDER']))
                    mkdir($paths[$key]['ABSFOLDER'], 0777, true);

                $w = $sizes[0];
                $h = $sizes[1];
                $quality = isset($sizes[2]) ? $sizes[2] : 90;

                // Записать оригинальный файл не изменяя его
                if (($w == 0 && $h == 0) || ($w == $origWidth && $h == $origHeight))
                {
                    if(array_key_exists('img', $imgData) && ($imgData['img'] instanceof  UploadedFile))
                    {
                        if ($imgData['img']->saveAs($paramAbsolutePath, false))
                        {
                            $data['Images'][$key] = $paramRelativePath;
                        }
                    }
                    else
                    {
                        copy($imgData['tempName'], $paramAbsolutePath);
                    }
                }
                else // Если файл надо как-то ресайзить - то тут ресайзим
                {
                    if($w !=0 && $h != 0) // Если надо просто кропнуть какую-то часть
                    {
                        $cm = CoordHelper::Calc($origWidth, $origHeight, $w, $h);

                        $origImage->crop($cm[4], $cm[5], $cm[0], $cm[1]);
                        $origImage->resize($w, $h, Yii\image\drivers\Image::NONE);
                        if ($origImage->save($paramAbsolutePath, $quality))
                            $data['Images'][$key] = $paramRelativePath;
                    }
                    else if($h == 0) // Когда надо пропорционально уменьшить фотку по заданной ширине
                    {
                        $origImage->resize($w, null);
                        if($origImage->save($paramAbsolutePath, $quality))
                            $data['Images'][$key] = $paramRelativePath;
                    }
                    else if($w == 0) // Когда надо пропорционально уменьшить фотку по заданной высоте
                    {
                        $origImage->resize(null, $h);
                        if($origImage->save($paramAbsolutePath, $quality))
                            $data['Images'][$key] = $paramRelativePath;
                    }
                }

                unset($origImage);
            }

            unlink($imgData['tempName']);
            return ['hasError' => false, 'data' => $data];
        }
        catch (Exception $ex)
        {
            return ['hasError' => true, 'error' => $ex->getMessage()];
        }
    }
}

