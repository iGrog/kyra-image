<?php

use yii\db\Schema;
use yii\db\Migration;

class m141002_114403_KyraImage extends Migration
{
    public function up()
    {
        $this->createTable('images', [
            'IID' => Schema::TYPE_PK,
            'FileName' => Schema::TYPE_STRING . '(255) NOT NULL',
            'FileTitle' => Schema::TYPE_STRING . '(255) NULL DEFAULT NULL',
            'FileDesc' => 'TEXT NULL',
            'FileSize' => Schema::TYPE_INTEGER . ' NOT NULL',
            'FileType' => Schema::TYPE_STRING . '(100) NOT NULL',
            'Width' => Schema::TYPE_INTEGER,
            'Height' => Schema::TYPE_INTEGER,
            'Orientation' => 'CHAR (1)',
            'Exif' => 'TEXT NULL',
            'UID' => Schema::TYPE_INTEGER . '(11) DEFAULT NULL'
        ]);
        $this->createIndex('user_owner_image', 'images', 'UID', false);
    }

    public function down()
    {
        echo "m141002_114403_KyraImage cannot be reverted.\n";
        return false;
    }
}
