<?php

namespace kyra\image\models;

class CoordHelper
{
    public static function Calc($origWidth, $origHeight, $w, $h)
    {
        $koeff = $w / $h;


        // Координаты левого верхнего и правого нижнего угла
        $leftUpX = ($origWidth / 2) - ($w / 2);
        $leftUpY = ($origHeight / 2) - ($h / 2);
        $rightDownX = ($origWidth / 2) + ($w / 2);
        $rightDownY = ($origHeight / 2) + ($h / 2);

        $distY = $origHeight - $rightDownY;
        $distX = $origWidth - $rightDownX;

        if($distX < $distY) // Ограничитель у нас ось Y
        {
            $leftUpX = 0;
            $rightDownX = $origWidth;
            $deltaY = $distX / $koeff;
            $leftUpY -= $deltaY;
            $rightDownY += $deltaY;
        }
        else if($distY < $distX) // Ограничитель у нас ось X
        {
            $leftUpY = 0;
            $rightDownY = $origHeight;
            $deltaX = $koeff * $distY;
            $leftUpX -= $deltaX;
            $rightDownX += $deltaX;
        }
        else
        {

        }

        if($leftUpY < 0) $leftUpY = 0;
        if($leftUpX < 0) $leftUpX = 0;
        if($rightDownX > $origWidth) $rightDownX = $origWidth;
        if($rightDownY > $origHeight) $rightDownY = $origHeight;


        $wNew = $rightDownX - $leftUpX;
        $hNew = $rightDownY - $leftUpY;

        return [$leftUpX, $leftUpY, $rightDownX, $rightDownY, $wNew, $hNew];
    }
}