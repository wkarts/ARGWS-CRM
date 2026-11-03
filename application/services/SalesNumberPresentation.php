<?php
/** Same native number formats, without option lookup, hooks, models or storage. */
final class SalesNumberPresentation
{
    public static function format($number,$format,$prefix,$date,$padding)
    {
        if ($format == 1) {
            // Number based
            $number = $prefix . str_pad($number, $padding, '0', STR_PAD_LEFT);
        } elseif ($format == 2) {
            // Year based
            $number = $prefix . date('Y', strtotime($date)) . '/' . str_pad($number, $padding, '0', STR_PAD_LEFT);
        } elseif ($format == 3) {
            // Number-yy based
            $number = $prefix . str_pad($number, $padding, '0', STR_PAD_LEFT) . '-' . date('y', strtotime($date));
        } elseif ($format == 4) {
            // Number-mm-yyyy based
            $number = $prefix . str_pad($number, $padding, '0', STR_PAD_LEFT) . '/' . date('m', strtotime($date)) . '/' . date('Y', strtotime($date));
        }
        return $number;
    }
}
