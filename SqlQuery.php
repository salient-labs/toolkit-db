<?php declare(strict_types=1);

namespace Salient\Db;

use Salient\Contract\Core\Entity\Readable;
use Salient\Contract\Core\Chainable;
use Salient\Core\Concern\ChainableTrait;
use Salient\Core\Concern\ReadableTrait;
use Salient\Utility\Get;
use LogicException;

/**
 * A simple representation of a SQL query
 *
 * @property-read array<string,mixed> $Values Parameter name => value
 */
final class SqlQuery implements Chainable, Readable
{
    use ChainableTrait;
    use ReadableTrait;

    public const AND = 'AND';
    public const OR = 'OR';

    /**
     * A list of optionally nested WHERE conditions
     *
     * To join a list of conditions with an explicit operator:
     *
     * ```php
     * <?php
     * [
     *     '__' => SqlQuery::AND,
     *     'Id = ?',
     *     'Deleted IS NULL',
     * ]
     * ```
     *
     * To use nested conditions:
     *
     * ```php
     * <?php
     * [
     *     '__' => SqlQuery::AND,
     *     'ItemKey = ?',
     *     [
     *         '__' => SqlQuery::OR,
     *         'Expiry IS NULL',
     *         'Expiry > ?',
     *     ],
     * ]
     * ```
     *
     * @var array<string|mixed[]>
     */
    public $Where = [];

    /**
     * Parameter name => value
     *
     * @var array<string,mixed>
     */
    protected $Values = [];

    /** @var callable(string): string */
    protected $ParamCallback;

    /**
     * @inheritDoc
     */
    public static function getReadableProperties(): array
    {
        return ['Values'];
    }

    /**
     * @param callable(string): string $paramCallback Applied to the name of
     * each parameter added to the query.
     */
    public function __construct(callable $paramCallback)
    {
        $this->ParamCallback = $paramCallback;
    }

    /**
     * Add a parameter and assign its query placeholder to a variable
     *
     * @param mixed $value
     * @param-out string $placeholder
     * @return $this
     */
    public function addParam(string $name, $value, ?string &$placeholder)
    {
        if (array_key_exists($name, $this->Values)) {
            throw new LogicException(sprintf('Parameter already added: %s', $name));
        }

        $placeholder = ($this->ParamCallback)($name);
        $this->Values[$name] = $value;

        return $this;
    }

    /**
     * Add a WHERE condition
     *
     * @see SqlQuery::$Where
     *
     * @param (callable(): (string|mixed[]))|string|mixed[] $condition
     * @return $this
     */
    public function where($condition)
    {
        $this->Where[] = is_callable($condition) ? $condition() : $condition;

        return $this;
    }

    /**
     * Add a list of values as a WHERE condition ("<name> IN (<value>...)")
     * unless the list is empty
     *
     * @param mixed ...$value
     * @return $this
     */
    public function whereValueInList(string $name, ...$value)
    {
        if (!$value) {
            return $this;
        }

        foreach ($value as $val) {
            $expr[] = $this->addNextParam($val);
        }
        $this->Where[] = "$name IN (" . implode(',', $expr) . ')';

        return $this;
    }

    /**
     * Prepare a WHERE condition for use in a SQL statement
     *
     * @param array<string,mixed>|null $values
     * @param-out array<string,mixed> $values
     */
    public function getWhere(?array &$values = null): ?string
    {
        $values = $this->Values;
        $where = $this->buildWhere($this->Where);

        return $where === ''
            ? null
            : $where;
    }

    /**
     * @param mixed $value
     */
    private function addNextParam($value): string
    {
        $this->addParam('param_' . count($this->Values), $value, $param);

        return $param;
    }

    /**
     * @param array<string|mixed[]> $where
     */
    private function buildWhere(array $where): string
    {
        $glue = $where['__'] ?? self::AND;
        if (!is_string($glue)) {
            throw new LogicException(sprintf(
                'Invalid operator in WHERE condition: %s',
                Get::code($glue),
            ));
        }
        unset($where['__']);
        /** @var string|array<string|mixed[]> $condition */
        foreach ($where as $condition) {
            if (is_array($condition)) {
                $condition = $this->buildWhere($condition);
                if ($condition === '') {
                    continue;
                }
                $sql[] = "($condition)";
            } else {
                $sql[] = $condition;
            }
        }

        return implode(" $glue ", $sql ?? []);
    }
}
