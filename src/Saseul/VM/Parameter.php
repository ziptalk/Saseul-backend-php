<?php

namespace Saseul\VM;

use Util\Hasher;

class Parameter
{
    protected $name;
    protected $type;
    protected $maxlength;
    protected $requirements;
    protected $default;
    protected $cases;

    public function __construct(array $initial_info = [])
    {
        $this->name = $initial_info['name'] ?? null;
        $this->type = $initial_info['type'] ?? Type::ANY;
        $this->maxlength = $initial_info['maxlength'] ?? 0;
        $this->requirements = $initial_info['requirements'] ?? false;
        $this->default = $initial_info['default'] ?? null;
        $this->cases = $initial_info['cases'] ?? null;
    }

    public function name(?string $name = null)
    {
        $this->name = $name ?? ($this->name);

        return $this->name;
    }

    public function type(?string $type = null)
    {
        $this->type = $type ?? ($this->type);

        return $this->type;
    }

    public function maxlength(?int $maxlength = null)
    {
        $this->maxlength = $maxlength ?? ($this->maxlength);

        return $this->maxlength;
    }

    public function requirements(?bool $requirements = null)
    {
        $this->requirements = $requirements ?? ($this->requirements);

        return $this->requirements;
    }

    public function default($default = null)
    {
        $this->default = $default ?? ($this->default);

        return $this->default;
    }

    public function setDefaultNull()
    {
        $this->default = null;
    }

    public function cases(?array $cases = null)
    {
        $this->cases = $cases ?? ($this->cases);

        return $this->cases;
    }

    public function objValidity(): bool
    {
        return is_string($this->name) &&
            is_string($this->type) &&
            is_numeric($this->maxlength) &&
            is_bool($this->requirements) &&
            (is_null($this->cases) || is_array($this->cases));
    }

    public function structureValidity($value, ?string &$err_msg): bool
    {
        # requirements;
        if ($this->requirements === true && is_null($value)) {
            $err_msg = "The data must contain the '$this->name' parameter. ";
            return false;
        }

        # set default;
        if (is_null($value)) {
            $value = $this->default;
        }

        # cases;
        if (!is_null($this->cases) && !in_array($value, $this->cases)) {
            $cases = implode(', ', $this->cases);
            $err_msg = "Parameter '$this->name' must be one of the following: $cases ";
            return false;
        }

        # maxlength;
        if (strlen(Hasher::string($value)) > $this->maxlength) {
            $err_msg = "The length of the parameter '$this->name' must be less than $this->maxlength characters. ";
            return false;
        }

        return true;
    }

    public function typeValidity($value, ?string &$err_msg): bool
    {
        # requirements;
        if ($this->requirements === false) {
            return true;
        }

        # type;
        switch ($this->type) {
            case Type::STRING:
                if (!is_string($value)) {
                    $err_msg = "Parameter '$this->name' must be of string type. ";
                    return false;
                }
                break;
            case Type::INT:
                if (!is_int($value)) {
                    $err_msg = "Parameter '$this->name' must be of integer type. ";
                    return false;
                }
                break;
            case Type::DOUBLE:
                if (!is_double($value)) {
                    $err_msg = "Parameter '$this->name' must be of double type. ";
                    return false;
                }
                break;
            case Type::ARRAY:
                if (!is_array($value)) {
                    $err_msg = "Parameter '$this->name' must be of object/array type. ";
                    return false;
                }
                break;
            case Type::BOOLEAN:
                if (!is_bool($value)) {
                    $err_msg = "Parameter '$this->name' must be of boolean type. ";
                    return false;
                }
                break;
            default:
                break;
        }

        return true;
    }

    public function obj(): array
    {
        return [
            'name' => $this->name,
            'type' => $this->type,
            'maxlength' => $this->maxlength,
            'requirements' => $this->requirements,
            'default' => $this->default,
            'cases' => $this->cases,
        ];
    }
}