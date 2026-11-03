<?php
declare(strict_types=1);
/** The native matcher, shared without database/provider access. Order and labels preserve the original model. */
final class NativeSpamFilterMatcher
{
    public static function check(array $filters,string $email,string $subject,string $message):string|false
    {
        $status=false;
        foreach($filters as$filter){$type=$filter['type'];$value=$filter['value'];
            if($type==='sender'&&strtolower($value)==strtolower($email))$status='Blocked Sender';
            if($type==='subject'&&strpos('x'.strtolower($subject),strtolower($value)))$status='Blocked Subject';
            if($type==='phrase'&&strpos('x'.strtolower($message),strtolower($value)))$status='Blocked Phrase';
        }
        return$status;
    }
}
