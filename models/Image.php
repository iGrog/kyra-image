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
        if (empty($img)) return ['hasError' => true, 'error' => 'Empty file'];
        if (empty($uploadParams)) return ['hasError' => true, 'error' => 'Empty uploadParams'];
        if (empty($uid)) $uid = 0;

        // Привести имя файла в нормальный вид, транслитерировать и в нижний регистр
        $pInfo = pathinfo($img->name);
        $name = trim(strtolower(Transliter::cleanString($pInfo['filename']) . '.' . $pInfo['extension']));

        try
        {
            $imgData = getimagesize($img->tempName);
            $i = new Image;
            $i->FileName = $name;
            $i->UID = empty($uid) ? null : $uid;
            $i->FileTitle = isset($imgParams['title']) ? $imgParams['title'] : null;
            $i->FileDesc = isset($imgParams['desc']) ? $imgParams['desc'] : null;
            $i->FileSize = $img->size;
            $i->FileType = $img->type;
            $i->Width = $imgData[0];
            $i->Height = $imgData[1];
            $i->Orientation = $imgData[0] == $imgData[1]
                            ? 'S'
                            : ($imgData[0] > $imgData[1] ? 'L' : 'P');
            $i->Exif = @json_encode(@exif_read_data($img->tempName));
            $i->save(false);
            $iid = $i->IID;
            if (empty($iid)) return ['hasError' => false, 'error' => 'Error in DB while adding image'];

            $pathGenerator = isset($uploadParams['pathGeneratorClass'])
                            ? Yii::createObject($uploadParams['pathGeneratorClass'])
                            : Yii::createObject('kyra/image/models/DefaultPathGenerator');
//            else
//            {
//                $absolutePath = Yii::getAlias($uploadParams[0]);
//                $relativePath = str_replace('@webroot', '', $uploadParams[0]);
//            }

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
                $origImage = Yii::$app->image->load($img->tempName);
                $origWidth = $origImage->width;
                $origHeight = $origImage->height;

                $paramAbsolutePath = $paths[$key]['ABS'];
                $paramRelativePath = $paths[$key]['REL'];

                if(!is_dir($paths[$key]['ABSFOLDER']))
                    mkdir($paths[$key]['ABSFOLDER'], 0777, true);

                $w = $sizes[0];
                $h = $sizes[1];

                // Записать оригинальный файл не изменяя его
                if (($w == 0 && $h == 0) || ($w == $origWidth && $h == $origHeight))
                {
                    if ($img->saveAs($paramAbsolutePath, false))
                    {
                        $data['Images'][$key] = $paramRelativePath;
                    }
                }
                else // Если файл надо как-то ресайзить - то тут ресайзим
                {
                    $cm = CoordHelper::Calc($origWidth, $origHeight, $w, $h);

                    $origImage->crop($cm[4], $cm[5], $cm[0], $cm[1]);
                    $origImage->resize($w, $h, Yii\image\drivers\Image::NONE);
                    if ($origImage->save($paramAbsolutePath, 90))
                    {
                        $data['Images'][$key] = $paramRelativePath;
                    }
                }

                unset($origImage);
            }

            unlink($img->tempName);
            return ['hasError' => false, 'data' => $data];
        }
        catch (Exception $ex)
        {
            return ['hasError' => true, 'error' => $ex->getMessage()];
        }
    }
}

