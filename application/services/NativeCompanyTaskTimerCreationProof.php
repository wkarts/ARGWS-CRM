<?php
declare(strict_types=1);
/** Private capability issued by the final canonical writer only during its owned insertion. */
trait NativeCompanyTaskTimerCreationProof
{
    private static ?WeakMap $nativeTimerProofs=null;
    private function nativeTimerCreationProof(int$i,int$a,string$source,int$id,array$row,?int$origin=null):object
    {
        $proof=new stdClass();self::$nativeTimerProofs??=new WeakMap();self::$nativeTimerProofs[$proof]=[$this->db,$this->p,$i,$a,$source,$id,$row,$origin];return$proof;
    }
    public static function assertNativeTimerCreationProof(object$proof,$db,string$p,int$i,int$a,string$source,int$id,array$row,?int$origin=null):void
    {
        if(self::$nativeTimerProofs===null||!isset(self::$nativeTimerProofs[$proof])||self::$nativeTimerProofs[$proof]!==[$db,$p,$i,$a,$source,$id,$row,$origin])throw new DomainException('native_timer_creation_proof_required');
    }
    private static ?WeakMap $nativeTimerEditProofs=null;
    private function nativeTimerEditProof(int$i,int$a,string$source,int$id,array$row):object
    {
        $proof=new stdClass();self::$nativeTimerEditProofs??=new WeakMap();self::$nativeTimerEditProofs[$proof]=[$this->db,$this->p,$i,$a,$source,$id,$row];return$proof;
    }
    public static function assertNativeTimerEditProof(object$proof,$db,string$p,int$i,int$a,string$source,int$id,array$row):void
    {
        if(self::$nativeTimerEditProofs===null||!isset(self::$nativeTimerEditProofs[$proof])||self::$nativeTimerEditProofs[$proof]!==[$db,$p,$i,$a,$source,$id,$row])throw new DomainException('native_timer_edit_proof_required');
    }
}
