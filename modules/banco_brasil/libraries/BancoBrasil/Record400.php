<?php
declare(strict_types=1);
namespace BancoBrasil;
final class Record400
{
    private string $line;
    private array $used = [];
    public function __construct() { $this->line = str_repeat(' ', 400); }
    public function put(int $start, int $end, string $value): self
    {
        if ($start < 1 || $end > 400 || $end < $start || strlen($value) !== $end - $start + 1
            || preg_match('/[^\x20-\x7E]/', $value)) throw new \InvalidArgumentException('bb_record_field_invalid');
        for ($i = $start; $i <= $end; $i++) {
            if (isset($this->used[$i])) throw new \LogicException('bb_record_overlap');
            $this->used[$i] = true;
        }
        $this->line = substr_replace($this->line, $value, $start - 1, strlen($value));
        return $this;
    }
    public function __toString(): string { return $this->line; }
}
