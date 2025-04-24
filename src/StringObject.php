<?php

namespace DevTheorem\Handlebars;

/**
 * Class for Object property access on a string.
 * @implements \ArrayAccess<string|int, mixed>
 */
#[\AllowDynamicProperties]
class StringObject implements \ArrayAccess, \Stringable
{
    protected string $string;

    public function __construct(string $string)
    {
        $this->string = $string;
    }

    public function __toString(): string
    {
        return $this->string;
    }

    public function offsetExists(mixed $offset): bool
    {
        return isset($this->$offset);
    }

    public function offsetGet(mixed $offset): mixed
    {
        return $this->$offset;
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        $this->$offset = $value;
    }

    public function offsetUnset(mixed $offset): void
    {
        unset($this->$offset);
    }
}
