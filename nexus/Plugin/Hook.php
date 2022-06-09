<?php

namespace Nexus\Plugin;

class Hook
{
    private static array $callbacks = [];

    private bool $isDoingAction = false;

    public function addFilter($name, $function, $priority, $argc)
    {
        $id = $this->buildUniqueId($function);
        $isPriorityExists = isset(self::$callbacks[$priority]);
        self::$callbacks[$name][$priority][$id] = ['function' => $function, 'argc' => $argc];
        if (!$isPriorityExists && count(self::$callbacks) > 1) {
            krsort(self::$callbacks, SORT_NUMERIC);
        }
    }

    private function buildUniqueId($function): string
    {
        if (is_string($function)) {
            return $function;
        } elseif (is_object($function) && ($function instanceof \Closure)) {
            //Closure
            return spl_object_hash($function);
        } elseif (is_array($function)) {
            if (is_object($function[0])) {
                return spl_object_hash($function[0]).$function[1];
            } elseif (is_string($function[0])) {
                return $function[0].'::'.$function[1];
            }
        }
        throw new \InvalidArgumentException("Invalid function, type: " . gettype($function));
    }

    public function applyFilter($name, $value = '')
    {
        if (!isset(self::$callbacks[$name])) {
            do_log("No this hook: $name");
            return $value;
        }
        $args = func_get_args();
        reset(self::$callbacks[$name]);
        do_log("name: $name, argc: " . (func_num_args() - 1));
        do {
            foreach ((array)current(self::$callbacks[$name]) as $id => $callback) {
                $args[1] = $value;
//                do_log("name: $name, id: $id, before, params: " . json_encode(array_slice($args, 1, $callback['argc'])));
                $value = call_user_func_array($callback['function'], array_slice($args, 1, $callback['argc']));
                if ($this->isDoingAction) {
                    $value = $args[1];
                }
//                do_log("name: $name, id: $id, after, value: " . var_export($value, true));
            }
        }
        while (next(self::$callbacks[$name]) !== false);
        $this->isDoingAction = false;
        return $value;
    }

    public function addAction($name, $function, $priority, $argc)
    {
        return $this->addFilter($name, $function, $priority, $argc);
    }

    public function doAction($name, ...$args)
    {
        $this->isDoingAction = true;
        $this->applyFilter(...func_get_args());
    }

    public function dump()
    {
        echo '<pre>';
        var_dump(self::$callbacks);
        echo '</pre>';
    }
}
