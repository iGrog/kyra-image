<?php

namespace kyra\image\models;

use yii\base\Event;

class ImageUploadedEvent extends Event
{
    public $uploadKey;
    public $payload;
}